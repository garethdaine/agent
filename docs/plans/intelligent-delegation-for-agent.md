# Implementation Plan

Derived from discovery session 2.

# Delegation Engineering — Implementation Plan

## Overview

Build a first-class delegation layer on top of Agent's existing job orchestration platform. Complex work is decomposed into a directed acyclic graph of verifiable delegated tasks with explicit authority, accountability, trust-aware assignment, and adaptive re-assignment.

The plan is divided into 4 sequential phases. Each phase is independently deployable behind feature flags (`delegation.enabled`, `delegation.ui_enabled`). All existing Agent tests must remain green throughout.

---

## Phase 1 — Backend Foundation (Data Layer + Core Services)

**Goal**: Establish all database tables, Eloquent models, config, seeder, feature gate, core domain services, API endpoints for graph CRUD, and policy authorization. No execution or coordination yet.

### 1.1 Config and Feature Gate

- Create `config/delegation.php` with:
  - `enabled` (bool, default false)
  - `ui_enabled` (bool, default false)
  - `max_tasks_per_graph` (int, default 25)
  - `max_concurrent_graphs_per_user` (int, default 3)
  - `max_parallel_tasks_per_graph` (int, default 5)
  - `max_retry_attempts` (int, default 3)
  - `recovery_thresholds` (array: timeout_seconds, cost_overrun_percent, max_consecutive_failures)
  - `metrics_throttle_seconds` (int, default 60)
  - `metrics_scheduled_interval_minutes` (int, default 15)
  - `reconciler_interval_minutes` (int, default 2)
  - `cancellation_default_policy` (string, default 'drain')
  - `capabilities_seed` (array of initial capability slugs)
  - `verification_timeout_seconds` (int, default 300)
- Create `app/Http/Middleware/DelegationFeatureGate.php` — returns 404 JSON when `delegation.enabled` is false. Pattern: check `config('delegation.enabled')`, abort with ErrorEnvelope.

### 1.2 Database Migrations (10 migrations, sequential timestamps)

All follow existing patterns from `2026_02_12_020511_create_agent_jobs_table.php`: named indexes, explicit column lengths, `foreignId()->constrained()->cascadeOnDelete()`, `softDeletes()` where applicable.

**Migration 1**: `create_delegation_capabilities_table`
- `id`, `slug` (string 64, unique), `name` (string 120), `description` (text nullable), `is_active` (boolean default true), `timestamps`

**Migration 2**: `create_delegatee_profiles_table`
- `id`, `user_id` (FK constrained cascadeOnDelete), `name` (string 120), `runner_type` (string 16), `config_json` (json nullable — template overrides, env, working_directory), `is_active` (boolean default true), `softDeletes`, `timestamps`
- Index: `(user_id, runner_type, is_active, deleted_at)`

**Migration 3**: `create_delegatee_capabilities_pivot_table`
- `id`, `delegatee_profile_id` (FK), `delegation_capability_id` (FK), `timestamps`
- Unique: `(delegatee_profile_id, delegation_capability_id)`

**Migration 4**: `create_delegatee_metrics_table`
- `id`, `delegatee_profile_id` (FK unique), `window_24h_json` (json nullable), `window_7d_json` (json nullable), `last_recomputed_at` (timestampTz nullable), `timestamps`
- Each window JSON: `{total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms}`

**Migration 5**: `create_delegation_graphs_table`
- `id`, `user_id` (FK constrained cascadeOnDelete), `name` (string 255), `description` (text nullable), `status` (string 24, default 'draft'), `cancellation_policy` (string 16, default config value), `max_parallel_tasks` (unsignedTinyInteger, default config value), `metadata_json` (json nullable), `error_code` (string 100 nullable), `error_summary` (text nullable), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `softDeletes`, `timestamps`
- Index: `(user_id, status, deleted_at)`

**Migration 6**: `create_delegation_tasks_table`
- `id`, `delegation_graph_id` (FK constrained cascadeOnDelete), `name` (string 255), `status` (string 24, default 'pending'), `sequence_order` (unsignedSmallInteger), `contract_json` (json — required_capability, authority_scope, reversibility, criticality, budget_constraints, time_constraints, verification_strategy), `assigned_delegatee_profile_id` (FK nullable constrained nullOnDelete), `assignment_reason_json` (json nullable), `metadata_json` (json nullable), `error_code` (string 100 nullable), `error_summary` (text nullable), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `timestamps`
- Index: `(delegation_graph_id, status)`
- Index: `(assigned_delegatee_profile_id)`

