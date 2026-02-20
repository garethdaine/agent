# Requirements Discovery Summary

Session: 99

## Delegation Engineering — Discovery Summary

### Overview
Build a first-class delegation layer on Agent's existing job orchestration platform. Complex work is decomposed into a directed acyclic graph (DAG) of verifiable delegated tasks with explicit authority, accountability, trust-aware assignment, and adaptive re-assignment. The system is deployed across 4 phases behind feature flags (`delegation.enabled`, `delegation.ui_enabled`), all additive-only with no modifications to existing models, services, or migrations.

---

### Key Architectural Decisions

#### Data Model & Entities

**DelegateeProfile** independently stores runner configuration — `runner_type` (string 16), `command_template` (string 2000, same `{{placeholder}}` syntax as AgentJob validated by CommandPolicy and rendered by CommandTemplateRenderer), `working_directory` (string 1024), and `env_json` (json nullable) — fully decoupled from AgentJob. AttemptSpawner creates transient AgentJobs from this config at dispatch time, passing the command_template directly to the transient AgentJob.

**DelegationGraph** status constants: `draft`, `validating` (reserved for future), `ready`, `running`, `succeeded`, `failed`, `partial`, `cancelled`. The `partial` status is new — added to support partial-completion semantics.

**DelegationTask** status constants: `pending`, `blocked`, `ready`, `assigned`, `running`, `verifying`, `succeeded`, `failed`, `cancelled`.

**Terminal status derivation** (cancel-aware tri-state):
- If graph was explicitly cancelled by user action → always `cancelled` regardless of task outcomes
- Otherwise: all tasks succeeded → `succeeded`; mix of succeeded and failed → `partial`; zero tasks succeeded → `failed`

**contract_json schema** (refined from plan):
- `required_capability` (string, references active DelegationCapability slug)
- `authority_scope` — `{allowed_working_directories: string[], allowed_env_keys: string[], max_runtime_seconds: int}` — per-attempt enforcement; max_runtime_seconds must be ≤ 86400 (global max from AgentJob validation); cannot change command or runner type
- `criticality` (enum: `low`, `medium`, `high`, `critical`) — actively influences RecoveryEvaluator retry aggressiveness and escalation threshold
- `time_constraints` — `{max_total_seconds: int}` — aggregate wall-clock ceiling across all attempts, retries, and verification for a task; distinct from per-attempt max_runtime_seconds; capped at ≤ (authority_scope.max_runtime_seconds × max_retry_attempts) + verification_timeout_seconds, enforced by ContractValidator
- `verification_strategy` (array of step definitions referencing preset check profiles or step types)
- `prompt` (string, inline task instructions) and/or `task_markdown_path` (string, file reference); inline takes precedence if both provided
- **Deferred from MVP**: `budget_constraints`, `reversibility`

#### API Format

**Linear-chain shorthand**: ordered array of task objects where each task implicitly depends on the previous one. Example: `{"tasks": [{"name": "Analyze", ...}, {"name": "Implement", ...}]}` creates Analyze → Implement. Full DAG JSON with explicit dependency edges also supported.

**Clone endpoint**: `POST /graphs/{id}/clone` creates a new draft graph. Accepts optional `mode` parameter: `all` (default, full clone copying all task definitions and dependencies) or `failed_subtree` (clone only failed tasks plus their transitive dependents, preserving internal dependencies). All statuses reset, assignment/attempt data cleared. Available on graphs in any terminal status.

