# Implementation Plan

Derived from discovery session 99.

# Implementation Plan

Derived from discovery session 99.

# Delegation Engineering — Implementation Plan

## Overview

Build a first-class delegation layer on Agent's existing job orchestration platform. Complex work is decomposed into a directed acyclic graph (DAG) of verifiable delegated tasks with explicit authority, accountability, trust-aware assignment, and adaptive re-assignment. Deployed across 4 sequential phases behind feature flags (`delegation.enabled`, `delegation.ui_enabled`). All additive-only — zero modifications to existing models, services, or migrations.

---

## Phase 1 — Backend Foundation (Data Layer + Core Services)

**Goal**: Establish all database tables, Eloquent models, config updates, seeder, core domain services, API endpoints for graph CRUD, and policy authorization. No execution or coordination yet.

### 1.1 Config Update: `config/delegation.php`

The file already exists with a subset of keys. Update it to match the confirmed config schema:

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
    'transient_error_codes' => ['TIMEOUT', 'RATE_LIMIT', 'CONNECTION_*'],
    'non_transient_error_codes' => ['INVALID_OUTPUT', 'PERMISSION_DENIED'],
    'ai_critic_prompt_template' => null,
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

**Changes from existing file**: Add keys `transient_error_codes`, `non_transient_error_codes`, `ai_critic_prompt_template`, `check_profiles`. Update `default_max_parallel_tasks` default from 3 to 5. Update `capabilities_seed` to match confirmed list (replace `linting`/`file_editing` with `documentation`/`analysis`/`planning`). Remove `max_recovery_attempts` key (consolidated into `max_retry_attempts`).

### 1.2 Feature Gate Middleware

`app/Http/Middleware/DelegationFeatureGate.php` — **already exists**. Returns 404 JSON via `ErrorEnvelope::make('FEATURE_DISABLED', 'Delegation is not enabled.', 404)` when `config('delegation.enabled')` is false. No changes needed.

### 1.3 Database Migrations (10 migrations)

All follow existing patterns from `2026_02_12_020511_create_agent_jobs_table.php`: named indexes, explicit column lengths, `foreignId()->constrained()->cascadeOnDelete()`, `softDeletes()` where applicable. Sequential timestamps starting from `2026_02_17_200000`.

**Migration 1** — `2026_02_17_200000_create_delegation_capabilities_table`
- `id`, `slug` (string 64, unique), `name` (string 120), `description` (text nullable), `is_active` (boolean default true), `timestamps`

**Migration 2** — `2026_02_17_200001_create_delegatee_profiles_table`
- `id`, `user_id` (FK constrained cascadeOnDelete), `name` (string 120), `runner_type` (string 16), `command_template` (string 2000), `working_directory` (string 1024), `env_json` (json nullable), `config_json` (json nullable), `is_active` (boolean default true), `softDeletes`, `timestamps`
- Index: `(user_id, runner_type, is_active, deleted_at)` named `delegatee_profiles_user_runner_active_deleted_idx`

**Migration 3** — `2026_02_17_200002_create_delegatee_capabilities_pivot_table`
- `id`, `delegatee_profile_id` (FK constrained cascadeOnDelete), `delegation_capability_id` (FK constrained cascadeOnDelete), `timestamps`
- Unique: `(delegatee_profile_id, delegation_capability_id)` named `delegatee_cap_pivot_profile_capability_unique`

**Migration 4** — `2026_02_17_200003_create_delegatee_metrics_table`
- `id`, `delegatee_profile_id` (FK constrained cascadeOnDelete, unique), `window_24h_json` (json nullable), `window_7d_json` (json nullable), `last_recomputed_at` (timestampTz nullable), `timestamps`
- Each window JSON schema: `{total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms}`

**Migration 5** — `2026_02_17_200004_create_delegation_graphs_table`
- `id`, `user_id` (FK constrained cascadeOnDelete), `name` (string 255), `description` (text nullable), `status` (string 24, default 'draft'), `cancellation_policy` (string 16, default from config), `max_parallel_tasks` (unsignedTinyInteger, default from config), `metadata_json` (json nullable), `error_code` (string 100 nullable), `error_summary` (text nullable), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `softDeletes`, `timestamps`
- Index: `(user_id, status, deleted_at)` named `delegation_graphs_user_status_deleted_idx`

**Migration 6** — `2026_02_17_200005_create_delegation_tasks_table`
- `id`, `delegation_graph_id` (FK constrained cascadeOnDelete), `name` (string 255), `status` (string 24, default 'pending'), `sequence_order` (unsignedSmallInteger), `contract_json` (json — required_capability, authority_scope, criticality, time_constraints, verification_strategy, prompt/task_markdown_path), `assigned_delegatee_profile_id` (FK nullable constrained nullOnDelete), `assignment_reason_json` (json nullable), `metadata_json` (json nullable), `error_code` (string 100 nullable), `error_summary` (text nullable), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `timestamps`
- Index: `(delegation_graph_id, status)` named `delegation_tasks_graph_status_idx`
- Index: `(assigned_delegatee_profile_id)` named `delegation_tasks_assigned_profile_idx`

**Migration 7** — `2026_02_17_200006_create_delegation_task_dependencies_table`
- `id`, `task_id` (FK constrained cascadeOnDelete — the dependent task), `depends_on_task_id` (FK constrained cascadeOnDelete — the prerequisite), `timestamps`
- Unique: `(task_id, depends_on_task_id)` named `delegation_task_deps_task_depends_unique`

**Migration 8** — `2026_02_17_200007_create_delegation_attempts_table`
- `id`, `delegation_task_id` (FK constrained cascadeOnDelete), `delegatee_profile_id` (FK constrained), `agent_job_run_id` (FK nullable constrained('agent_job_runs') nullOnDelete), `attempt_number` (unsignedTinyInteger), `status` (string 24, default 'running'), `started_at` (timestampTz), `finished_at` (timestampTz nullable), `duration_ms` (unsignedInteger default 0), `error_code` (string 100 nullable), `error_summary` (text nullable), `metadata_json` (json nullable), `timestamps`
- Index: `(delegation_task_id, attempt_number)` named `delegation_attempts_task_attempt_idx`

**Migration 9** — `2026_02_17_200008_create_delegation_verification_results_table`
- `id`, `delegation_task_id` (FK constrained cascadeOnDelete), `delegation_attempt_id` (FK nullable constrained cascadeOnDelete), `step_type` (string 32 — 'automated_check', 'ai_critic', 'human_approval'), `step_order` (unsignedTinyInteger), `verdict` (string 16 — 'passed', 'failed', 'skipped', 'pending'), `evidence_json` (json nullable), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `timestamps`
- Index: `(delegation_task_id, step_order)` named `delegation_verification_task_step_idx`

**Migration 10** — `2026_02_17_200009_create_delegation_events_table`
- `id`, `delegation_graph_id` (FK constrained cascadeOnDelete), `delegation_task_id` (FK nullable constrained cascadeOnDelete), `event_type` (string 64), `sequence` (unsignedInteger), `payload_json` (json nullable), `event_ts` (timestampTz), `timestamps`
- Index: `(delegation_graph_id, sequence)` named `delegation_events_graph_sequence_idx`
- Index: `(delegation_task_id, event_ts)` named `delegation_events_task_ts_idx`

### 1.4 Eloquent Models (10 models)

All in `app/Models/` namespace. Follow existing patterns: `$guarded = []`, `casts()` method, relationship methods, status constants with `ACTIVE_STATUSES`/`TERMINAL_STATUSES` arrays, scopes.

**`DelegationCapability`** — slug, name, description, is_active; `scopeActive(Builder $query)` filters by `is_active = true`.

**`DelegateeProfile`** — uses `SoftDeletes`; relationships: `belongsTo(User)`, `belongsToMany(DelegationCapability)` via `delegatee_capabilities_pivot` with pivot timestamps, `hasOne(DelegateeMetric)`, `hasMany(DelegationTask, 'assigned_delegatee_profile_id')`, `hasMany(DelegationAttempt)`; casts: `env_json => array`, `config_json => array`, `is_active => boolean`; `scopeActive` filters by `is_active = true, deleted_at IS NULL`.

**`DelegateeCapabilityPivot`** — extends `Illuminate\Database\Eloquent\Relations\Pivot` with `$incrementing = true`; `belongsTo(DelegateeProfile)`, `belongsTo(DelegationCapability)`.

**`DelegateeMetric`** — `belongsTo(DelegateeProfile)`; casts: `window_24h_json => array`, `window_7d_json => array`, `last_recomputed_at => datetime`.

**`DelegationGraph`** — uses `SoftDeletes`; status constants: `STATUS_DRAFT = 'draft'`, `STATUS_VALIDATING = 'validating'`, `STATUS_READY = 'ready'`, `STATUS_RUNNING = 'running'`, `STATUS_SUCCEEDED = 'succeeded'`, `STATUS_FAILED = 'failed'`, `STATUS_PARTIAL = 'partial'`, `STATUS_CANCELLED = 'cancelled'`; `ACTIVE_STATUSES = ['running']`; `TERMINAL_STATUSES = ['succeeded', 'failed', 'partial', 'cancelled']`; relationships: `belongsTo(User)`, `hasMany(DelegationTask)`, `hasMany(DelegationEvent)`; casts: `metadata_json => array`, `started_at => datetime`, `finished_at => datetime`, `max_parallel_tasks => integer`.

**`DelegationTask`** — status constants: `STATUS_PENDING = 'pending'`, `STATUS_BLOCKED = 'blocked'`, `STATUS_READY = 'ready'`, `STATUS_ASSIGNED = 'assigned'`, `STATUS_RUNNING = 'running'`, `STATUS_VERIFYING = 'verifying'`, `STATUS_SUCCEEDED = 'succeeded'`, `STATUS_FAILED = 'failed'`, `STATUS_CANCELLED = 'cancelled'`; `ACTIVE_STATUSES = ['pending', 'blocked', 'ready', 'assigned', 'running', 'verifying']`; `TERMINAL_STATUSES = ['succeeded', 'failed', 'cancelled']`; relationships: `belongsTo(DelegationGraph)`, `hasMany(DelegationAttempt)`, `hasMany(DelegationVerificationResult)`, `belongsTo(DelegateeProfile, 'assigned_delegatee_profile_id')`, dependencies via `hasManyThrough` or explicit: `hasMany(DelegationTaskDependency, 'task_id')` as `dependencies`, `hasMany(DelegationTaskDependency, 'depends_on_task_id')` as `dependents`; casts: `contract_json => array`, `assignment_reason_json => array`, `metadata_json => array`, `started_at => datetime`, `finished_at => datetime`, `sequence_order => integer`.