**Migration 7**: `create_delegation_task_dependencies_table`
- `id`, `task_id` (FK constrained cascadeOnDelete — the dependent task), `depends_on_task_id` (FK constrained cascadeOnDelete — the prerequisite), `timestamps`
- Unique: `(task_id, depends_on_task_id)`

**Migration 8**: `create_delegation_attempts_table`
- `id`, `delegation_task_id` (FK constrained cascadeOnDelete), `delegatee_profile_id` (FK constrained), `agent_job_run_id` (FK nullable constrained nullOnDelete), `attempt_number` (unsignedTinyInteger), `status` (string 24, default 'running'), `started_at` (timestampTz), `finished_at` (timestampTz nullable), `duration_ms` (unsignedInteger default 0), `error_code` (string 100 nullable), `error_summary` (text nullable), `metadata_json` (json nullable), `timestamps`
- Index: `(delegation_task_id, attempt_number)`

**Migration 9**: `create_delegation_verification_results_table`
- `id`, `delegation_task_id` (FK constrained cascadeOnDelete), `delegation_attempt_id` (FK nullable constrained cascadeOnDelete), `step_type` (string 32 — 'automated_check', 'ai_critic', 'human_approval'), `step_order` (unsignedTinyInteger), `verdict` (string 16 — 'passed', 'failed', 'skipped', 'pending'), `evidence_json` (json nullable — output, logs, diff, reviewer notes), `started_at` (timestampTz nullable), `finished_at` (timestampTz nullable), `timestamps`
- Index: `(delegation_task_id, step_order)`

**Migration 10**: `create_delegation_events_table`
- `id`, `delegation_graph_id` (FK constrained cascadeOnDelete), `delegation_task_id` (FK nullable constrained cascadeOnDelete), `event_type` (string 64), `sequence` (unsignedInteger), `payload_json` (json nullable), `event_ts` (timestampTz), `timestamps`
- Index: `(delegation_graph_id, sequence)`
- Index: `(delegation_task_id, event_ts)`

### 1.3 Eloquent Models (10 models)

Follow existing patterns from AgentJob/AgentJobRun: `$guarded = []`, `casts()` method, relationship methods, status constants with `ACTIVE_STATUSES`/`TERMINAL_STATUSES` arrays, scopes.

- `DelegationCapability` — slug, name, description, isActive scope
- `DelegateeProfile` — SoftDeletes, relationships to user, capabilities (belongsToMany via pivot), metrics (hasOne), tasks, config_json cast
- `DelegateCapabilityPivot` — pivot model
- `DelegateeMetric` — belongsTo profile, window JSON casts
- `DelegationGraph` — SoftDeletes, status constants (draft/validating/ready/running/succeeded/failed/cancelled), ACTIVE_STATUSES, TERMINAL_STATUSES, relationships to user/tasks/events
- `DelegationTask` — status constants (pending/blocked/ready/assigned/running/verifying/succeeded/failed), relationships to graph/attempts/dependencies/dependents/verificationResults/assignedProfile
- `DelegationTaskDependency` — belongsTo task, belongsTo dependsOnTask
- `DelegationAttempt` — status constants (running/succeeded/failed), relationships to task/profile/agentJobRun
- `DelegationVerificationResult` — relationships to task/attempt, evidence_json cast
- `DelegationEvent` — relationships to graph/task, payload_json cast, event_ts cast

### 1.4 Core Domain Services

**`app/Support/Delegation/GraphStateTransitionService.php`**
- Exact same atomic pattern as `RunStateTransitionService`: `DelegationGraph::query()->whereKey($id)->whereIn('status', $from)->update($payload)`, returns bool.

**`app/Support/Delegation/TaskStateTransitionService.php`**
- Same pattern for DelegationTask status transitions.

**`app/Support/Delegation/ContractValidator.php`**
- Validates contract_json structure: required_capability must reference active capability, authority_scope keys must be valid, verification_strategy must have at least one step, budget/time constraints must be positive numbers.
- Returns `['valid' => bool, 'errors' => [...]]`.