**Graph validation** (`POST /graphs/{id}/validate`): synchronous — structural validation (cycle detection via Kahn's algorithm, contract schema validation, capability existence, dependency resolution, time_constraints derived cap enforcement) and file-existence checks for `task_markdown_path` all run inline. Returns result immediately; transitions to `ready` or stays `draft` with errors. The `validating` status is reserved for future async validation if heavier checks are added.

#### Verification Pipeline

**AutomatedCheckStep** uses preset check profiles defined in `config/delegation.php`. No arbitrary commands allowed in contracts. Three MVP profiles:
- `laravel_standard`: `['php artisan test', './vendor/bin/pint --test']`
- `test_only`: `['php artisan test']`
- `lint_only`: `['./vendor/bin/pint --test']`

**AiCriticStep** spawns a dedicated AgentJobRun with a review-specific prompt template and the task output as context, dispatched via ExecuteAgentRunJob. The review prompt is stored as a default PHP heredoc in the AiCriticStep class, overridable via `config('delegation.ai_critic_prompt_template')`. Uses the same job-chain callback pattern as AttemptSpawner — dispatches the review run with a chained callback, returns a `pending` result immediately (non-blocking, like HumanApprovalStep). The callback parses the run output, writes the DelegationVerificationResult, and fires `DelegationTaskVerified` to resume the pipeline.

**HumanApprovalStep** is non-blocking: creates a pending DelegationVerificationResult and returns immediately. Task stays in `verifying` status. When the user resolves via `POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve`, the controller fires a `DelegationTaskVerified` event to resume the Coordinator flow. Timeout enforcement via `verification_timeout_seconds` (default 300s); reconciler treats expired pending approvals as failures.

**VerificationPipeline** is resumable: since both AiCriticStep and HumanApprovalStep are non-blocking (return `pending`), the pipeline must track which step it's on. When `DelegationTaskVerified` fires, the Coordinator resumes the pipeline from the next step after the one that just resolved. Pipeline short-circuits on first failure.

#### Execution Engine

**Scheduling**: eager ready, lazy dispatch. All dependency-satisfied tasks transition to `ready` immediately. AttemptSpawner only dispatches up to `max_parallel_tasks_per_graph` limit. Remaining `ready` tasks are picked up when a running task completes (Coordinator checks for ready tasks on every `DelegationTaskCompleted` event), prioritized by `sequence_order` (lowest first).

**sequence_order**: auto-assigned from topological depth (Kahn's algorithm output) by DelegationGraphBuilder. Callers can optionally provide explicit `sequence_order` values on individual tasks to override priority within the same depth level.

**Completion detection**: AttemptSpawner registers a queued closure or Laravel job chain callback that fires after ExecuteAgentRunJob completes. This avoids modifying RunStateTransitionService. DelegationReconciler (every 2 minutes) serves as safety net for missed callbacks.

**Recovery classification** (combined heuristic):
- `timed_out` → always transient (retry same delegatee)
- `skipped` → always non-transient (re-delegate)
- `failed` and `killed` → check attempt's `error_code` against configurable lists in `config/delegation.php` (`transient_error_codes`, `non_transient_error_codes`) to decide

**Criticality influence on recovery**: `critical`/`high` tasks get more retry attempts and escalate to human approval sooner. Configurable via `escalation_criticality_threshold` in config (default `high`).

**Partial completion**: when a task aborts (exhausts recovery), only that task and its transitive dependents are failed. Independent branches continue running to completion. Graph terminal status determined by tri-state logic above.

**Drain cancellation**: active attempts finish naturally; succeeded tasks retain `succeeded` status; only `pending`/`blocked`/`ready` tasks are marked `cancelled`. Graph terminal status is always `cancelled` (user-initiated). Task-level results preserved for audit and potential clone-and-resume.

#### DelegateeAssigner Ranking

Primary sort: `success_rate` from DelegateeMetric sliding windows (24h, 7d). Secondary tiebreaker: lowest current load — prefer the delegatee with fewest active (running) attempts across all graphs. Distributes work evenly.

#### Coordinator Architecture

Hybrid pattern:
- **DelegationCoordinator**: single Laravel EventSubscriberInterface for primary happy-path flow — `GraphStarted` → identify root tasks → `TaskReady` → assign → spawn → `AttemptFinished` (success path) → verify → `TaskVerified` → complete → check graph completion
- **RecoveryHandler**: separate listener for `AttemptFinished` (failure path) and `RecoveryTriggered`
- **MetricsListener**: separate listener for `DelegationAttemptFinished`, throttled recomputation
- **BroadcastSubscriber**: separate listener for selective broadcast dispatch

#### Queue Topology

- AttemptSpawner dispatches ExecuteAgentRunJob on existing `agent` queue (Redis connection)
- Coordination jobs, verification jobs, metrics recomputation, and reconciler run on dedicated `delegation` queue
- Horizon config: add `supervisor-delegation` with queue `['delegation']`, timeout 900, maxProcesses `env('HORIZON_DELEGATION_MAX_PROCESSES', 2)`, auto balance — follows existing one-supervisor-per-queue pattern

#### Audit Strategy

- User-initiated actions (graph create/start/cancel/clone, profile CRUD, human approval resolution): recorded in both AuditLogger (`recordUserAction`) and DelegationEvent
- Automated coordination (task assignment, retry, re-delegation, escalation, graph completion): recorded in DelegationEvent only
- Summary audit log entry: one `recordSystemAction` call when a graph reaches terminal status, capturing final status, task counts by status, and duration

#### Frontend

**DAG visualization**: custom SVG with dagre layout library (lightweight graph layout algorithm). Nodes rendered as SVG elements with status-colored fills, edges as SVG paths. Zoom via wheel event → SVG viewBox scaling, pan via mousedown+drag → viewBox translate, node click via click event on SVG group → emit to parent. All interactions implemented with native SVG transforms and vanilla JS event handlers — no additional interaction library needed for ≤25 node graphs.

**New npm dependency**: `dagre` (no additional interaction libraries)

---

### Config: `config/delegation.php`

```php
[
    'enabled' => (bool) env('DELEGATION_ENABLED', false),
    'ui_enabled' => (bool) env('DELEGATION_UI_ENABLED', false),
    'max_tasks_per_graph' => (int) env('DELEGATION_MAX_TASKS_PER_GRAPH', 25),
    'max_concurrent_graphs_per_user' => (int) env('DELEGATION_MAX_CONCURRENT_GRAPHS', 3),
    'default_max_parallel_tasks' => (int) env('DELEGATION_DEFAULT_MAX_PARALLEL_TASKS', 5),
    'max_retry_attempts' => (int) env('DELEGATION_MAX_RETRY_ATTEMPTS', 3),
    'default_cancellation_policy' => env('DELEGATION_DEFAULT_CANCELLATION_POLICY', 'drain'),
    'verification_timeout_seconds' => (int) env('DELEGATION_VERIFICATION_TIMEOUT', 300),
    'metrics_event_throttle_seconds' => 60,
    'metrics_recomputation_interval_minutes' => 15,
    'reconciler_interval_minutes' => 2,
    'min_trust_for_retry' => 0.4,
    'min_trust_for_redelegation' => 0.6,
    'escalation_criticality_threshold' => 'high',
    'max_recovery_attempts' => 5,
    'transient_error_codes' => ['TIMEOUT', 'RATE_LIMIT', 'CONNECTION_*'],
    'non_transient_error_codes' => ['INVALID_OUTPUT', 'PERMISSION_DENIED'],
    'ai_critic_prompt_template' => null, // null = use AiCriticStep class default heredoc
    'capabilities_seed' => [
        'code_generation', 'code_review', 'testing',
        'documentation', 'refactoring', 'analysis', 'planning',
    ],
    'check_profiles' => [
        'laravel_standard' => ['php artisan test', './vendor/bin/pint --test'],
        'test_only' => ['php artisan test'],
        'lint_only' => ['./vendor/bin/pint --test'],
    ],
]
```

---

### New Entities (10 Models)

1. **DelegationCapability** — slug (string 64, unique), name (string 120), description (text nullable), is_active (boolean default true); isActive scope
2. **DelegateeProfile** — SoftDeletes; runner_type (string 16), command_template (string 2000, same {{placeholder}} syntax as AgentJob), working_directory (string 1024), env_json (json nullable), config_json (json nullable); belongsTo User; belongsToMany capabilities via pivot; hasOne metrics
3. **DelegateeCapabilityPivot** — pivot model with delegatee_profile_id + delegation_capability_id unique constraint
4. **DelegateeMetric** — belongsTo profile (unique FK); window_24h_json, window_7d_json each containing {total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms}; last_recomputed_at
5. **DelegationGraph** — SoftDeletes; statuses: draft/validating/ready/running/succeeded/failed/partial/cancelled; ACTIVE_STATUSES = [running]; TERMINAL_STATUSES = [succeeded, failed, partial, cancelled]; belongsTo User; hasMany tasks/events; cancellation_policy, max_parallel_tasks, metadata_json, error_code, error_summary, started_at, finished_at
6. **DelegationTask** — statuses: pending/blocked/ready/assigned/running/verifying/succeeded/failed/cancelled; belongsTo graph; hasMany attempts/dependencies/dependents/verificationResults; belongsTo assignedProfile (nullable); sequence_order, contract_json, assignment_reason_json, metadata_json, error_code, error_summary, started_at, finished_at
7. **DelegationTaskDependency** — belongsTo task (dependent), belongsTo dependsOnTask (prerequisite); unique constraint on (task_id, depends_on_task_id)
8. **DelegationAttempt** — statuses: running/succeeded/failed; belongsTo task/profile; belongsTo agentJobRun (nullable, nullOnDelete); attempt_number, duration_ms, error_code, error_summary, metadata_json, started_at, finished_at
9. **DelegationVerificationResult** — step_type (automated_check/ai_critic/human_approval), step_order, verdict (passed/failed/skipped/pending), evidence_json; belongsTo task/attempt (attempt nullable)
10. **DelegationEvent** — event_type (string 64), auto-incrementing sequence per graph, payload_json, event_ts; belongsTo graph; belongsTo task (nullable)

### New Services (14)

1. **GraphStateTransitionService** — atomic status transitions for DelegationGraph using `whereKey($id)->whereIn('status', $from)->update($payload)` pattern (same as RunStateTransitionService)
2. **TaskStateTransitionService** — atomic status transitions for DelegationTask using same pattern
3. **ContractValidator** — validates contract_json structure: required_capability references active capability; authority_scope keys valid with max_runtime_seconds ≤ 86400; criticality is valid enum (low/medium/high/critical); time_constraints.max_total_seconds ≤ (max_runtime_seconds × max_retry_attempts) + verification_timeout_seconds; verification_strategy has ≥1 step with valid check profiles; prompt or task_markdown_path present; check profile names exist in config
4. **DelegationGraphBuilder** — accepts raw DAG JSON or linear-chain shorthand (ordered task array with implicit sequential dependencies); creates graph+tasks+dependencies in DB transaction; validates no cycles (Kahn's algorithm), max 25 tasks, dependency resolution, contract validity via ContractValidator; auto-assigns sequence_order from topological depth with optional caller override
5. **ContractEnforcer** — intersects authority_scope.allowed_working_directories with PathPolicy allowed bases; intersects allowed_env_keys with EnvPolicy forbidden keys; applies max_runtime_seconds cap ≤ global max; returns narrowed config array or validation error if contract is impossible to satisfy
6. **DelegateeAssigner** — matches required_capability against active DelegateeProfile capabilities; ranks by success_rate from DelegateeMetric sliding windows (24h primary, 7d fallback); tiebreaks by lowest current load (fewest active running attempts across all graphs); returns ranked list with assignment_reason_json explaining selection
7. **DelegationEventWriter** — follows RunEventWriter pattern; auto-increments sequence per graph; writes DelegationEvent records
8. **DelegationCoordinator** — single EventSubscriberInterface for happy-path flow: GraphStarted → identify root tasks → TaskReady → assign → spawn → AttemptFinished(success) → verify → TaskVerified → resume pipeline or complete task → check graph completion → fire next TaskReady events
9. **AttemptSpawner** — creates DelegationAttempt record; uses ContractEnforcer for narrowed config; creates transient AgentJob from DelegateeProfile config (command_template passed directly); dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with completion callback on 'delegation' queue; links attempt to AgentJobRun via FK
10. **RecoveryHandler** — separate listener for failed attempts and RecoveryTriggered events; combined heuristic classification (timed_out=transient, skipped=non-transient, failed/killed=error_code config lookup); decision chain: retry (transient + attempts < max) → re-delegate (non-transient or exhausted) → escalate (critical/high tasks, create pending human_approval verification) → abort (propagate failure to transitive dependents only); criticality influences escalation threshold
11. **VerificationPipeline** — executes ordered steps from verification_strategy; tracks current step position for resumability; short-circuits on first failure; persists DelegationVerificationResult records; resumes from next step when DelegationTaskVerified fires after a pending step resolves
12. **DelegateeMetricsRecomputer** — event-triggered on DelegationAttemptFinished with 60s cache-lock throttle; scheduled fallback every 15 min; queries attempts within sliding windows (24h, 7d); computes total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms
13. **DelegationReconciler** — runs every 2 minutes via scheduler; detects stuck tasks (active status but terminal AgentJobRun), missed graph completions (all tasks terminal but graph not), expired human approval timeouts (pending verification results older than verification_timeout_seconds); fires missed events to trigger normal coordination flow
14. **DelegationBroadcastSubscriber** — separate listener; selectively dispatches broadcast events with enriched payloads (task names, delegatee names, progress percentages) on per-graph and per-user private channels

### Verification Step Implementations (3)

1. **AutomatedCheckStep** — resolves check profile name from verification_strategy config; looks up command array from config('delegation.check_profiles'); executes commands sequentially in task's working directory; non-zero exit code = failure; captures stdout/stderr as evidence_json
2. **AiCriticStep** — creates transient AgentJob with review prompt template (default heredoc in class, overridable via config('delegation.ai_critic_prompt_template')) + task output as context; dispatches via ExecuteAgentRunJob with job-chain callback; returns 'pending' immediately (non-blocking); callback parses structured pass/fail verdict from run output, writes DelegationVerificationResult, fires DelegationTaskVerified
3. **HumanApprovalStep** — creates pending DelegationVerificationResult with verdict='pending'; returns immediately (non-blocking); resolution via POST API endpoint fires DelegationTaskVerified event; reconciler enforces timeout by treating expired pending results as failures

### New Controllers (3)

1. **DelegationGraphController** — CRUD (index/store/show/update/destroy) + restore/validate/start/cancel/clone + events listing
2. **DelegationTaskController** — list/show tasks with attempts and verification results; POST verification resolution endpoint
3. **DelegateeProfileController** — CRUD + soft delete/restore

### New API Endpoints

All under `agent/api/v1/delegation/` prefix, gated by DelegationFeatureGate middleware, auth:sanctum, throttle:agent-mutations on mutations.

**Graphs**: GET /graphs, POST /graphs, GET /graphs/{id}, PUT /graphs/{id}, DELETE /graphs/{id}, POST /graphs/{id}/restore, POST /graphs/{id}/validate, POST /graphs/{id}/start, POST /graphs/{id}/cancel, POST /graphs/{id}/clone (accepts optional `mode`: 'all' | 'failed_subtree')

**Tasks**: GET /graphs/{graphId}/tasks, GET /graphs/{graphId}/tasks/{taskId}, POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve

**Delegatee Profiles**: GET /delegatee-profiles, POST /delegatee-profiles, GET /delegatee-profiles/{id}, PUT /delegatee-profiles/{id}, DELETE /delegatee-profiles/{id}

**Events**: GET /graphs/{id}/events

### Authorization Policies (2)

1. **DelegationGraphPolicy** — ownership checks (user_id matches auth user); state guards: update only in draft; start only in ready; cancel only in active; delete only in draft or terminal; restore only soft-deleted; clone in any status
2. **DelegateeProfilePolicy** — ownership checks only (user_id matches auth user)

### New Vue Pages/Components (8)

DelegationIndex, DelegationGraphShow, DelegationGraphCreate (JSON editor + linear-chain mode with inline validation), DelegationTaskDetail, DelegationVerificationApproval, DelegateeProfileIndex, DelegateeProfileForm, DelegationGraphVisualization (custom SVG + dagre layout, native SVG transform zoom/pan, click-to-detail)

### Broadcast Events (2)

- **DelegationGraphBroadcast** — PrivateChannel `delegation.graph.{graphId}` — graph/task/attempt status changes, verification results
- **DelegationUserSummaryBroadcast** — PrivateChannel `delegation.user.{userId}` — graph started/completed/failed, escalation needed

### New Migrations (10)

delegation_capabilities, delegatee_profiles, delegatee_capabilities_pivot, delegatee_metrics, delegation_graphs, delegation_tasks, delegation_task_dependencies, delegation_attempts, delegation_verification_results, delegation_events

### Modified Files

- `routes/api.php` — add delegation route group with DelegationFeatureGate middleware
- `routes/channels.php` — add delegation channel auth (graph ownership, user match)
- `routes/web.php` — add delegation Inertia routes gated by delegation.ui_enabled
- `config/horizon.php` — add supervisor-delegation: queue ['delegation'], timeout 900, maxProcesses env('HORIZON_DELEGATION_MAX_PROCESSES', 2), auto balance; add environment overrides for production/local
- Laravel scheduler — register DelegationReconciler (every 2 min) and DelegateeMetricsRecomputer (every 15 min)
- `database/seeders/DatabaseSeeder.php` — call DelegationCapabilitySeeder
- `package.json` — add dagre dependency

## Goals

- Build a delegation layer that decomposes complex work into a DAG of verifiable tasks with explicit authority_scope, criticality-aware recovery, trust-based assignment, and adaptive re-assignment
- Implement 10 database tables, 10 Eloquent models, 14 domain services, 3 verification step types, 3 controllers, 2 authorization policies, and 1 feature gate middleware across 4 sequential phases
- Support two graph creation formats: full DAG JSON with explicit dependency edges, and linear-chain shorthand (ordered array of task objects with implicit sequential dependencies)
- Implement event-driven coordination with a hybrid architecture: DelegationCoordinator subscriber for happy-path flow, separate listeners for recovery (RecoveryHandler), metrics (MetricsListener), and broadcast (BroadcastSubscriber)
- Implement a 3-step verification pipeline (AutomatedCheckStep with preset profiles, non-blocking AiCriticStep via spawned AgentJobRun with job-chain callback, non-blocking HumanApprovalStep with API-driven resolution) with pipeline resumability for pending steps
- Implement trust-aware DelegateeAssigner with primary ranking by success_rate from sliding-window metrics (24h, 7d) and secondary tiebreak by lowest current load (fewest active attempts)
- Implement RecoveryEvaluator with combined heuristic error classification (timed_out=transient, skipped=non-transient, failed/killed=config-driven error_code lookup) and criticality-influenced decision chain (retry → re-delegate → escalate → abort)
- Implement partial-completion semantics: failed task cascades only to transitive dependents, independent branches continue, graph resolves to 'partial' terminal status via cancel-aware tri-state derivation
- Implement drain cancellation that respects outcomes: active attempts finish, succeeded tasks preserved, pending/blocked/ready tasks cancelled, graph always 'cancelled'
- Build real-time UI with Vue 3 + Inertia pages, custom SVG DAG visualization using dagre layout with native SVG transform zoom/pan/click interactions, WebSocket updates via Laravel Echo/Reverb
- Implement clone endpoint with optional 'failed_subtree' mode for targeted retry workflows on partial-completion graphs
- Deploy incrementally behind feature flags (delegation.enabled, delegation.ui_enabled) with zero impact on existing Agent test suite
- Add dedicated Horizon supervisor-delegation for the 'delegation' queue with independent scaling and 900s timeout


## Constraints

- All existing Agent tests must remain green — delegation is additive-only with no modifications to existing models, services, or migrations
- DelegateeProfile independently stores runner config: runner_type (string 16), command_template (string 2000, same {{placeholder}} syntax as AgentJob validated by CommandPolicy and rendered by CommandTemplateRenderer), working_directory (string 1024), env_json (json nullable); AttemptSpawner creates transient AgentJobs from this config
- contract_json authority_scope limited to: allowed_working_directories (intersected with PathPolicy), allowed_env_keys (intersected with EnvPolicy), max_runtime_seconds (must be ≤ 86400 global max); cannot change command or runner type
- budget_constraints and reversibility are deferred from MVP contract_json schema
- No arbitrary commands in verification contracts — AutomatedCheckStep uses only preset check profiles defined in config/delegation.php (laravel_standard, test_only, lint_only)
- Maximum 25 tasks per graph, 3 concurrent graphs per user, 5 default parallel tasks per graph (all configurable via config/delegation.php)
- Queue topology: AttemptSpawner dispatches ExecuteAgentRunJob on existing 'agent' queue; coordination/verification/metrics/reconciler jobs on dedicated 'delegation' queue with dedicated Horizon supervisor (timeout 900, maxProcesses env-driven)
- Completion detection uses Laravel Bus::chain job callbacks registered by AttemptSpawner — no modifications to RunStateTransitionService or ExecuteAgentRunJob
- Both HumanApprovalStep and AiCriticStep are non-blocking: create pending DelegationVerificationResult, return immediately; resolution fires DelegationTaskVerified to resume pipeline; VerificationPipeline must be resumable mid-sequence
- Graph validation (POST /graphs/{id}/validate) is synchronous including file-existence checks; 'validating' status reserved for future async use
- Terminal graph status derivation: user-cancel → always 'cancelled'; else all-succeeded → 'succeeded'; mix → 'partial'; zero-succeeded → 'failed'
- Drain cancellation preserves succeeded task results; only pending/blocked/ready tasks marked cancelled
- DelegationGraphBuilder auto-assigns sequence_order from topological depth (Kahn's algorithm); callers may optionally override specific tasks
- time_constraints.max_total_seconds capped at ≤ (authority_scope.max_runtime_seconds × max_retry_attempts) + verification_timeout_seconds; enforced by ContractValidator with no separate config key
- AiCriticStep review prompt stored as default PHP heredoc in class, overridable via config('delegation.ai_critic_prompt_template')
- Clone endpoint supports optional mode parameter: 'all' (default) or 'failed_subtree' (failed tasks + transitive dependents only)
- DAG visualization uses dagre + custom SVG with native SVG transform zoom/pan/click — no additional interaction or graph framework dependencies
- Audit strategy: user actions → AuditLogger + DelegationEvent; automated coordination → DelegationEvent only; one summary AuditLogger entry per graph terminal status


## Acceptance Criteria

- DelegationFeatureGate middleware returns 404 JSON when config('delegation.enabled') is false; all delegation API routes are inaccessible when disabled
- All 10 migrations run successfully creating tables with correct indexes, foreign keys (cascadeOnDelete/nullOnDelete as specified), soft deletes, and column types matching AgentJob patterns (string lengths, json columns)
- DelegateeProfile migration includes runner_type (string 16), command_template (string 2000), working_directory (string 1024), env_json (json nullable), config_json (json nullable) with index on (user_id, runner_type, is_active, deleted_at)
- All 10 Eloquent models have correct $guarded=[], casts() methods, relationship methods, status constants with ACTIVE_STATUSES/TERMINAL_STATUSES arrays including 'partial' and 'cancelled' in DelegationGraph and 'cancelled' in DelegationTask
- DelegationGraphBuilder accepts raw DAG JSON and creates graph+tasks+dependencies in a single DB transaction
- DelegationGraphBuilder accepts linear-chain shorthand (ordered task array) and creates implicit sequential dependencies where each task depends on the previous
- DelegationGraphBuilder rejects graphs with cycles (Kahn's algorithm), >25 tasks, unresolved dependency references, and invalid contracts
- DelegationGraphBuilder auto-assigns sequence_order from topological depth and respects optional caller-provided overrides on individual tasks
- ContractValidator validates: required_capability references active capability; authority_scope.max_runtime_seconds ≤ 86400; criticality is valid enum (low/medium/high/critical); time_constraints.max_total_seconds ≤ (max_runtime_seconds × max_retry_attempts) + verification_timeout_seconds; verification_strategy has ≥1 step with check profiles existing in config; prompt or task_markdown_path present
- ContractEnforcer correctly narrows DelegateeProfile config by intersecting authority_scope with PathPolicy allowed bases and EnvPolicy forbidden keys, capping max_runtime_seconds; rejects impossible constraints with validation error
- CommandPolicy validates DelegateeProfile.command_template using same {{placeholder}} syntax and rules as AgentJob command_template
- POST /graphs/{id}/validate runs synchronous validation (structural + file-existence) and transitions draft→ready or returns errors staying in draft
- POST /graphs/{id}/start checks concurrent graph limit (max 3), transitions ready→running, fires DelegationGraphStarted
- POST /graphs/{id}/cancel applies cancellation_policy: 'kill' stops all active attempts immediately via SIGTERM, transitions non-terminal tasks to failed/cancelled, graph to cancelled; 'drain' lets active attempts finish, marks only pending/blocked/ready as cancelled, preserves succeeded task results
- POST /graphs/{id}/clone with mode='all' creates new draft graph with all task definitions and dependencies copied, statuses/assignments/attempts cleared
- POST /graphs/{id}/clone with mode='failed_subtree' creates new draft graph containing only failed tasks plus their transitive dependents with internal dependencies preserved
- DelegationCoordinator correctly orchestrates happy-path: graph start → root tasks ready → assign → spawn → attempt finish (success) → verify → task complete → check graph completion → next ready tasks dispatched
- Eager-ready/lazy-dispatch: all dependency-satisfied tasks transition to 'ready'; AttemptSpawner dispatches only up to max_parallel_tasks; Coordinator dispatches additional ready tasks (lowest sequence_order first) on each TaskCompleted event
- AttemptSpawner creates transient AgentJob from DelegateeProfile config (command_template passed directly), dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with completion callback job on 'delegation' queue, links DelegationAttempt to AgentJobRun via FK
- DelegateeAssigner matches required_capability against active profiles, ranks by success_rate from 24h sliding window, breaks ties by lowest active attempt count across all graphs
- RecoveryHandler classifies timed_out as transient, skipped as non-transient, failed/killed by error_code lookup against config transient_error_codes/non_transient_error_codes lists (with wildcard support)
- RecoveryHandler decision chain respects criticality: critical/high tasks escalate at escalation_criticality_threshold; retry → re-delegate → escalate (create pending human_approval) → abort (fail transitive dependents only)
- Partial completion: when a task aborts, only its transitive dependents are failed; independent branches continue; graph resolves to 'partial' if mix of succeeded/failed, 'failed' if zero succeeded, 'succeeded' if all succeeded
- time_constraints.max_total_seconds enforced as aggregate ceiling: RecoveryEvaluator checks cumulative elapsed time (sum of attempt durations + verification time) before retrying; exceeding ceiling triggers abort
- AutomatedCheckStep resolves check profile from config('delegation.check_profiles'), executes commands sequentially, captures stdout/stderr as evidence_json, non-zero exit = failure verdict
- AiCriticStep creates transient AgentJob with review prompt (class heredoc default, config override), dispatches via ExecuteAgentRunJob with Bus::chain callback, returns 'pending' immediately; callback parses structured pass/fail verdict, writes DelegationVerificationResult, fires DelegationTaskVerified
- HumanApprovalStep creates pending DelegationVerificationResult with verdict='pending' and returns immediately; task remains in 'verifying' status; no queue worker held open
- POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve accepts approve/reject with notes, updates verdict, fires DelegationTaskVerified event to resume VerificationPipeline from next step
- VerificationPipeline tracks current step position and resumes from next unresolved step when DelegationTaskVerified fires; short-circuits on first failure verdict
- DelegateeMetricsRecomputer triggers on DelegationAttemptFinished with 60s cache-lock throttle; scheduled fallback every 15 minutes; computes total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms per window
- DelegationReconciler runs every 2 minutes: detects stuck tasks (active status with terminal AgentJobRun), missed graph completions (all tasks terminal but graph not), expired human approval timeouts (pending results > verification_timeout_seconds); fires missed events
- Horizon config includes supervisor-delegation: connection redis, queue ['delegation'], balance auto, autoScalingStrategy time, maxProcesses env('HORIZON_DELEGATION_MAX_PROCESSES', 2), timeout 900, tries 1; with production/local environment overrides
- Broadcast events sent on correct private channels (delegation.graph.{graphId}, delegation.user.{userId}) with enriched payloads including task names, delegatee names, progress percentages
- Channel authorization in routes/channels.php verifies user owns graph for graph channels and authenticated user matches for user channels
- Vue DelegationGraphVisualization renders DAG using dagre layout with SVG nodes (status-colored fills) and edges (dependency arrows); zoom via wheel→viewBox scaling, pan via mousedown+drag→viewBox translate, node click emits to parent for detail navigation
- All delegation Inertia web routes return 404 when config('delegation.ui_enabled') is false
- User-initiated actions produce both AuditLogger and DelegationEvent records; automated coordination produces DelegationEvent only; one summary AuditLogger recordSystemAction entry when graph reaches terminal status with final status, task counts by status, duration
- DelegationGraphPolicy enforces: update only in draft; start only in ready; cancel only in active (running); delete only in draft or terminal; restore only soft-deleted; clone available on any graph the user owns
- config/delegation.php includes ai_critic_prompt_template key (null default = use class heredoc), check_profiles with three presets, transient_error_codes, non_transient_error_codes with wildcard pattern support
- DelegationCapabilitySeeder seeds 7 capabilities from config: code_generation, code_review, testing, documentation, refactoring, analysis, planning