**`DelegationTaskDependency`** — `belongsTo(DelegationTask, 'task_id')` as `task`, `belongsTo(DelegationTask, 'depends_on_task_id')` as `dependsOnTask`.

**`DelegationAttempt`** — status constants: `STATUS_RUNNING = 'running'`, `STATUS_SUCCEEDED = 'succeeded'`, `STATUS_FAILED = 'failed'`; `ACTIVE_STATUSES = ['running']`; `TERMINAL_STATUSES = ['succeeded', 'failed']`; relationships: `belongsTo(DelegationTask)`, `belongsTo(DelegateeProfile)`, `belongsTo(AgentJobRun, 'agent_job_run_id')`; casts: `metadata_json => array`, `started_at => datetime`, `finished_at => datetime`, `duration_ms => integer`, `attempt_number => integer`.

**`DelegationVerificationResult`** — relationships: `belongsTo(DelegationTask)`, `belongsTo(DelegationAttempt, 'delegation_attempt_id')`; casts: `evidence_json => array`, `started_at => datetime`, `finished_at => datetime`.

**`DelegationEvent`** — relationships: `belongsTo(DelegationGraph)`, `belongsTo(DelegationTask, 'delegation_task_id')` (nullable); casts: `payload_json => array`, `event_ts => datetime`.

### 1.5 Core Domain Services

All in `app/Support/Delegation/` namespace.

**`GraphStateTransitionService`** — Exact same atomic pattern as `RunStateTransitionService`: accepts `int $graphId`, `array $fromStatuses`, `string $toStatus`, `array $attributes = []`; executes `DelegationGraph::query()->whereKey($graphId)->whereIn('status', $fromStatuses)->update($payload)` with `updated_at` set to `CarbonImmutable::now('UTC')`; returns bool (`$updated === 1`).

**`TaskStateTransitionService`** — Same pattern for `DelegationTask`.

**`ContractValidator`** — Validates contract_json structure. Required checks:
- `required_capability` must reference an active `DelegationCapability` slug
- `authority_scope.max_runtime_seconds` must be ≤ 86400 (global max from AgentJob validation)
- `authority_scope.allowed_working_directories` must be array of strings
- `authority_scope.allowed_env_keys` must be array of strings
- `criticality` must be one of `low`, `medium`, `high`, `critical`
- `time_constraints.max_total_seconds` must be ≤ `(authority_scope.max_runtime_seconds × config('delegation.max_retry_attempts')) + config('delegation.verification_timeout_seconds')`
- `verification_strategy` must have ≥1 step; check profile names referenced in automated_check steps must exist in `config('delegation.check_profiles')`
- Either `prompt` or `task_markdown_path` must be present (inline prompt takes precedence if both provided)
- Returns `['valid' => bool, 'errors' => [...]]`

**`DelegationGraphBuilder`** — Accepts raw DAG JSON (with explicit dependency edges) or linear-chain shorthand (ordered task array where each task implicitly depends on the previous). Creates `DelegationGraph` + `DelegationTask` records + `DelegationTaskDependency` records in a single DB transaction. Validates: no cycles (Kahn's algorithm / topological sort), max 25 tasks per `config('delegation.max_tasks_per_graph')`, all dependency references resolve within the graph, all contracts valid via `ContractValidator`. Auto-assigns `sequence_order` from topological depth (Kahn's algorithm BFS level); respects optional caller-provided `sequence_order` overrides on individual tasks. Returns created `DelegationGraph` or validation errors.

**`ContractEnforcer`** — Takes a `DelegationTask`'s `contract_json` `authority_scope` and a `DelegateeProfile`'s config. Narrows by: intersecting `allowed_working_directories` with `PathPolicy` resolved allowed bases (`config('agent.allowed_working_directory_bases')`), intersecting `allowed_env_keys` against `EnvPolicy` forbidden keys (`config('agent.forbidden_env_keys')` + pattern), capping `max_runtime_seconds` to ≤ global 86400 max. Returns narrowed config array ready for transient AgentJob creation, or returns validation error if contract is impossible to satisfy (e.g., working directory outside all allowed bases).

**`DelegateeAssigner`** — Given a `DelegationTask`, finds eligible `DelegateeProfile` records by matching `required_capability` against active profiles' capabilities (via pivot). Primary rank: `success_rate` from `DelegateeMetric.window_24h_json` (falls back to `window_7d_json` if 24h data insufficient). Secondary tiebreaker: lowest current load — fewest active (running) `DelegationAttempt` records across all graphs for that profile. Returns ranked list with `assignment_reason_json` explaining selection logic.

**`DelegationEventWriter`** — Follows `RunEventWriter` pattern (auto-increments sequence per graph). Constructor queries `DelegationEvent` max sequence for graph. `append(string $eventType, ?int $taskId, array $payload)` creates `DelegationEvent` record with auto-incremented sequence and `event_ts = CarbonImmutable::now('UTC')`.

### 1.6 API Endpoints and Controllers

All under `agent/api/v1/delegation/` prefix. Added to `routes/api.php` as a new route group with `DelegationFeatureGate` middleware applied to the group, `auth:sanctum` inside, `throttle:agent-mutations` on mutations. Follows the exact same routing pattern as existing agent job/run and interrogation routes.

**New Controllers** (in `app/Http/Controllers/Api/V1/`):