**`app/Support/Delegation/DelegationGraphBuilder.php`**
- Accepts raw JSON or linear-chain shorthand format.
- Creates DelegationGraph + DelegationTasks + DelegationTaskDependencies in a DB transaction.
- Validates: no cycles (topological sort), max 25 tasks, all dependency references resolve, all contracts valid via ContractValidator.
- Returns created graph or validation errors.

**`app/Support/Delegation/ContractEnforcer.php`**
- Takes a DelegationTask's contract_json authority_scope and narrows the existing PathPolicy/CommandPolicy/EnvPolicy for the spawned AgentJobRun.
- Intersection logic: task's allowed_working_directories must be subset of config allowed bases; task's allowed_commands must match runner executable allowlist; task's allowed_env_keys must not include forbidden keys.
- Returns narrowed config array or validation error if contract is impossible to satisfy.

**`app/Support/Delegation/DelegateeAssigner.php`**
- Given a DelegationTask, finds eligible DelegateeProfiles by matching required_capability.
- Ranks by trust metrics (success_rate from DelegateeMetric sliding windows), then by availability (no active attempts over concurrent limit).
- Returns ranked list with assignment_reason_json explaining selection.

**`app/Support/Delegation/DelegationEventWriter.php`**
- Follows RunEventWriter pattern: auto-increments sequence per graph, writes DelegationEvent records.

### 1.5 API Endpoints

All under `agent/api/v1/delegation/` prefix, same auth/throttle middleware pattern as existing routes.

**Graphs**:
- `GET /graphs` — list user's graphs (paginated, filterable by status)
- `POST /graphs` — create graph (accepts JSON or linear-chain shorthand)
- `GET /graphs/{id}` — show graph with tasks and edges
- `PUT /graphs/{id}` — update draft graph
- `DELETE /graphs/{id}` — soft delete graph
- `POST /graphs/{id}/restore` — restore soft-deleted graph
- `POST /graphs/{id}/validate` — validate and transition draft to ready
- `POST /graphs/{id}/start` — start execution (Phase 2 wires this up)
- `POST /graphs/{id}/cancel` — cancel graph (Phase 2 wires this up)

**Tasks** (nested under graph):
- `GET /graphs/{graphId}/tasks` — list tasks
- `GET /graphs/{graphId}/tasks/{taskId}` — show task with attempts and verification results

**Delegatee Profiles**:
- `GET /delegatee-profiles` — list profiles
- `POST /delegatee-profiles` — create profile
- `GET /delegatee-profiles/{id}` — show profile with capabilities and metrics
- `PUT /delegatee-profiles/{id}` — update profile
- `DELETE /delegatee-profiles/{id}` — soft delete

**Events**:
- `GET /graphs/{id}/events` — list delegation events for graph

### 1.6 Authorization Policy

`DelegationGraphPolicy` — ownership checks (user_id matches auth user) plus state-based guards:
- `update`: only in draft status
- `start`: only in ready status
- `cancel`: only in active statuses
- `delete`: only in draft or terminal statuses
- `restore`: only soft-deleted

`DelegateeProfilePolicy` — ownership checks only.

### 1.7 Database Seeder

`DelegationCapabilitySeeder` — seeds initial capabilities from `config('delegation.capabilities_seed')`: code_generation, code_review, testing, documentation, refactoring, analysis, planning.

### 1.8 Tests (Phase 1)

- `DelegationGraphBuilderTest` — graph creation, cycle detection, max size, linear-chain shorthand, dependency resolution
- `ContractValidatorTest` — valid/invalid contracts, missing capability, missing verification strategy
- `ContractEnforcerTest` — narrowing logic, impossible contracts, intersection with existing policies
- `DelegateeAssignerTest` — capability matching, trust ranking, no eligible delegatee
- `GraphStateTransitionTest` — atomic transitions, concurrent conflict, invalid transitions
- `TaskStateTransitionTest` — all valid/invalid transitions
- `DelegationApiWorkflowTest` — full CRUD lifecycle via API endpoints, authorization, validation errors
- `DelegateeProfileApiTest` — CRUD, capability assignment, soft delete/restore

---

## Phase 2 — Execution Engine (Coordination + Recovery + Verification)

**Goal**: Wire up event-driven execution, recovery evaluation, verification pipeline, metrics recomputation, and reconciliation. Graphs can actually run.

### 2.1 Domain Events (Internal Laravel Events)

- `DelegationGraphStarted` — graph transitioned to running
- `DelegationGraphCompleted` — graph reached terminal status
- `DelegationTaskReady` — task's dependencies all satisfied
- `DelegationTaskAssigned` — delegatee selected
- `DelegationTaskStarted` — attempt spawned
- `DelegationTaskCompleted` — attempt finished (succeeded or failed)
- `DelegationTaskVerified` — verification pipeline completed
- `DelegationAttemptFinished` — attempt reached terminal status
- `DelegationRecoveryTriggered` — recovery evaluator invoked

### 2.2 Coordination Services

**`app/Support/Delegation/DelegationCoordinator.php`**
- Central event subscriber. Listens to domain events and dispatches actions.
- On `GraphStarted`: identify root tasks (no dependencies), transition to ready, trigger assignment.
- On `TaskReady`: invoke DelegateeAssigner, transition to assigned, spawn attempt.
- On `AttemptFinished`: if succeeded, transition task to verifying, invoke verification pipeline. If failed, invoke RecoveryEvaluator.
- On `TaskVerified`: if passed, transition to succeeded. Fire DelegationTaskCompleted. Check if all graph tasks are terminal — if yes, complete graph. Otherwise, identify newly-ready tasks and fire DelegationTaskReady for each.
- On `RecoveryTriggered`: execute recovery decision.

**`app/Support/Delegation/AttemptSpawner.php`**
- Creates DelegationAttempt record.
- Uses ContractEnforcer to build narrowed runtime config.
- Creates a transient AgentJob (or reuses graph-level job definition) and dispatches AgentJobRun via existing ExecuteAgentRunJob queue job.
- Links attempt to the created AgentJobRun via agent_job_run_id FK.

**`app/Support/Delegation/AttemptCompletionListener.php`**
- Listens to existing Agent run lifecycle events (run reaching terminal status).
- When an AgentJobRun linked to a DelegationAttempt finishes, transitions the attempt and fires DelegationAttemptFinished.

### 2.3 Recovery Evaluator

**`app/Support/Delegation/RecoveryEvaluator.php`**
- Hardcoded decision logic with config-driven thresholds from `config('delegation.recovery_thresholds')`.
- Decision chain: (1) if attempts < max_retry_attempts and error is transient → retry with same delegatee, (2) if attempts exhausted or non-transient error → re-delegate to next-ranked delegatee, (3) if no eligible delegatees remain → escalate to human approval (create pending human_approval verification step), (4) if escalation not possible or already escalated → abort task, propagate failure to graph.
- Each decision is recorded in DelegationEvent with full context.

### 2.4 Verification Pipeline

**`app/Support/Delegation/VerificationPipeline.php`**
- Receives task + attempt, reads verification_strategy from contract_json.
- Executes steps in order. Each step is a class implementing `VerificationStepInterface`.
- `AutomatedCheckStep` — runs configured checks (test commands, lint, pattern matching). Spawns a subprocess, captures output as evidence.
- `AiCriticStep` — sends task output to a reviewer AI runner. Parses structured verdict.
- `HumanApprovalStep` — creates a pending verification result, broadcasts to UI, waits for user action (approve/reject via API endpoint).
- Pipeline short-circuits on first failure. All results persisted as DelegationVerificationResult records.
- New API endpoint: `POST /graphs/{graphId}/tasks/{taskId}/verification/{resultId}/resolve` — for human approval resolution.

### 2.5 Metrics Recomputation

**`app/Support/Delegation/DelegateeMetricsRecomputer.php`**
- Queries DelegationAttempt records within sliding windows (24h, 7d).
- Computes: total_attempts, succeeded, failed, timed_out, avg_duration_ms, success_rate, p95_duration_ms.
- Updates DelegateeMetric record.
- Triggered by DelegationAttemptFinished event with 60-second throttle (using cache lock).
- Scheduled fallback: runs every 15 minutes via Laravel scheduler for all active profiles.

### 2.6 Delegation Reconciler

**`app/Support/Delegation/DelegationReconciler.php`**
- Modelled on ReconcileActiveRunsService.
- Finds DelegationTasks in active statuses where latest attempt's AgentJobRun is terminal but task hasn't transitioned.
- Finds DelegationGraphs where all tasks are terminal but graph hasn't transitioned.
- Fires missed events to trigger normal coordination flow.
- Scheduled: every 2 minutes via Laravel scheduler.

### 2.7 Graph Start and Cancel Logic