**`DelegationGraphController`**:
- `index(Request)` — `GET /graphs` — list user's graphs, paginated, filterable by status, softDeletes-aware (`?deleted=1|all`), same pagination envelope as `AgentJobController::index`
- `store(Request)` — `POST /graphs` — create graph via `DelegationGraphBuilder` (accepts JSON body with `tasks` array in DAG or linear-chain format); returns 201
- `show(Request, int $id)` — `GET /graphs/{id}` — show graph with tasks loaded (including dependencies)
- `update(Request, int $id)` — `PUT /graphs/{id}` — update draft graph name/description/cancellation_policy/max_parallel_tasks; policy enforces draft-only
- `destroy(Request, int $id)` — `DELETE /graphs/{id}` — soft delete graph; policy enforces draft or terminal only
- `restore(Request, int $id)` — `POST /graphs/{id}/restore` — restore soft-deleted graph
- `validate(Request, int $id)` — `POST /graphs/{id}/validate` — synchronous validation (structural via Kahn's cycle detection, contract schema validation via ContractValidator, capability existence, dependency resolution, time_constraints cap enforcement, file-existence checks for `task_markdown_path`); transitions draft→ready on success, stays draft and returns errors on failure
- `start(Request, int $id)` — `POST /graphs/{id}/start` — checks concurrent graph limit, transitions ready→running; Phase 2 wires up DelegationGraphStarted event
- `cancel(Request, int $id)` — `POST /graphs/{id}/cancel` — Phase 2 wires up kill/drain logic
- `clone(Request, int $id)` — `POST /graphs/{id}/clone` — accepts optional `mode` parameter: `'all'` (default, full clone copying all task definitions and dependencies) or `'failed_subtree'` (clone only failed tasks plus their transitive dependents, preserving internal dependencies); all statuses reset to pending, assignment/attempt data cleared, graph status set to draft; available only on graphs in terminal statuses (`succeeded`, `failed`, `partial`, `cancelled`)
- `events(Request, int $id)` — `GET /graphs/{id}/events` — list delegation events for graph, paginated by sequence

**`DelegationTaskController`**:
- `index(Request, int $graphId)` — `GET /graphs/{graphId}/tasks` — list tasks for graph
- `show(Request, int $graphId, int $taskId)` — `GET /graphs/{graphId}/tasks/{taskId}` — show task with attempts and verification results loaded
- `resolveVerification(Request, int $graphId, int $taskId, int $resultId)` — `POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve` — Phase 2 wires up; accepts approve/reject with notes

**`DelegateeProfileController`**:
- `index(Request)` — `GET /delegatee-profiles` — list user's profiles, paginated
- `store(Request)` — `POST /delegatee-profiles` — create profile; validates runner_type, command_template via `CommandPolicy::validateForSave`, working_directory via `PathPolicy::validateWorkingDirectory`, env_json via `EnvPolicy::validate`; capability assignment via capability slug array
- `show(Request, int $id)` — `GET /delegatee-profiles/{id}` — show profile with capabilities and metrics loaded
- `update(Request, int $id)` — `PUT /delegatee-profiles/{id}` — update profile
- `destroy(Request, int $id)` — `DELETE /delegatee-profiles/{id}` — soft delete

### 1.7 Authorization Policies

**`DelegationGraphPolicy`** (in `app/Policies/`):
- `viewAny(User)` — true (authenticated)
- `view(User, DelegationGraph)` — ownership: `$graph->user_id === $user->id`
- `create(User)` — true (authenticated)
- `update(User, DelegationGraph)` — ownership + status must be `draft`
- `start(User, DelegationGraph)` — ownership + status must be `ready`
- `cancel(User, DelegationGraph)` — ownership + status must be in `ACTIVE_STATUSES`
- `delete(User, DelegationGraph)` — ownership + status must be `draft` or in `TERMINAL_STATUSES`
- `restore(User, DelegationGraph)` — ownership + must be soft-deleted
- `clone(User, DelegationGraph)` — ownership + status must be in `TERMINAL_STATUSES` (`succeeded`, `failed`, `partial`, `cancelled`)

**`DelegateeProfilePolicy`** (in `app/Policies/`):
- `viewAny(User)` — true
- `view(User, DelegateeProfile)` — ownership
- `create(User)` — true
- `update(User, DelegateeProfile)` — ownership
- `delete(User, DelegateeProfile)` — ownership
- `restore(User, DelegateeProfile)` — ownership

### 1.8 Database Seeder

**`DelegationCapabilitySeeder`** (in `database/seeders/`): Seeds initial capabilities from `config('delegation.capabilities_seed')`: code_generation, code_review, testing, documentation, refactoring, analysis, planning. Uses `updateOrCreate` on slug to be idempotent.

**`DatabaseSeeder`** modification: Call `$this->call(DelegationCapabilitySeeder::class)` at end of `run()`.

### 1.9 Tests (Phase 1)

All follow existing test patterns (RefreshDatabase, `actingAs` with Sanctum, `assertStatus`, `assertJsonStructure`).

- `tests/Feature/DelegationGraphBuilderTest.php` — graph creation with DAG JSON, linear-chain shorthand creating implicit sequential dependencies, cycle detection rejection, max 25 task rejection, unresolved dependency rejection, invalid contract rejection, sequence_order auto-assignment from topological depth, caller override of sequence_order
- `tests/Feature/ContractValidatorTest.php` — valid contracts pass, missing required_capability fails, invalid criticality enum fails, max_runtime_seconds > 86400 fails, missing verification_strategy fails, invalid check profile name fails, time_constraints cap enforcement, prompt/task_markdown_path presence
- `tests/Feature/ContractEnforcerTest.php` — narrowing working directories with PathPolicy intersection, env key filtering against EnvPolicy forbidden keys, max_runtime_seconds capping, impossible contract (working directory outside all allowed bases) returns error
- `tests/Feature/DelegateeAssignerTest.php` — capability matching, trust ranking by success_rate, tiebreak by lowest active attempt count, no eligible delegatee returns empty
- `tests/Feature/GraphStateTransitionTest.php` — atomic transitions succeed, concurrent conflict returns false, invalid transition (wrong from status) returns false
- `tests/Feature/TaskStateTransitionTest.php` — all valid transitions, invalid transitions rejected
- `tests/Feature/DelegationApiWorkflowTest.php` — full CRUD lifecycle via API endpoints, graph create (both formats), show, update (draft only), validate (success and failure), start (ready only, concurrent limit), clone (both modes, terminal status only, rejected on non-terminal), delete/restore, events listing, authorization (other user rejected), feature gate (404 when disabled)
- `tests/Feature/DelegateeProfileApiTest.php` — CRUD, command_template validation via CommandPolicy, working_directory validation via PathPolicy, env_json validation via EnvPolicy, capability assignment, soft delete/restore, ownership enforcement

---

## Phase 2 — Execution Engine (Coordination + Recovery + Verification)

**Goal**: Wire up event-driven execution, recovery evaluation, verification pipeline, metrics recomputation, and reconciliation. Graphs can actually run end-to-end.

### 2.1 Domain Events (Internal Laravel Events)

All in `app/Events/Delegation/` namespace. Plain PHP event classes (not broadcast; broadcast is Phase 3). Each carries the relevant model ID(s).

- `DelegationGraphStarted` — `int $graphId`
- `DelegationGraphCompleted` — `int $graphId`, `string $terminalStatus`
- `DelegationTaskReady` — `int $taskId`
- `DelegationTaskAssigned` — `int $taskId`, `int $delegateeProfileId`
- `DelegationTaskStarted` — `int $taskId`, `int $attemptId`
- `DelegationTaskCompleted` — `int $taskId`, `string $terminalStatus`
- `DelegationTaskVerified` — `int $taskId`, `int $verificationResultId`
- `DelegationAttemptFinished` — `int $attemptId`, `string $terminalStatus`
- `DelegationRecoveryTriggered` — `int $taskId`, `int $attemptId`

### 2.2 Coordination Services

**`app/Support/Delegation/DelegationCoordinator.php`** — implements `Illuminate\Contracts\Events\Dispatcher` subscriber pattern (`EventSubscriberInterface` / `$subscribe` method). Handles happy-path flow:
- On `DelegationGraphStarted`: identify root tasks (no dependencies via `DelegationTaskDependency`), transition each to `ready` via `TaskStateTransitionService`, fire `DelegationTaskReady` for each
- On `DelegationTaskReady`: invoke `DelegateeAssigner`, assign top-ranked profile, transition to `assigned`, set `assignment_reason_json`, invoke `AttemptSpawner` if under `max_parallel_tasks` limit (eager-ready/lazy-dispatch: only dispatches up to limit; remaining `ready` tasks wait)
- On `DelegationAttemptFinished` (success path): transition task to `verifying`, invoke `VerificationPipeline`
- On `DelegationTaskVerified`: if pipeline returned a pending step, do nothing (wait for resolution). If pipeline complete: if all steps passed, transition task to `succeeded`, fire `DelegationTaskCompleted`. If any step failed, invoke `RecoveryHandler`. After task completion: check if newly-ready tasks exist (dependencies all satisfied), fire `DelegationTaskReady` for each (prioritized by lowest `sequence_order`). Check if all graph tasks are terminal — if yes, derive terminal status (cancel-aware tri-state) and complete graph via `GraphStateTransitionService`, fire `DelegationGraphCompleted`.
- On `DelegationTaskCompleted`: check for additional `ready` tasks to dispatch (under parallel limit), fire `DelegationTaskReady` for each

**`app/Support/Delegation/AttemptSpawner.php`** — Creates `DelegationAttempt` record with `attempt_number` (previous max + 1), `status = 'running'`, `started_at`. Uses `ContractEnforcer` to build narrowed runtime config. Creates a transient `AgentJob` from `DelegateeProfile` config (`runner_type`, `command_template` passed directly, `working_directory`, `env_json`), sets `is_enabled = false`, `cron_expression = '0 0 1 1 0'` (project-standard sentinel cron for non-scheduled transient jobs, never triggered by scheduler), names it with graph/task context. Creates `AgentJobRun` with `status = 'queued'`, `trigger_type = 'manual'`, links to transient job. Dispatches `ExecuteAgentRunJob` on `'agent'` queue via `Bus::chain` with a completion callback job dispatched on `'delegation'` queue. The callback (a dedicated `DelegationAttemptCompletionJob` queue job) fires `DelegationAttemptFinished` event. Links attempt to AgentJobRun via FK.

**`app/Jobs/DelegationAttemptCompletionJob.php`** — queued on `'delegation'` queue. Receives `int $attemptId`. Loads attempt + linked AgentJobRun. If AgentJobRun is terminal: determines attempt terminal status from run status (succeeded → succeeded, everything else → failed), transitions attempt via direct update, sets `finished_at`, `duration_ms`, copies `error_code`/`error_summary` from run. Fires `DelegationAttemptFinished`.

**`app/Support/Delegation/RecoveryHandler.php`** — Separate event listener for `DelegationAttemptFinished` (failure path) and `DelegationRecoveryTriggered`. Combined heuristic error classification:
- `timed_out` AgentJobRun status → always transient (retry same delegatee)
- `skipped` → always non-transient (re-delegate)
- `failed` and `killed` → check attempt's `error_code` against configurable lists in `config('delegation.transient_error_codes')` and `config('delegation.non_transient_error_codes')` with wildcard pattern support (e.g., `CONNECTION_*` matches `CONNECTION_RESET`, `CONNECTION_TIMEOUT`)

Decision chain (criticality-influenced):
1. If transient + attempts < `config('delegation.max_retry_attempts')` + cumulative elapsed time < `time_constraints.max_total_seconds` → retry with same delegatee
2. If non-transient or retry attempts exhausted → re-delegate to next-ranked eligible delegatee via `DelegateeAssigner`
3. If no eligible delegatees remain + task criticality ≥ `config('delegation.escalation_criticality_threshold')` (default 'high') → escalate to human approval (create pending `human_approval` DelegationVerificationResult)
4. If escalation not possible or already escalated or low criticality → abort task. Propagate failure to transitive dependents only (transition them to `failed`/`cancelled`). Independent branches continue.

Each decision recorded as `DelegationEvent` with full context (decision type, reason, attempt count, delegatee info).

**Partial completion**: When a task aborts, only its transitive dependents (found by traversing `DelegationTaskDependency` graph forward) are failed. Independent branches continue running. Graph terminal status determined by cancel-aware tri-state: if user-cancelled → `cancelled`; else if all tasks succeeded → `succeeded`; mix of succeeded and failed/cancelled → `partial`; zero succeeded → `failed`.

### 2.3 Verification Pipeline

**`app/Support/Delegation/VerificationPipeline.php`** — Receives task + attempt. Reads `verification_strategy` from `contract_json`. Executes steps in order. Each step is a class implementing `VerificationStepInterface` (`execute(DelegationTask $task, DelegationAttempt $attempt): DelegationVerificationResult`). Pipeline is **resumable**: tracks current step position. When invoked, finds first unresolved step (no DelegationVerificationResult with terminal verdict for this task+step_order). Executes it. If step returns `pending` verdict (non-blocking steps), pipeline returns and waits. When `DelegationTaskVerified` fires after a pending step resolves, Coordinator calls pipeline again, which resumes from next unresolved step. Pipeline short-circuits on first `failed` verdict.

**`app/Support/Delegation/Contracts/VerificationStepInterface.php`**:
```php
interface VerificationStepInterface {
    public function execute(DelegationTask $task, DelegationAttempt $attempt): DelegationVerificationResult;
}
```

**`app/Support/Delegation/Verification/AutomatedCheckStep.php`** — Resolves check profile name from verification_strategy step config. Looks up command array from `config('delegation.check_profiles')`. No arbitrary commands allowed. Executes commands sequentially in task's working directory using `Symfony\Component\Process\Process`. Non-zero exit code = failure. Captures stdout/stderr as `evidence_json`. Creates `DelegationVerificationResult` with `verdict = 'passed'` or `'failed'`. Blocking (synchronous).

**`app/Support/Delegation/Verification/AiCriticStep.php`** — Creates transient AgentJob with review prompt template (default PHP heredoc in class, overridable via `config('delegation.ai_critic_prompt_template')`). Transient job uses `is_enabled = false`, `cron_expression = '0 0 1 1 0'` (same sentinel cron as AttemptSpawner). Task output (from attempt's AgentJobRun stdout events) injected as context. Dispatches via `ExecuteAgentRunJob` with `Bus::chain` callback on `'delegation'` queue. Returns `pending` `DelegationVerificationResult` immediately (**non-blocking**). The chained callback job (`AiCriticCompletionJob`) parses structured pass/fail verdict from review run output, updates the DelegationVerificationResult verdict/evidence_json, fires `DelegationTaskVerified` to resume pipeline.

**`app/Jobs/AiCriticCompletionJob.php`** — queued on `'delegation'`. Receives verification result ID. Loads result + linked review run. Parses structured verdict. Updates result. Fires `DelegationTaskVerified`.

**`app/Support/Delegation/Verification/HumanApprovalStep.php`** — Creates pending `DelegationVerificationResult` with `verdict = 'pending'`, `step_type = 'human_approval'`. Returns immediately (**non-blocking**). Task stays in `verifying` status. Resolution via `POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve` accepts approve/reject with notes, updates verdict and evidence_json, fires `DelegationTaskVerified` event to resume Coordinator/Pipeline flow.

### 2.4 Graph Start and Cancel Logic

**Graph Start** (wired into `DelegationGraphController::start`):
- Validates graph is in `ready` status via policy
- Checks user's concurrent running graph count < `config('delegation.max_concurrent_graphs_per_user')`
- Transitions graph to `running` via `GraphStateTransitionService`
- Sets `started_at = CarbonImmutable::now('UTC')`
- Fires `DelegationGraphStarted` event
- Records `DelegationEvent` (type: `graph.started`)
- Records `AuditLogger::recordUserAction` (action: `delegation_graph.start`)

**Graph Cancel** (wired into `DelegationGraphController::cancel`):
- Validates graph is in active status via policy
- Reads `cancellation_policy` from graph (`'kill'` or `'drain'`)
- **Kill mode**: find all active DelegationAttempts with linked AgentJobRuns; transition each linked run to `stopping` via `RunStateTransitionService` (triggers existing SIGTERM logic in ExecuteAgentRunJob); transition all non-terminal tasks to `cancelled`; transition graph to `cancelled`
- **Drain mode**: stop dispatching new tasks (set a `cancelling` flag in graph metadata_json); let active attempts finish naturally (AttemptCompletionListener still fires, but Coordinator checks for cancelling flag and skips spawning new tasks); succeeded tasks retain `succeeded` status; only `pending`/`blocked`/`ready` tasks are marked `cancelled`; when all active attempts complete, transition graph to `cancelled`
- Graph terminal status is always `cancelled` for user-initiated cancel
- Records `DelegationEvent` and `AuditLogger::recordUserAction`

### 2.5 Metrics Recomputation

**`app/Support/Delegation/DelegateeMetricsRecomputer.php`** — Two trigger modes:
1. **Event-triggered**: Listens to `DelegationAttemptFinished` with 60-second cache-lock throttle (`Cache::lock('delegation:metrics:recompute:' . $profileId, 60)`)
2. **Scheduled fallback**: Artisan command `delegation:recompute-metrics` registered in `routes/console.php`, scheduled every 15 minutes via `Schedule::command('delegation:recompute-metrics')->everyFifteenMinutes()->withoutOverlapping(5)`

Queries `DelegationAttempt` records within sliding windows (24h, 7d) for the specific `delegatee_profile_id`. Computes: `total_attempts`, `succeeded`, `failed`, `timed_out`, `avg_duration_ms`, `success_rate` (succeeded/total), `p95_duration_ms`. Updates/creates `DelegateeMetric` record.

### 2.6 Delegation Reconciler

**`app/Support/Delegation/DelegationReconciler.php`** — Modelled on `ReconcileActiveRunsService`. Artisan command `delegation:reconcile` registered in `routes/console.php`, scheduled every 2 minutes via `Schedule::command('delegation:reconcile')->everyTwoMinutes()->withoutOverlapping(2)`.

Detects and fixes:
1. **Stuck tasks**: `DelegationTask` in active status (`running`) where latest `DelegationAttempt`'s linked `AgentJobRun` is in terminal status but attempt/task haven't transitioned. Fires missed `DelegationAttemptFinished` event.
2. **Missed graph completions**: `DelegationGraph` in `running` status where all `DelegationTask` records are in terminal statuses but graph hasn't transitioned. Derives terminal status and fires `DelegationGraphCompleted`.
3. **Expired human approval timeouts**: `DelegationVerificationResult` with `verdict = 'pending'` and `step_type = 'human_approval'` where `created_at + config('delegation.verification_timeout_seconds')` has passed. Updates verdict to `'failed'`, fires `DelegationTaskVerified` to resume pipeline (which will short-circuit on failure).

### 2.7 Scheduler Registration

Add to `routes/console.php`:
```php
Schedule::command('delegation:reconcile')
    ->everyTwoMinutes()
    ->withoutOverlapping(2);

Schedule::command('delegation:recompute-metrics')
    ->everyFifteenMinutes()
    ->withoutOverlapping(5);
```

### 2.8 Horizon Config Update

Add to `config/horizon.php`:

In `defaults`:
```php
'supervisor-delegation' => [
    'connection' => 'redis',
    'queue' => ['delegation'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'maxProcesses' => max(1, min(8, (int) env('HORIZON_DELEGATION_MAX_PROCESSES', 2))),
    'maxTime' => 0,
    'maxJobs' => 0,
    'memory' => 128,
    'tries' => 1,
    'backoff' => 0,
    'timeout' => 900,
    'nice' => 0,
],
```

In `waits`:
```php
'redis:delegation' => 30,
```

In `environments.production`:
```php
'supervisor-delegation' => [
    'maxProcesses' => max(1, min(8, (int) env('HORIZON_DELEGATION_MAX_PROCESSES', 2))),
    'balanceMaxShift' => 1,
    'balanceCooldown' => 3,
],
```

In `environments.local`:
```php
'supervisor-delegation' => [
    //
],
```

### 2.9 Tests (Phase 2)

- `tests/Feature/DelegationCoordinatorTest.php` — event flow from graph start to completion for linear chain, multi-path DAG with parallel branches, eager-ready/lazy-dispatch behavior, sequence_order priority
- `tests/Feature/AttemptSpawnerTest.php` — transient AgentJob creation from DelegateeProfile config with `cron_expression = '0 0 1 1 0'` and `is_enabled = false`, command_template pass-through, contract enforcement via ContractEnforcer, AgentJobRun creation and linkage, Bus::chain dispatch
- `tests/Feature/DelegationAttemptCompletionJobTest.php` — run terminal triggers attempt completion, status mapping (succeeded→succeeded, failed→failed, timed_out→failed, killed→failed)
- `tests/Feature/RecoveryHandlerTest.php` — retry (transient error, same delegatee, checks against `config('delegation.max_retry_attempts')`), re-delegate (non-transient or exhausted), escalate (high criticality, create pending human_approval), abort (low criticality or no delegatees), wildcard error code matching, time_constraints ceiling enforcement, partial completion (only transitive dependents failed)
- `tests/Feature/VerificationPipelineTest.php` — all step types execute, short-circuit on failure, evidence persistence, pipeline resumability (pending step → resolve → resume next step), AiCriticStep non-blocking flow with transient job using sentinel cron, HumanApprovalStep non-blocking flow
- `tests/Feature/DelegateeMetricsRecomputerTest.php` — window computation accuracy, 60-second throttle, scheduled fallback, p95 calculation
- `tests/Feature/DelegationReconcilerTest.php` — stuck task detection (active task with terminal run), missed graph completion, expired human approval timeout
- `tests/Feature/GraphStartCancelTest.php` — concurrent graph limit (max 3 rejects 4th), kill cancellation (SIGTERM sent, tasks cancelled), drain cancellation (active attempts finish, succeeded preserved, pending/blocked/ready cancelled), terminal status always 'cancelled' for user cancel

---

## Phase 3 — Broadcast and UI

**Goal**: Real-time WebSocket updates and operator-facing UI for delegation graph visualization, monitoring, and human approval.

### 3.1 Broadcast Events

Follow `InterrogationSessionUpdated` pattern: implement `ShouldBroadcast`, `broadcastOn`/`broadcastAs`/`broadcastWith`.

**`app/Events/Delegation/DelegationGraphBroadcast.php`** — `ShouldBroadcast` on `PrivateChannel('delegation.graph.' . $this->graphId)`. Events: graph status changes, task status changes, attempt status changes, verification results. Payload includes `graph_id`, `event_type`, enriched data (task names, delegatee names, progress percentages).

**`app/Events/Delegation/DelegationUserSummaryBroadcast.php`** — `ShouldBroadcast` on `PrivateChannel('delegation.user.' . $this->userId)`. Events: graph started, graph completed, graph failed, escalation needed (human approval pending).

### 3.2 Broadcast Subscriber

**`app/Support/Delegation/DelegationBroadcastSubscriber.php`** — Separate event listener (not combined with Coordinator). Listens to internal domain events. Selectively dispatches broadcast events (curated subset). Enriches payloads with display-friendly data: task names, delegatee names, progress percentages (completed tasks / total tasks), current status display text.

### 3.3 Channel Authorization

Add to `routes/channels.php`:
```php
Broadcast::channel('delegation.graph.{graphId}', function ($user, $graphId) {
    return DelegationGraph::query()
        ->whereKey((int) $graphId)
        ->where('user_id', (int) $user->id)
        ->exists();
});

Broadcast::channel('delegation.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

### 3.4 Vue Components and Pages

All under `resources/js/Pages/Delegation/` and `resources/js/Components/Delegation/`:

**Pages**:
- `DelegationIndex.vue` — list of user's delegation graphs with status badges (color-coded), filters (by status), create button, paginated. Real-time updates via Echo on user channel.
- `DelegationGraphShow.vue` — main graph detail page. Shows graph metadata, task list with status indicators, DAG visualization component, real-time status updates via Echo on graph channel. Links to task detail.
- `DelegationGraphCreate.vue` — form for creating graphs. Two modes: JSON editor (raw DAG JSON with explicit dependency edges) and linear-chain shorthand mode (simplified ordered task list). Inline validation panel showing ContractValidator results. Submit calls `POST /graphs` then redirects to show.
- `DelegationVerificationApproval.vue` — human approval UI for pending verification steps. Shows task context, attempt output excerpt, approve/reject buttons with notes textarea. Calls `POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve`.
- `DelegateeProfileIndex.vue` — list/manage delegatee profiles. Shows capability badges, trust metrics (success_rate, total attempts), active/inactive status. Create/edit/delete actions.
- `DelegateeProfileForm.vue` — create/edit delegatee profile form. Fields: name, runner_type, command_template, working_directory, env_json (key-value editor), capability multi-select. Validates inline against same rules as API.

**Components**:
- `DelegationGraphVisualization.vue` — custom SVG component with dagre layout library. Nodes rendered as SVG `<rect>` + `<text>` elements with status-colored fills (draft=gray, running=blue, succeeded=green, failed=red, cancelled=orange, verifying=yellow, pending=lightgray). Edges rendered as SVG `<path>` elements (dependency arrows). Interactions: zoom via wheel event → SVG viewBox scaling, pan via mousedown+drag → viewBox translate, node click via click event on SVG `<g>` group → emits `node-clicked` event with taskId to parent for detail navigation. All implemented with native SVG transforms and vanilla JS event handlers.

### 3.5 Web Routes

Add to `routes/web.php` inside the authenticated middleware group, gated behind `config('delegation.ui_enabled')`:
```php
Route::middleware(function ($request, $next) {
    if (! config('delegation.ui_enabled')) {
        abort(404);
    }
    return $next($request);
})->group(function () {
    Route::get('/delegation', fn () => Inertia::render('Delegation/Index'))->name('delegation.index');
    Route::get('/delegation/create', fn () => Inertia::render('Delegation/Create'))->name('delegation.create');
    Route::get('/delegation/profiles', fn () => Inertia::render('Delegation/ProfileIndex'))->name('delegation.profiles.index');
    Route::get('/delegation/profiles/create', fn () => Inertia::render('Delegation/ProfileForm'))->name('delegation.profiles.create');
    Route::get('/delegation/profiles/{id}/edit', fn (int $id) => Inertia::render('Delegation/ProfileForm', ['profileId' => $id]))->name('delegation.profiles.edit');
    Route::get('/delegation/{id}', fn (int $id) => Inertia::render('Delegation/GraphShow', ['graphId' => $id]))->name('delegation.show');
});
```

### 3.6 NPM Dependency

Add `dagre` to `package.json` devDependencies (or dependencies). Used by `DelegationGraphVisualization.vue` for DAG layout computation.

### 3.7 Tests (Phase 3)

- `tests/Feature/DelegationBroadcastTest.php` — verify correct broadcast events dispatched on correct private channels with correct payloads when domain events fire (graph status change, task status change, verification result, escalation)
- `tests/Feature/DelegationChannelAuthTest.php` — graph channel: owner authorized, non-owner rejected; user channel: matching user authorized, different user rejected
- `tests/Feature/DelegationWebRouteAuthTest.php` — Inertia page access requires authentication, returns 404 when `config('delegation.ui_enabled')` is false, returns 200 when enabled

---

## Phase 4 — Hardening and Observability

**Goal**: Production readiness — monitoring, audit integration, performance tuning, feature flag cleanup.

### 4.1 Structured Logging

Add structured JSON logging (via `Log::info('delegation.*', [...])`) to all coordination decision points:
- `delegation.graph.started` — `{graph_id, user_id, task_count}`
- `delegation.graph.completed` — `{graph_id, terminal_status, duration_ms, tasks_by_status: {succeeded: N, failed: N, cancelled: N}}`
- `delegation.task.assigned` — `{graph_id, task_id, delegatee_profile_id, assignment_reason}`
- `delegation.task.retried` — `{graph_id, task_id, attempt_number, error_code, classification}`
- `delegation.task.escalated` — `{graph_id, task_id, criticality, reason}`
- `delegation.attempt.completed` — `{attempt_id, task_id, status, duration_ms, error_code}`
- `delegation.verification.completed` — `{task_id, step_type, verdict, duration_ms}`
- `delegation.recovery.decision` — `{task_id, decision: retry|redelegate|escalate|abort, reason}`
- `delegation.reconciler.run` — `{stuck_tasks_found, missed_completions, expired_approvals}`

### 4.2 Horizon Queue Tags

Add `tags()` method to delegation queue jobs (`DelegationAttemptCompletionJob`, `AiCriticCompletionJob`, metrics/reconciler commands if converted to jobs) returning `['delegation', 'graph:' . $graphId, 'task:' . $taskId]` for Horizon dashboard filtering.

### 4.3 Audit Log Integration

Use existing `AuditLogger` which already supports arbitrary `target_type` strings — no code changes needed to AuditLogger itself.

User-initiated actions (recorded via `recordUserAction` in controllers):
- `delegation_graph.create`, `delegation_graph.update`, `delegation_graph.start`, `delegation_graph.cancel`, `delegation_graph.clone`, `delegation_graph.delete`, `delegation_graph.restore`
- `delegatee_profile.create`, `delegatee_profile.update`, `delegatee_profile.delete`, `delegatee_profile.restore`
- `delegation_verification.resolve` (human approval resolution)

Automated system action (recorded via `recordSystemAction`):
- One summary audit log entry when graph reaches terminal status: `delegation_graph.completed` with `after` payload containing `{status, tasks_succeeded, tasks_failed, tasks_cancelled, duration_ms}`

Automated coordination actions (task assignment, retry, re-delegation, escalation) are recorded in `DelegationEvent` only — not in `AuditLogger`.

### 4.4 Performance Tuning

- Review database query patterns after initial deployment. Add additional indexes if query analysis reveals slow paths (e.g., compound index on `delegation_attempts` for metrics recomputation window queries).
- Optimize `DelegationReconciler` query to use `chunkById(100)` processing to avoid loading all active tasks into memory.
- Ensure metrics recomputation queries use proper window bounds (e.g., `WHERE started_at >= NOW() - INTERVAL '24 hours'`) with existing timestamp indexes.
- Load test with 25-task graphs to verify concurrency limits work correctly under parallel dispatch.

### 4.5 Feature Flag Removal (Post-Validation)

After validation in production:
- Set `delegation.enabled` and `delegation.ui_enabled` to `true` by default in `config/delegation.php`
- Optionally remove `DelegationFeatureGate` middleware from route group (or leave as permanent kill switch)
- Remove ui_enabled inline middleware check from web routes (or leave as permanent kill switch)

### 4.6 Tests (Phase 4)

- `tests/Feature/DelegationAuditLogTest.php` — verify all user-initiated delegation actions produce `AgentAuditLog` entries with correct `target_type`, `action`, `before`/`after` payloads. Verify summary `recordSystemAction` entry on graph terminal status with correct task counts.
- `tests/Feature/DelegationEndToEndTest.php` — full integration: create graph via API → validate → start → simulate AttemptCompletionJob firing with successful run → verification pipeline executes (AutomatedCheckStep) → task completes → graph completes with `succeeded` status. Verify all events, state transitions, metrics updated, audit log written.
- `tests/Feature/DelegationHorizonConfigTest.php` — verify `supervisor-delegation` present in Horizon config with correct queue, timeout, and maxProcesses settings (follows pattern of existing `InterrogationHorizonConfigTest`)

---

## File Inventory

### Modified Files (7)
- `config/delegation.php` — add missing keys (transient_error_codes, non_transient_error_codes, ai_critic_prompt_template, check_profiles), update capabilities_seed and default_max_parallel_tasks, remove max_recovery_attempts
- `config/horizon.php` — add supervisor-delegation defaults + environment overrides + waits entry
- `routes/api.php` — add delegation route group with DelegationFeatureGate middleware
- `routes/channels.php` — add delegation.graph.{graphId} and delegation.user.{userId} channel auth
- `routes/web.php` — add delegation Inertia routes gated by delegation.ui_enabled
- `routes/console.php` — register delegation:reconcile (every 2 min) and delegation:recompute-metrics (every 15 min) schedules
- `database/seeders/DatabaseSeeder.php` — call DelegationCapabilitySeeder
- `package.json` — add dagre dependency

### New Migrations (10)
- `create_delegation_capabilities_table`, `create_delegatee_profiles_table`, `create_delegatee_capabilities_pivot_table`, `create_delegatee_metrics_table`, `create_delegation_graphs_table`, `create_delegation_tasks_table`, `create_delegation_task_dependencies_table`, `create_delegation_attempts_table`, `create_delegation_verification_results_table`, `create_delegation_events_table`

### New Models (10)
- `DelegationCapability`, `DelegateeProfile`, `DelegateeCapabilityPivot`, `DelegateeMetric`, `DelegationGraph`, `DelegationTask`, `DelegationTaskDependency`, `DelegationAttempt`, `DelegationVerificationResult`, `DelegationEvent`

### New Support Services (14) — `app/Support/Delegation/`
- `GraphStateTransitionService`, `TaskStateTransitionService`, `ContractValidator`, `DelegationGraphBuilder`, `ContractEnforcer`, `DelegateeAssigner`, `DelegationEventWriter`, `DelegationCoordinator`, `AttemptSpawner`, `RecoveryHandler`, `VerificationPipeline`, `DelegateeMetricsRecomputer`, `DelegationReconciler`, `DelegationBroadcastSubscriber`

### New Verification Steps (3) — `app/Support/Delegation/Verification/`
- `VerificationStepInterface`, `AutomatedCheckStep`, `AiCriticStep`, `HumanApprovalStep`

### New Domain Events (9) — `app/Events/Delegation/`
- `DelegationGraphStarted`, `DelegationGraphCompleted`, `DelegationTaskReady`, `DelegationTaskAssigned`, `DelegationTaskStarted`, `DelegationTaskCompleted`, `DelegationTaskVerified`, `DelegationAttemptFinished`, `DelegationRecoveryTriggered`

### New Broadcast Events (2) — `app/Events/Delegation/`
- `DelegationGraphBroadcast`, `DelegationUserSummaryBroadcast`

### New Queue Jobs (2) — `app/Jobs/`
- `DelegationAttemptCompletionJob`, `AiCriticCompletionJob`

### New Artisan Commands (2) — `app/Console/Commands/`
- `DelegationReconcileCommand` (`delegation:reconcile`), `DelegationRecomputeMetricsCommand` (`delegation:recompute-metrics`)

### New Controllers (3) — `app/Http/Controllers/Api/V1/`
- `DelegationGraphController`, `DelegationTaskController`, `DelegateeProfileController`

### New Policies (2) — `app/Policies/`
- `DelegationGraphPolicy`, `DelegateeProfilePolicy`

### New Seeder (1) — `database/seeders/`
- `DelegationCapabilitySeeder`

### New Vue Pages/Components (8) — `resources/js/Pages/Delegation/` and `resources/js/Components/Delegation/`
- `Index.vue`, `GraphShow.vue`, `Create.vue` (GraphCreate), `TaskDetail.vue`, `VerificationApproval.vue`, `ProfileIndex.vue`, `ProfileForm.vue`, `GraphVisualization.vue`

### New Test Files (19)
- Phase 1 (8): DelegationGraphBuilderTest, ContractValidatorTest, ContractEnforcerTest, DelegateeAssignerTest, GraphStateTransitionTest, TaskStateTransitionTest, DelegationApiWorkflowTest, DelegateeProfileApiTest
- Phase 2 (8): DelegationCoordinatorTest, AttemptSpawnerTest, DelegationAttemptCompletionJobTest, RecoveryHandlerTest, VerificationPipelineTest, DelegateeMetricsRecomputerTest, DelegationReconcilerTest, GraphStartCancelTest
- Phase 3 (3): DelegationBroadcastTest, DelegationChannelAuthTest, DelegationWebRouteAuthTest
- Phase 4 (3): DelegationAuditLogTest, DelegationEndToEndTest, DelegationHorizonConfigTest

---

## Revision Diff Summary

### Fix 1 — Transient AgentJob cron expression
- **Phase 2, §2.2 AttemptSpawner**: Changed `cron_expression = '* * * * *'` to `cron_expression = '0 0 1 1 0'` (project-standard sentinel cron for non-scheduled transient jobs, matching `BuildTaskRunFactory` pattern).
- **Phase 2, §2.3 AiCriticStep**: Added explicit note that transient review job uses `cron_expression = '0 0 1 1 0'` (same sentinel cron as AttemptSpawner).
- **Phase 2, §2.9 AttemptSpawnerTest**: Updated acceptance to verify transient job uses `cron_expression = '0 0 1 1 0'`.
- **Phase 2, §2.9 VerificationPipelineTest**: Added note verifying AiCriticStep transient job uses sentinel cron.

### Fix 2 — Clone eligibility restricted to terminal statuses only
- **Phase 1, §1.6 DelegationGraphController::clone**: Changed "available on graphs in any terminal status" wording to explicitly state "available only on graphs in terminal statuses (`succeeded`, `failed`, `partial`, `cancelled`)". (Previously the endpoint description said terminal but the policy said "any status".)
- **Phase 1, §1.7 DelegationGraphPolicy::clone**: Changed from "ownership (available on any status the user owns)" to "ownership + status must be in `TERMINAL_STATUSES` (`succeeded`, `failed`, `partial`, `cancelled`)". This is the authoritative correction — policy now enforces terminal-only.
- **Phase 1, §1.9 DelegationApiWorkflowTest**: Updated clone test description to include "terminal status only, rejected on non-terminal" assertion.

### Fix 3 — Retry config key consolidation (`max_recovery_attempts` removed)
- **Phase 1, §1.1 Config**: Removed `max_recovery_attempts` key from config schema. The canonical key is `max_retry_attempts` (int, default 3). Added explicit note in changes summary: "Remove `max_recovery_attempts` key (consolidated into `max_retry_attempts`)".
- All formulas and references throughout the plan already used `config('delegation.max_retry_attempts')` — no other changes needed since the plan text (ContractValidator, RecoveryHandler decision chain) was already consistent with `max_retry_attempts`.

## Sections

- Phase 1 — Backend Foundation: Update config/delegation.php with missing keys (transient_error_codes, non_transient_error_codes, ai_critic_prompt_template, check_profiles, capabilities_seed alignment), remove max_recovery_attempts (consolidated into max_retry_attempts). Create 10 migrations following agent_jobs pattern (named indexes, foreignId()->constrained()->cascadeOnDelete(), softDeletes). DelegateeProfile migration includes runner_type (string 16), command_template (string 2000), working_directory (string 1024), env_json (json nullable), config_json (json nullable) with composite index. Create 10 Eloquent models with $guarded=[], casts(), relationships, status constants, ACTIVE_STATUSES/TERMINAL_STATUSES arrays (DelegationGraph includes 'partial' and 'cancelled'). Build core services: GraphStateTransitionService and TaskStateTransitionService (atomic whereKey/whereIn/update pattern from RunStateTransitionService), ContractValidator (capability existence, max_runtime_seconds ≤ 86400, criticality enum, time_constraints cap using max_retry_attempts, check profile validation, prompt/task_markdown_path presence), DelegationGraphBuilder (DAG JSON and linear-chain shorthand, DB transaction, Kahn's algorithm cycle detection, max 25 tasks, auto sequence_order from topological depth with caller override), ContractEnforcer (PathPolicy/EnvPolicy intersection, max_runtime_seconds cap), DelegateeAssigner (capability matching, success_rate ranking, load tiebreak), DelegationEventWriter (auto-incrementing sequence per graph). Create 3 controllers (DelegationGraphController with CRUD+validate+start+cancel+clone+events, DelegationTaskController with list+show+resolveVerification, DelegateeProfileController with CRUD+soft delete), 2 authorization policies (DelegationGraphPolicy with state-based guards including clone restricted to TERMINAL_STATUSES only, DelegateeProfilePolicy with ownership). Create DelegationCapabilitySeeder, update DatabaseSeeder. DelegationFeatureGate middleware already exists. 8 test files.
- Phase 2 — Execution Engine: Create 9 internal domain event classes in app/Events/Delegation/. Build DelegationCoordinator as EventSubscriberInterface for happy-path flow (GraphStarted→root tasks ready→assign→spawn→AttemptFinished success→verify→TaskVerified→resume or complete→check graph→next ready tasks). Build AttemptSpawner (creates DelegationAttempt, transient AgentJob from DelegateeProfile config with command_template pass-through, cron_expression='0 0 1 1 0' sentinel, is_enabled=false, dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with DelegationAttemptCompletionJob callback on 'delegation' queue). Build DelegationAttemptCompletionJob (maps AgentJobRun terminal status to attempt status, fires DelegationAttemptFinished). Build RecoveryHandler as separate listener (combined heuristic: timed_out=transient, skipped=non-transient, failed/killed=config error_code lookup with wildcard, decision chain using config('delegation.max_retry_attempts'): retry→re-delegate→escalate→abort, criticality influences escalation threshold, partial completion propagates failure only to transitive dependents). Build VerificationPipeline (resumable step tracking, short-circuits on failure) with 3 step implementations: AutomatedCheckStep (preset check profiles from config, blocking), AiCriticStep (spawns review AgentJobRun via Bus::chain with cron_expression='0 0 1 1 0' sentinel, non-blocking pending result, AiCriticCompletionJob callback), HumanApprovalStep (non-blocking pending result, API-driven resolution fires DelegationTaskVerified). Wire graph start logic (concurrent limit check, transition, event) and cancel logic (kill=SIGTERM via RunStateTransitionService stopping, drain=flag+let-finish+cancel-pending). Build DelegateeMetricsRecomputer (event-triggered with 60s cache-lock throttle + scheduled every 15 min). Build DelegationReconciler (stuck tasks, missed graph completions, expired human approval timeouts, scheduled every 2 min). Update config/horizon.php with supervisor-delegation (queue ['delegation'], timeout 900, maxProcesses env-driven). Register scheduler commands in routes/console.php. 8 test files.
- Phase 3 — Broadcast and UI: 2 broadcast event classes (DelegationGraphBroadcast on per-graph private channel, DelegationUserSummaryBroadcast on per-user private channel), DelegationBroadcastSubscriber as separate listener with enriched payloads. Channel auth in routes/channels.php (graph ownership, user match). 8 Vue pages/components: DelegationIndex (list with status badges), DelegationGraphShow (detail with DAG visualization and Echo updates), DelegationGraphCreate (JSON editor + linear-chain mode with inline validation), DelegationTaskDetail (contract, attempts, verification), DelegationVerificationApproval (approve/reject with notes), DelegateeProfileIndex (list with capability badges and metrics), DelegateeProfileForm (create/edit with inline validation), DelegationGraphVisualization (custom SVG + dagre layout, native SVG transform zoom/pan, click-to-detail). Inertia web routes gated by delegation.ui_enabled. Add dagre npm dependency. 3 test files.
- Phase 4 — Hardening and Observability: Add structured JSON logging (Log::info) to all coordination decisions with consistent key prefixes (delegation.graph.*, delegation.task.*, delegation.attempt.*, delegation.recovery.*, delegation.reconciler.*). Add Horizon queue tags to delegation jobs for dashboard filtering. Integrate with existing AuditLogger: user-initiated actions via recordUserAction in controllers (delegation_graph.create/update/start/cancel/clone/delete/restore, delegatee_profile.create/update/delete/restore, delegation_verification.resolve), one summary recordSystemAction when graph reaches terminal status with final status and task counts by status. Automated coordination recorded in DelegationEvent only. Performance tuning: chunkById for reconciler queries, proper window bounds for metrics queries, load test with 25-task graphs. Feature flag removal: set defaults to true in config after production validation. 3 test files (DelegationAuditLogTest, DelegationEndToEndTest, DelegationHorizonConfigTest).

## Risks

- Contract enforcement intersection logic is complex — ContractEnforcer must correctly narrow PathPolicy allowed_working_directory_bases and EnvPolicy forbidden_env_keys without creating impossible constraints or accidentally widening permissions beyond what the existing agent config allows. The intersection must be a strict subset. Mitigation: exhaustive unit tests for boundary conditions (empty intersection, single-base match, env key overlap with forbidden pattern), code review focused on security invariant that delegation can never grant more access than the base agent config.
- Event-driven coordination can miss events if queue workers crash mid-processing or Bus::chain callbacks fail to fire. If DelegationAttemptCompletionJob never runs, the task stays in 'running' forever. Mitigation: DelegationReconciler runs every 2 minutes as safety net (modelled on proven ReconcileActiveRunsService which handles the same class of problem for AgentJobRuns). All state transitions use atomic whereIn/update pattern ensuring idempotent recovery.
- Linking DelegationAttempts to AgentJobRuns via FK creates coupling between delegation and existing run lifecycle. If ExecuteAgentRunJob behavior changes (e.g., new terminal statuses, different finalization flow), DelegationAttemptCompletionJob may break or produce incorrect status mappings. Mitigation: DelegationAttemptCompletionJob maps only from AgentJobRun::TERMINAL_STATUSES which is a stable constant; dedicated test suite validates all mappings; any run lifecycle changes require delegation regression testing.
- DAG cycle detection and topological sort must be correct — a bug in Kahn's algorithm implementation could cause infinite loops during execution (task waiting for dependency that waits for it) or deadlocked graphs (tasks never becoming ready). Mitigation: well-tested DelegationGraphBuilder with explicit cycle detection; max task limit of 25 bounds algorithm complexity to O(V+E) where V≤25; test cases cover self-loops, mutual dependencies, transitive cycles, and valid complex DAGs.
- Human approval verification step and AiCriticStep both introduce non-blocking waits — if no human responds or the AI review run fails to complete, tasks hang in 'verifying' status indefinitely. VerificationPipeline resumability adds complexity with partial step completion tracking. Mitigation: verification_timeout_seconds config (default 300s) enforced by DelegationReconciler for human approval; AiCriticStep's chained callback handles review run failures; pipeline tracks position via existing DelegationVerificationResult records rather than in-memory state.
- Metrics recomputation under high delegation volume could create database load spikes — scanning DelegationAttempt records across sliding windows for multiple profiles concurrently. Mitigation: 60-second cache-lock throttle prevents event-storm triggering; scheduled fallback bounds maximum staleness to 15 minutes; queries scoped to single delegatee_profile_id with indexed timestamp columns; pre-aggregated snapshots in DelegateeMetric avoid per-request computation.
- AttemptSpawner creates transient AgentJob records that accumulate over time. Each delegation attempt generates an AgentJob + AgentJobRun pair that serves no purpose after the attempt completes. Mitigation: transient jobs created with is_enabled=false and sentinel cron_expression='0 0 1 1 0' so they're excluded from scheduling; existing agent:prune command cleans up soft-deleted jobs; transient jobs can be soft-deleted on attempt completion or batch-cleaned by a future maintenance task.
- Partial completion semantics with transitive dependent failure propagation is graph-traversal logic that must correctly identify all downstream tasks without over-propagating (failing tasks on independent branches) or under-propagating (missing deeply nested dependents). Mitigation: forward traversal from failed task through DelegationTaskDependency using BFS; unit tests with diamond dependencies, isolated branches, and deep chains verify correct propagation boundaries.
- Bus::chain callback pattern for completion detection means delegation depends on Laravel's job chaining reliability. If the chained callback job is lost (e.g., Redis connection failure between main job completion and callback dispatch), the completion is missed. Mitigation: DelegationReconciler (every 2 min) detects stuck tasks where the linked AgentJobRun is terminal but the attempt hasn't transitioned, providing guaranteed eventual consistency.

## Assumptions

- Existing Agent test suite (AgentApiWorkflowTest, AgentRunnerLifecycleTest, AgentJobValidationTest, all 49 existing test files) remains green throughout — delegation is additive-only with no modifications to existing models (AgentJob, AgentJobRun, AgentRunEvent), services (RunStateTransitionService, ExecuteAgentRunJob, CommandPolicy, PathPolicy, EnvPolicy, CommandTemplateRenderer), or migrations.
- config/delegation.php already exists with a subset of the required keys (enabled, ui_enabled, max_tasks_per_graph, etc.) and DelegationFeatureGate middleware already exists — Phase 1 extends the config file and leaves the middleware unchanged rather than creating either from scratch.
- Laravel Horizon queue infrastructure with Redis connection can handle an additional 'delegation' queue supervisor alongside existing 'agent' and 'interrogation' supervisors without Redis connection pool exhaustion — delegation queue volume is bounded by max_concurrent_graphs_per_user (3) × max_parallel_tasks (5) = 15 concurrent delegation jobs maximum per user.
- ExecuteAgentRunJob accepts any valid AgentJob+AgentJobRun pair for execution — it does not validate that the AgentJob was created through the normal job creation API flow, making it safe for AttemptSpawner to create transient AgentJob records programmatically and dispatch runs against them.
- Bus::chain() with a completion callback job reliably fires the callback when the primary job completes (succeeds or fails) — this is the standard Laravel behavior and has been stable since Laravel 8. The callback job is dispatched to the 'delegation' queue, not the 'agent' queue.
- CommandPolicy::validateForSave and CommandTemplateRenderer::renderTokens work correctly with DelegateeProfile-sourced command_template values — the same {{placeholder}} syntax and validation rules apply regardless of whether the source is an AgentJob or a DelegateeProfile.
- Vue 3 + Inertia.js stack supports adding dagre as an npm dependency for DAG layout computation — the dagre library outputs node/edge coordinates that can be consumed by custom SVG rendering without requiring a full graph rendering framework.
- The 25-task maximum per graph is sufficient for MVP delegation use cases. Kahn's algorithm for cycle detection and topological sort runs in O(V+E) which is trivially fast at this scale. No algorithmic optimization is needed beyond a correct implementation.
- Reverb/Echo WebSocket infrastructure can handle additional broadcast channels for delegation (per-graph and per-user private channels) without scaling changes — delegation broadcast volume is bounded by graph concurrency limits and selective event filtering in DelegationBroadcastSubscriber.
- The seeded capability list (code_generation, code_review, testing, documentation, refactoring, analysis, planning) covers MVP use cases. New capabilities can be added via the DelegationCapabilitySeeder or direct database insertion without code changes since DelegateeAssigner queries capabilities dynamically.
- Database is PostgreSQL (confirmed by pgvector extension migration 2026_02_16_040000_enable_pgvector_extension.php) — atomic whereIn/update pattern used by state transition services works correctly, timestampTz columns provide timezone-aware storage, json columns support proper indexing.
- AuditLogger already supports arbitrary target_type strings (it's a plain string column, not an enum) — no modifications to AuditLogger or the agent_audit_logs table are needed to record delegation-specific audit entries.

## Sections

- Phase 1 — Backend Foundation: Update config/delegation.php with missing keys (transient_error_codes, non_transient_error_codes, ai_critic_prompt_template, check_profiles, capabilities_seed alignment), remove max_recovery_attempts (consolidated into max_retry_attempts). Create 10 migrations following agent_jobs pattern (named indexes, foreignId()->constrained()->cascadeOnDelete(), softDeletes). DelegateeProfile migration includes runner_type (string 16), command_template (string 2000), working_directory (string 1024), env_json (json nullable), config_json (json nullable) with composite index. Create 10 Eloquent models with $guarded=[], casts(), relationships, status constants, ACTIVE_STATUSES/TERMINAL_STATUSES arrays (DelegationGraph includes 'partial' and 'cancelled'). Build core services: GraphStateTransitionService and TaskStateTransitionService (atomic whereKey/whereIn/update pattern from RunStateTransitionService), ContractValidator (capability existence, max_runtime_seconds ≤ 86400, criticality enum, time_constraints cap using max_retry_attempts, check profile validation, prompt/task_markdown_path presence), DelegationGraphBuilder (DAG JSON and linear-chain shorthand, DB transaction, Kahn's algorithm cycle detection, max 25 tasks, auto sequence_order from topological depth with caller override), ContractEnforcer (PathPolicy/EnvPolicy intersection, max_runtime_seconds cap), DelegateeAssigner (capability matching, success_rate ranking, load tiebreak), DelegationEventWriter (auto-incrementing sequence per graph). Create 3 controllers (DelegationGraphController with CRUD+validate+start+cancel+clone+events, DelegationTaskController with list+show+resolveVerification, DelegateeProfileController with CRUD+soft delete), 2 authorization policies (DelegationGraphPolicy with state-based guards including clone restricted to TERMINAL_STATUSES only, DelegateeProfilePolicy with ownership). Create DelegationCapabilitySeeder, update DatabaseSeeder. DelegationFeatureGate middleware already exists. 8 test files.
- Phase 2 — Execution Engine: Create 9 internal domain event classes in app/Events/Delegation/. Build DelegationCoordinator as EventSubscriberInterface for happy-path flow (GraphStarted→root tasks ready→assign→spawn→AttemptFinished success→verify→TaskVerified→resume or complete→check graph→next ready tasks). Build AttemptSpawner (creates DelegationAttempt, transient AgentJob from DelegateeProfile config with command_template pass-through, cron_expression='0 0 1 1 0' sentinel, is_enabled=false, dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with DelegationAttemptCompletionJob callback on 'delegation' queue). Build DelegationAttemptCompletionJob (maps AgentJobRun terminal status to attempt status, fires DelegationAttemptFinished). Build RecoveryHandler as separate listener (combined heuristic: timed_out=transient, skipped=non-transient, failed/killed=config error_code lookup with wildcard, decision chain using config('delegation.max_retry_attempts'): retry→re-delegate→escalate→abort, criticality influences escalation threshold, partial completion propagates failure only to transitive dependents). Build VerificationPipeline (resumable step tracking, short-circuits on failure) with 3 step implementations: AutomatedCheckStep (preset check profiles from config, blocking), AiCriticStep (spawns review AgentJobRun via Bus::chain with cron_expression='0 0 1 1 0' sentinel, non-blocking pending result, AiCriticCompletionJob callback), HumanApprovalStep (non-blocking pending result, API-driven resolution fires DelegationTaskVerified). Wire graph start logic (concurrent limit check, transition, event) and cancel logic (kill=SIGTERM via RunStateTransitionService stopping, drain=flag+let-finish+cancel-pending). Build DelegateeMetricsRecomputer (event-triggered with 60s cache-lock throttle + scheduled every 15 min). Build DelegationReconciler (stuck tasks, missed graph completions, expired human approval timeouts, scheduled every 2 min). Update config/horizon.php with supervisor-delegation (queue ['delegation'], timeout 900, maxProcesses env-driven). Register scheduler commands in routes/console.php. 8 test files.
- Phase 3 — Broadcast and UI: 2 broadcast event classes (DelegationGraphBroadcast on per-graph private channel, DelegationUserSummaryBroadcast on per-user private channel), DelegationBroadcastSubscriber as separate listener with enriched payloads. Channel auth in routes/channels.php (graph ownership, user match). 8 Vue pages/components: DelegationIndex (list with status badges), DelegationGraphShow (detail with DAG visualization and Echo updates), DelegationGraphCreate (JSON editor + linear-chain mode with inline validation), DelegationTaskDetail (contract, attempts, verification), DelegationVerificationApproval (approve/reject with notes), DelegateeProfileIndex (list with capability badges and metrics), DelegateeProfileForm (create/edit with inline validation), DelegationGraphVisualization (custom SVG + dagre layout, native SVG transform zoom/pan, click-to-detail). Inertia web routes gated by delegation.ui_enabled. Add dagre npm dependency. 3 test files.
- Phase 4 — Hardening and Observability: Add structured JSON logging (Log::info) to all coordination decisions with consistent key prefixes (delegation.graph.*, delegation.task.*, delegation.attempt.*, delegation.recovery.*, delegation.reconciler.*). Add Horizon queue tags to delegation jobs for dashboard filtering. Integrate with existing AuditLogger: user-initiated actions via recordUserAction in controllers (delegation_graph.create/update/start/cancel/clone/delete/restore, delegatee_profile.create/update/delete/restore, delegation_verification.resolve), one summary recordSystemAction when graph reaches terminal status with final status and task counts by status. Automated coordination recorded in DelegationEvent only. Performance tuning: chunkById for reconciler queries, proper window bounds for metrics queries, load test with 25-task graphs. Feature flag removal: set defaults to true in config after production validation. 3 test files (DelegationAuditLogTest, DelegationEndToEndTest, DelegationHorizonConfigTest).


## Risks

- Contract enforcement intersection logic is complex — ContractEnforcer must correctly narrow PathPolicy allowed_working_directory_bases and EnvPolicy forbidden_env_keys without creating impossible constraints or accidentally widening permissions beyond what the existing agent config allows. The intersection must be a strict subset. Mitigation: exhaustive unit tests for boundary conditions (empty intersection, single-base match, env key overlap with forbidden pattern), code review focused on security invariant that delegation can never grant more access than the base agent config.
- Event-driven coordination can miss events if queue workers crash mid-processing or Bus::chain callbacks fail to fire. If DelegationAttemptCompletionJob never runs, the task stays in 'running' forever. Mitigation: DelegationReconciler runs every 2 minutes as safety net (modelled on proven ReconcileActiveRunsService which handles the same class of problem for AgentJobRuns). All state transitions use atomic whereIn/update pattern ensuring idempotent recovery.
- Linking DelegationAttempts to AgentJobRuns via FK creates coupling between delegation and existing run lifecycle. If ExecuteAgentRunJob behavior changes (e.g., new terminal statuses, different finalization flow), DelegationAttemptCompletionJob may break or produce incorrect status mappings. Mitigation: DelegationAttemptCompletionJob maps only from AgentJobRun::TERMINAL_STATUSES which is a stable constant; dedicated test suite validates all mappings; any run lifecycle changes require delegation regression testing.
- DAG cycle detection and topological sort must be correct — a bug in Kahn's algorithm implementation could cause infinite loops during execution (task waiting for dependency that waits for it) or deadlocked graphs (tasks never becoming ready). Mitigation: well-tested DelegationGraphBuilder with explicit cycle detection; max task limit of 25 bounds algorithm complexity to O(V+E) where V≤25; test cases cover self-loops, mutual dependencies, transitive cycles, and valid complex DAGs.
- Human approval verification step and AiCriticStep both introduce non-blocking waits — if no human responds or the AI review run fails to complete, tasks hang in 'verifying' status indefinitely. VerificationPipeline resumability adds complexity with partial step completion tracking. Mitigation: verification_timeout_seconds config (default 300s) enforced by DelegationReconciler for human approval; AiCriticStep's chained callback handles review run failures; pipeline tracks position via existing DelegationVerificationResult records rather than in-memory state.
- Metrics recomputation under high delegation volume could create database load spikes — scanning DelegationAttempt records across sliding windows for multiple profiles concurrently. Mitigation: 60-second cache-lock throttle prevents event-storm triggering; scheduled fallback bounds maximum staleness to 15 minutes; queries scoped to single delegatee_profile_id with indexed timestamp columns; pre-aggregated snapshots in DelegateeMetric avoid per-request computation.
- AttemptSpawner creates transient AgentJob records that accumulate over time. Each delegation attempt generates an AgentJob + AgentJobRun pair that serves no purpose after the attempt completes. Mitigation: transient jobs created with is_enabled=false and sentinel cron_expression='0 0 1 1 0' so they're excluded from scheduling; existing agent:prune command cleans up soft-deleted jobs; transient jobs can be soft-deleted on attempt completion or batch-cleaned by a future maintenance task.
- Partial completion semantics with transitive dependent failure propagation is graph-traversal logic that must correctly identify all downstream tasks without over-propagating (failing tasks on independent branches) or under-propagating (missing deeply nested dependents). Mitigation: forward traversal from failed task through DelegationTaskDependency using BFS; unit tests with diamond dependencies, isolated branches, and deep chains verify correct propagation boundaries.
- Bus::chain callback pattern for completion detection means delegation depends on Laravel's job chaining reliability. If the chained callback job is lost (e.g., Redis connection failure between main job completion and callback dispatch), the completion is missed. Mitigation: DelegationReconciler (every 2 min) detects stuck tasks where the linked AgentJobRun is terminal but the attempt hasn't transitioned, providing guaranteed eventual consistency.


## Assumptions

- Existing Agent test suite (AgentApiWorkflowTest, AgentRunnerLifecycleTest, AgentJobValidationTest, all 49 existing test files) remains green throughout — delegation is additive-only with no modifications to existing models (AgentJob, AgentJobRun, AgentRunEvent), services (RunStateTransitionService, ExecuteAgentRunJob, CommandPolicy, PathPolicy, EnvPolicy, CommandTemplateRenderer), or migrations.
- config/delegation.php already exists with a subset of the required keys (enabled, ui_enabled, max_tasks_per_graph, etc.) and DelegationFeatureGate middleware already exists — Phase 1 extends the config file and leaves the middleware unchanged rather than creating either from scratch.
- Laravel Horizon queue infrastructure with Redis connection can handle an additional 'delegation' queue supervisor alongside existing 'agent' and 'interrogation' supervisors without Redis connection pool exhaustion — delegation queue volume is bounded by max_concurrent_graphs_per_user (3) × max_parallel_tasks (5) = 15 concurrent delegation jobs maximum per user.
- ExecuteAgentRunJob accepts any valid AgentJob+AgentJobRun pair for execution — it does not validate that the AgentJob was created through the normal job creation API flow, making it safe for AttemptSpawner to create transient AgentJob records programmatically and dispatch runs against them.
- Bus::chain() with a completion callback job reliably fires the callback when the primary job completes (succeeds or fails) — this is the standard Laravel behavior and has been stable since Laravel 8. The callback job is dispatched to the 'delegation' queue, not the 'agent' queue.
- CommandPolicy::validateForSave and CommandTemplateRenderer::renderTokens work correctly with DelegateeProfile-sourced command_template values — the same {{placeholder}} syntax and validation rules apply regardless of whether the source is an AgentJob or a DelegateeProfile.
- Vue 3 + Inertia.js stack supports adding dagre as an npm dependency for DAG layout computation — the dagre library outputs node/edge coordinates that can be consumed by custom SVG rendering without requiring a full graph rendering framework.
- The 25-task maximum per graph is sufficient for MVP delegation use cases. Kahn's algorithm for cycle detection and topological sort runs in O(V+E) which is trivially fast at this scale. No algorithmic optimization is needed beyond a correct implementation.
- Reverb/Echo WebSocket infrastructure can handle additional broadcast channels for delegation (per-graph and per-user private channels) without scaling changes — delegation broadcast volume is bounded by graph concurrency limits and selective event filtering in DelegationBroadcastSubscriber.
- The seeded capability list (code_generation, code_review, testing, documentation, refactoring, analysis, planning) covers MVP use cases. New capabilities can be added via the DelegationCapabilitySeeder or direct database insertion without code changes since DelegateeAssigner queries capabilities dynamically.
- Database is PostgreSQL (confirmed by pgvector extension migration 2026_02_16_040000_enable_pgvector_extension.php) — atomic whereIn/update pattern used by state transition services works correctly, timestampTz columns provide timezone-aware storage, json columns support proper indexing.
- AuditLogger already supports arbitrary target_type strings (it's a plain string column, not an enum) — no modifications to AuditLogger or the agent_audit_logs table are needed to record delegation-specific audit entries.