**Graph Start** (`POST /graphs/{id}/start`):
- Validates graph is in 'ready' status.
- Checks user's concurrent graph limit.
- Transitions graph to 'running'.
- Fires DelegationGraphStarted.

**Graph Cancel** (`POST /graphs/{id}/cancel`):
- Validates graph is in active status.
- Reads cancellation_policy ('kill' or 'drain').
- Kill: immediately stop all active attempts (via existing run stop logic), transition all non-terminal tasks to failed, transition graph to cancelled.
- Drain: stop dispatching new tasks, let active attempts finish, transition graph to cancelled when all active attempts complete.

### 2.8 Tests (Phase 2)

- `DelegationCoordinatorTest` — event flow from graph start to completion, multi-path DAG
- `AttemptSpawnerTest` — job creation, contract enforcement, run linkage
- `AttemptCompletionListenerTest` — run terminal triggers attempt completion
- `RecoveryEvaluatorTest` — retry, re-delegate, escalate, abort decision paths
- `VerificationPipelineTest` — all step types, short-circuit on failure, evidence persistence
- `DelegateeMetricsRecomputerTest` — window computation, throttle, scheduled fallback
- `DelegationReconcilerTest` — stuck task detection, missed event recovery
- `GraphStartCancelTest` — concurrent limits, kill vs drain, state transitions

---

## Phase 3 — Broadcast and UI

**Goal**: Real-time updates and operator-facing UI for delegation graph visualization, monitoring, and human approval.

### 3.1 Broadcast Events

Follow InterrogationSessionUpdated pattern: implement ShouldBroadcast, broadcastOn/broadcastAs/broadcastWith.

- `DelegationGraphBroadcast` — on PrivateChannel `delegation.graph.{graphId}`, events: graph status changes, task status changes, attempt status changes, verification results.
- `DelegationUserSummaryBroadcast` — on PrivateChannel `delegation.user.{userId}`, events: graph started, graph completed, graph failed, escalation needed.

**`app/Support/Delegation/DelegationBroadcastSubscriber.php`**
- Listens to internal domain events.
- Selectively dispatches broadcast events (curated subset, not all internal events).
- Enriches payload with display-friendly data (task names, delegatee names, progress percentages).

### 3.2 Channel Authorization

In `routes/channels.php`:
- `delegation.graph.{graphId}` — verify user owns the graph.
- `delegation.user.{userId}` — verify authenticated user matches.

### 3.3 Vue Components and Pages

All under `resources/js/Pages/Delegation/` and `resources/js/Components/Delegation/`:

- `DelegationIndex.vue` — list of user's delegation graphs with status badges, filters, create button.
- `DelegationGraphShow.vue` — main graph detail page with task list, dependency visualization, real-time status updates via Echo.
- `DelegationGraphCreate.vue` — form for creating graphs (JSON editor + linear-chain shorthand mode), inline and summary validation panel.
- `DelegationTaskDetail.vue` — task detail panel showing contract, assignment reason, attempts timeline, verification results, error info.
- `DelegationVerificationApproval.vue` — human approval UI for pending verification steps, approve/reject with notes.
- `DelegateeProfileIndex.vue` — list/manage delegatee profiles with capability badges and trust metrics.
- `DelegateeProfileForm.vue` — create/edit delegatee profile.
- `DelegationGraphVisualization.vue` — DAG visualization component showing nodes (tasks) with status colors, edges (dependencies), progress overlay. Uses a lightweight graph layout library.

### 3.4 Web Routes

Inertia routes for delegation pages, gated behind `delegation.ui_enabled` config check.

### 3.5 Tests (Phase 3)

- `DelegationBroadcastTest` — verify correct events broadcast on correct channels with correct payloads.
- `DelegationChannelAuthTest` — ownership verification for graph and user channels.
- `DelegationWebRouteAuthTest` — Inertia page access controls, feature flag gating.

---

## Phase 4 — Hardening and Observability

**Goal**: Production readiness — monitoring, alerting, performance tuning, documentation.

### 4.1 Observability

- Add structured logging to all coordination decisions (JSON logs with graph_id, task_id, decision, reason).
- Add Laravel Horizon dashboard tags for delegation queue jobs.
- Track key metrics via application logs (parseable by log aggregator):
  - `delegation.graph.started`, `delegation.graph.completed`, `delegation.graph.failed`
  - `delegation.task.assigned`, `delegation.task.retried`, `delegation.task.escalated`
  - `delegation.attempt.duration_ms`, `delegation.verification.duration_ms`
  - `delegation.recovery.decision` (retry/redelegate/escalate/abort)
  - `delegation.reconciler.stuck_tasks_found`

### 4.2 Audit Log Integration

Extend existing AuditLogger with new target_type values:
- `delegation_graph` — create, update, start, cancel, delete, restore
- `delegation_task` — assign, retry, escalate, abort
- `delegatee_profile` — create, update, delete, restore
- `delegation_verification` — resolve (human approval)

### 4.3 Performance Tuning

- Add database indexes if query analysis reveals slow paths.
- Optimize DelegationReconciler query to use chunk processing.
- Ensure metrics recomputation queries use proper window bounds with indexes.
- Load test with 25-task graphs to verify concurrency limits work correctly.

### 4.4 Feature Flag Removal

After validation in production:
- Remove `DelegationFeatureGate` middleware.
- Set `delegation.enabled` and `delegation.ui_enabled` to true by default.
- Clean up conditional checks.

### 4.5 Tests (Phase 4)

- `DelegationAuditLogTest` — verify all delegation actions produce audit entries
- End-to-end integration test: create graph via API, start execution, simulate run completion, verify coordination, verification, and graph completion
- Performance test: 25-task graph with mixed dependency patterns

---

## File Inventory Summary

### New Config Files (1)
- `config/delegation.php`

### New Migrations (10)
- `create_delegation_capabilities_table`
- `create_delegatee_profiles_table`
- `create_delegatee_capabilities_pivot_table`
- `create_delegatee_metrics_table`
- `create_delegation_graphs_table`
- `create_delegation_tasks_table`
- `create_delegation_task_dependencies_table`
- `create_delegation_attempts_table`
- `create_delegation_verification_results_table`
- `create_delegation_events_table`

### New Models (10)
- `DelegationCapability`, `DelegateeProfile`, `DelegateeCapabilityPivot`, `DelegateeMetric`, `DelegationGraph`, `DelegationTask`, `DelegationTaskDependency`, `DelegationAttempt`, `DelegationVerificationResult`, `DelegationEvent`

### New Support Services (14)
- `GraphStateTransitionService`, `TaskStateTransitionService`, `ContractValidator`, `DelegationGraphBuilder`, `ContractEnforcer`, `DelegateeAssigner`, `DelegationEventWriter`, `DelegationCoordinator`, `AttemptSpawner`, `AttemptCompletionListener`, `RecoveryEvaluator`, `VerificationPipeline`, `DelegateeMetricsRecomputer`, `DelegationReconciler`

### New Verification Steps (3)
- `AutomatedCheckStep`, `AiCriticStep`, `HumanApprovalStep`

### New Controllers (3)
- `DelegationGraphController`, `DelegationTaskController`, `DelegateeProfileController`

### New Policies (2)
- `DelegationGraphPolicy`, `DelegateeProfilePolicy`

### New Middleware (1)
- `DelegationFeatureGate`

### New Broadcast Events (2)
- `DelegationGraphBroadcast`, `DelegationUserSummaryBroadcast`

### New Vue Pages/Components (8)
- `DelegationIndex`, `DelegationGraphShow`, `DelegationGraphCreate`, `DelegationTaskDetail`, `DelegationVerificationApproval`, `DelegateeProfileIndex`, `DelegateeProfileForm`, `DelegationGraphVisualization`

### New Test Files (16+)
- Phase 1: 8 test files
- Phase 2: 8 test files
- Phase 3: 3 test files
- Phase 4: 2+ test files

### Modified Files
- `routes/api.php` — add delegation route group
- `routes/channels.php` — add delegation channel auth
- `routes/web.php` — add delegation Inertia routes
- `app/Console/Kernel.php` (or scheduler) — register reconciler and metrics recomputer schedules
- `app/Support/Agent/AuditLogger.php` — no changes needed (already supports arbitrary target_type strings)
- `database/seeders/DatabaseSeeder.php` — call DelegationCapabilitySeeder

## Sections

- Phase 1 — Backend Foundation: Config, 10 migrations, 10 models, core services (GraphStateTransitionService, TaskStateTransitionService, ContractValidator, DelegationGraphBuilder, ContractEnforcer, DelegateeAssigner, DelegationEventWriter), API CRUD endpoints, authorization policies, capability seeder, feature gate middleware, 8 test files
- Phase 2 — Execution Engine: 9 domain events, DelegationCoordinator event subscriber, AttemptSpawner (links to existing ExecuteAgentRunJob), AttemptCompletionListener, RecoveryEvaluator (retry/re-delegate/escalate/abort chain), VerificationPipeline with 3 pluggable step types, DelegateeMetricsRecomputer (event-triggered + scheduled), DelegationReconciler (2-min safety net), graph start/cancel logic (kill vs drain), human approval API endpoint, 8 test files
- Phase 3 — Broadcast and UI: 2 broadcast event classes, DelegationBroadcastSubscriber, channel auth for per-graph and per-user channels, 8 Vue pages/components (index, graph show, graph create with validation, task detail, verification approval, delegatee profiles, DAG visualization), Inertia web routes, 3 test files
- Phase 4 — Hardening and Observability: Structured logging for all coordination decisions, Horizon queue tags, audit log integration with new target_type values, performance tuning with load testing, feature flag removal, end-to-end integration test, 2+ test files


## Risks

- Contract enforcement intersection logic is complex — ContractEnforcer must correctly narrow PathPolicy/CommandPolicy/EnvPolicy without creating impossible constraints or accidentally widening permissions. Mitigation: exhaustive unit tests for boundary conditions, code review focused on security.
- Event-driven coordination can miss events if queue workers crash mid-processing. Mitigation: DelegationReconciler runs every 2 minutes as safety net (modelled on proven ReconcileActiveRunsService), all state transitions are atomic and idempotent.
- Linking DelegationAttempts to AgentJobRuns creates coupling between delegation and existing run lifecycle. If ExecuteAgentRunJob behavior changes, AttemptCompletionListener may break. Mitigation: AttemptCompletionListener has its own test suite, run lifecycle changes require delegation regression testing.
- DAG cycle detection and topological sort must be correct — a bug here could cause infinite loops or deadlocked graphs. Mitigation: well-tested DelegationGraphBuilder with explicit cycle detection using Kahn's algorithm, max task limit of 25 bounds complexity.
- Human approval verification step introduces blocking waits — if no human responds, tasks hang indefinitely. Mitigation: verification_timeout_seconds config (default 300s), RecoveryEvaluator treats timeout as failure and escalates.
- Metrics recomputation under high delegation volume could create database load spikes. Mitigation: 60-second throttle on event-triggered recomputation, pre-aggregated snapshots avoid per-request computation, scheduled fallback bounds maximum staleness.
- Migration of 10 new tables increases schema complexity. Mitigation: all tables follow established patterns, foreign keys with cascadeOnDelete keep referential integrity automatic, soft deletes preserve audit trail.
- Feature flag rollout means delegation code paths exist in production before full testing. Mitigation: DelegationFeatureGate middleware returns 404 when disabled, no delegation code executes in existing job/run paths unless explicitly invoked.


## Assumptions

- Existing Agent test suite (AgentApiWorkflowTest, AgentRunnerLifecycleTest, etc.) remains green — delegation is additive-only with no modifications to existing models, services, or migrations.
- Laravel Horizon queue infrastructure can handle additional delegation queue jobs alongside existing agent run and interrogation jobs without configuration changes (may need a dedicated queue name if volume is high).
- The existing ExecuteAgentRunJob and RunStateTransitionService are stable interfaces that delegation can depend on — their method signatures and behavior won't change during delegation development.
- Vue 3 + Inertia.js stack supports the DAG visualization requirements — a lightweight graph layout library (e.g., dagre or elkjs) can be integrated without major bundler changes.
- The 25-task maximum per graph is sufficient for MVP use cases and keeps topological sort and cycle detection performant without algorithmic optimization.
- Reverb/Echo WebSocket infrastructure can handle additional broadcast channels for delegation without scaling changes — delegation broadcast volume is bounded by graph concurrency limits.
- The seeded capability enum (code_generation, code_review, testing, documentation, refactoring, analysis, planning) covers MVP use cases — new capabilities can be added via migration or admin seeder without code changes.
- Database is MySQL/PostgreSQL — atomic whereIn/update pattern used by state transition services works correctly for single-row updates (same assumption as existing RunStateTransitionService).
- All delegation work runs on the same server as Agent (local-first architecture) — no distributed coordination, message brokers, or external service dependencies required for MVP.

