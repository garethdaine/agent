# Requirements Discovery Summary

Session: 2

# Delegation Engine v1 — Implementation Summary

## Overview

Complete the remaining ~80% of the Delegation Engine, which serves as the critical foundation for the Org Layer, Memory Architecture, and multi-agent coordination features. Implementation follows an interleaved model+service pairs approach organized by functional area (assignment, execution, verification).

## Data Model (9 Models)

### Assignment Functional Area
1. **DelegateeProfile** — SoftDeletes; runner_type (string 16), command_template (string 2000), working_directory (string 1024), env_json (json nullable), config_json (json nullable); belongsTo User; belongsToMany capabilities via pivot; hasOne metrics
2. **DelegateeCapabilityPivot** — pivot model with unique constraint on (delegatee_profile_id, delegation_capability_id)
3. **DelegateeMetric** — belongsTo profile (unique FK); window_24h_json, window_7d_json; last_recomputed_at (dedicated table for normalized storage and easier querying)

### Execution Functional Area
4. **DelegationGraph** — SoftDeletes; statuses: draft/validating/ready/running/succeeded/failed/partial/cancelled; ACTIVE_STATUSES = [running]; TERMINAL_STATUSES = [succeeded, failed, partial, cancelled]; belongsTo User; hasMany tasks/events; cancellation_policy (graceful with 15-minute timeout default), max_parallel_tasks (default 5, ceiling 15), metadata_json, error_code, error_summary, started_at, finished_at
5. **DelegationTask** — statuses: pending/blocked/ready/assigned/running/verifying/succeeded/failed/cancelled; belongsTo graph; hasMany attempts/dependencies/dependents/verificationResults; belongsTo assignedProfile (nullable); sequence_order, contract_json, assignment_reason_json, metadata_json
6. **DelegationTaskDependency** — belongsTo task + dependsOnTask; unique constraint on (task_id, depends_on_task_id)
7. **DelegationAttempt** — statuses: running/succeeded/failed; belongsTo task/profile; belongsTo agentJobRun (nullable, nullOnDelete); attempt_number, duration_ms, error_code, error_summary

### Verification Functional Area
8. **DelegationVerificationResult** — step_type (automated_check/ai_critic/human_approval), step_order, verdict (passed/failed/skipped/pending), evidence_json; belongsTo task/attempt
9. **DelegationEvent** — event_type (string 64), auto-incrementing sequence per graph, payload_json, event_ts; belongsTo graph/task(nullable)

## Core Services (17 Services)

### State Management
- **GraphStateTransitionService** — atomic status transitions using whereKey($id)->whereIn('status', $from)->update($payload) pattern
- **TaskStateTransitionService** — same atomic pattern for DelegationTask

### Graph Building & Validation
- **DelegationGraphBuilder** — accepts both DAG JSON and linear-chain shorthand formats; creates graph+tasks+dependencies in DB transaction; Kahn's algorithm cycle detection; max 25 tasks; auto-assigns sequence_order from topological depth
- **ContractValidator** — validates contract_json: required_capability references active capability; authority_scope.max_runtime_seconds ≤ 86400; criticality enum; time_constraints cap enforcement; verification_strategy with valid check profiles; prompt or task_markdown_path present
- **ContractEnforcer** — intersects authority_scope with PathPolicy/EnvPolicy boundaries; caps max_runtime_seconds; returns narrowed config with warnings in task metadata when scope is reduced

### Assignment & Execution
- **DelegateeAssigner** — matches required_capability; ranks by success_rate from 24h sliding window; tiebreaks by lowest current load (fewest active attempts); when no match found, task enters 'blocked' status for reconciler retry
- **AttemptSpawner** — creates DelegationAttempt; uses ContractEnforcer for narrowed config; creates transient AgentJob from DelegateeProfile with 'delegation' source tag; dispatches ExecuteAgentRunJob on 'agent' queue via Bus::chain with callback on 'delegation' queue; links attempt to AgentJobRun
- **DelegationCoordinator** — Laravel Event/Listener for happy-path flow: GraphStarted → root tasks ready → assign → spawn → AttemptFinished(success) → verify → TaskVerified → complete → check graph completion → fire next ready tasks

### Verification Pipeline (All 3 Required for v1)
- **VerificationPipeline** — executes ordered steps from verification_strategy; tracks current step for resumability; short-circuits on first failure; resumes from next step on DelegationTaskVerified
- **AutomatedCheckStep** — resolves check profile from config; executes commands sequentially; captures stdout/stderr as evidence_json
- **AiCriticStep** — spawns dedicated AgentJobRun with review prompt (layered: system default with optional per-task override via verification_strategy.ai_critic.prompt_template); dispatches via Bus::chain callback; returns 'pending' immediately; callback writes result and fires DelegationTaskVerified; evidence capture uses hybrid format (attempt JSON parse with verdict/issues/confidence fields, fall back to raw text if parsing fails)
- **HumanApprovalStep** — creates pending DelegationVerificationResult; returns immediately; resolution via API endpoint fires DelegationTaskVerified; reconciler enforces 4-hour default timeout

### Recovery & Metrics
- **RecoveryHandler** — separate listener for failed attempts; heuristic classification (timed_out=transient, skipped=non-transient, failed/killed=error_code config lookup); decision chain: 2 retries on same delegatee → 1 re-delegate attempt → escalate → abort; escalation notifications sent to graph owner only
- **DelegateeMetricsRecomputer** — event-triggered with 60s cache-lock throttle; scheduled fallback every 15 min; computes sliding window stats (24h, 7d); stores in dedicated DelegateeMetric table

### Supporting
- **DelegationEventWriter** — follows RunEventWriter pattern; auto-increments sequence per graph
- **DelegationReconciler** — scheduled every 2 min; detects stuck tasks, missed completions, expired human approvals (4-hour timeout), blocked tasks awaiting delegatee matching; fires missed events
- **DelegationBroadcastSubscriber** — queued broadcast dispatch (implements ShouldQueue) with enriched payloads on per-graph and per-user private channels

## Controllers & API

### Controllers (3)
- **DelegationGraphController** — CRUD + restore/validate/start/cancel/clone + events listing
- **DelegationTaskController** — list/show tasks with attempts and verification results; POST verification resolution
- **DelegateeProfileController** — CRUD + soft delete/restore

### Routing
- apiResource routing for standard CRUD operations
- Explicit route definitions for custom actions: start, cancel, clone, validate, restore

### API Endpoints (under agent/api/v1/delegation/, gated by DelegationFeatureGate, auth:sanctum)
- Graphs: GET/POST /graphs, GET/PUT/DELETE /graphs/{id}, POST restore/validate/start/cancel/clone
- Tasks: GET /graphs/{graphId}/tasks, GET /graphs/{graphId}/tasks/{taskId}, POST verification resolve
- Profiles: GET/POST /delegatee-profiles, GET/PUT/DELETE /delegatee-profiles/{id}
- Events: GET /graphs/{id}/events
- Clone accepts optional mode: 'all' | 'failed_subtree' (retains original history as metadata reference)

### Policies (2)
- DelegationGraphPolicy — ownership + state guards
- DelegateeProfilePolicy — ownership

## Frontend (7 Vue Pages — Visualization Deferred)

1. **DelegationIndex** — graph listing with status filters
2. **DelegationGraphShow** — graph detail with list-based task status overview
3. **DelegationGraphCreate** — JSON editor + linear-chain mode with inline validation
4. **DelegationTaskDetail** — task detail with attempts and verification history
5. **DelegationVerificationApproval** — human approval UI
6. **DelegateeProfileIndex** — profile listing
7. **DelegateeProfileForm** — profile CRUD form

**Deferred to follow-up:** DelegationGraphVisualization with dagre DAG layout

## Configuration

### Seeded Capabilities (6)
code_execution, review, testing, documentation, deployment, monitoring

### Config Values (config/delegation.php)
- max_tasks_per_graph: 25
- max_parallel_tasks_default: 5
- max_parallel_tasks_ceiling: 15
- human_approval_timeout_hours: 4
- graceful_cancellation_timeout_minutes: 15
- retry_same_delegatee_limit: 2
- redelegate_limit: 1
- metrics_recompute_throttle_seconds: 60
- metrics_recompute_schedule_minutes: 15
- reconciler_schedule_minutes: 2
- ai_critic_default_prompt_template: (system default review prompt)

### Horizon
- Add supervisor-delegation: queue ['delegation'], timeout 900, maxProcesses env('HORIZON_DELEGATION_MAX_PROCESSES', 2), auto balance

### Scheduler
- DelegationReconciler: every 2 minutes
- DelegateeMetricsRecomputer: every 15 minutes

### Feature Flags
- delegation.enabled (default false)
- delegation.ui_enabled (default false)

## Broadcast Events
- **DelegationGraphBroadcast** — PrivateChannel `delegation.graph.{graphId}` (implements ShouldQueue)
- **DelegationUserSummaryBroadcast** — PrivateChannel `delegation.user.{userId}` (implements ShouldQueue)
- Channel auth in routes/channels.php

## Cancellation Behavior
Graceful with timeout: when graph is cancelled, wait up to 15 minutes for running tasks to complete naturally. After timeout, force-kill uses hybrid mechanism: update AgentJobRun status to 'cancelled' AND send process signal via Horizon if run is currently executing.

## Escalation Behavior
When RecoveryHandler exhausts all recovery options (2 retries + 1 re-delegate), escalation notification is sent to the graph owner (the user who created the DelegationGraph). Task transitions to failed status with escalation metadata.

## Goals

- Complete 9 remaining Eloquent models with relationships, casts, scopes, and SoftDeletes where specified
- Build state transition services (GraphStateTransitionService, TaskStateTransitionService) using atomic whereKey/whereIn/update pattern
- Build DelegationGraphBuilder supporting both DAG JSON and linear-chain shorthand input formats with Kahn's algorithm cycle detection
- Build ContractValidator and ContractEnforcer with auto-narrowing of authority scope and warning injection into task metadata
- Build DelegateeAssigner with 24h sliding window success_rate ranking and load-based tiebreaking
- Build AttemptSpawner with Bus::chain completion callbacks and delegation source tagging on spawned AgentJobRuns
- Build DelegationCoordinator as Laravel Event/Listener subscriber for happy-path orchestration flow
- Build full VerificationPipeline with all three step types: AutomatedCheckStep, AiCriticStep (with layered prompt template configuration), HumanApprovalStep
- Build RecoveryHandler with 2 retry → 1 re-delegate → escalate decision chain, heuristic error classification, and graph-owner escalation notifications
- Build DelegateeMetricsRecomputer with event-triggered recomputation, 60s cache-lock throttle, and 15-min scheduled fallback
- Build DelegationReconciler for stuck task detection, blocked task reassignment, expired human approval handling, and graceful cancellation timeout enforcement
- Build DelegationEventWriter and DelegationBroadcastSubscriber with queued broadcasts on private channels
- Build 3 controllers with apiResource routing for CRUD and explicit routes for custom actions
- Build 2 authorization policies (DelegationGraphPolicy, DelegateeProfilePolicy)
- Build 7 Vue pages for delegation management (defer DAG visualization to follow-up)
- Configure Horizon supervisor-delegation queue and scheduler registrations
- Run DelegationCapabilitySeeder with 6 standard capabilities
- Implement hybrid force-kill mechanism (status update + process signal) for graceful cancellation timeout expiry


## Constraints

- All implementations must be additive-only — no modifications to existing models, services, or migrations
- Existing test suite must remain green throughout implementation
- Maximum 25 tasks per DelegationGraph enforced by DelegationGraphBuilder
- Maximum 15 parallel tasks ceiling enforced system-wide regardless of graph configuration
- Authority scopes only narrow, never widen — ContractEnforcer intersects with PathPolicy/EnvPolicy boundaries
- Human approval timeout fixed at 4 hours, enforced by reconciler
- Graceful cancellation timeout fixed at 15 minutes before force-kill
- Recovery chain fixed at 2 retries same delegatee → 1 re-delegate → escalate (no per-task configuration)
- Delegation-spawned AgentJobRuns must be tagged with 'delegation' source for filtering in main jobs list
- Broadcast events must implement ShouldQueue to avoid blocking coordination flow
- Clone operation must retain original attempt history as metadata reference, not copy execution state
- Blocked tasks (no matching delegatee) must remain in blocked status and retry via reconciler, not fail immediately
- All API endpoints gated by DelegationFeatureGate middleware and auth:sanctum
- DAG visualization deferred — v1 uses list-based task view only
- Escalation notifications go to graph owner only (no configurable targets in v1)
- AiCriticStep prompt templates use layered configuration: system default with optional per-task override
- AiCriticStep evidence uses hybrid format: attempt JSON parse, fall back to raw text


## Acceptance Criteria

- DelegationGraph can be created from both DAG JSON and linear-chain shorthand formats
- DelegationGraphBuilder rejects graphs with cycles using Kahn's algorithm
- DelegationGraphBuilder rejects graphs exceeding 25 tasks
- Graphs respect max_parallel_tasks (default 5, ceiling 15) during execution
- DelegateeAssigner correctly matches capabilities and ranks by 24h success_rate with load tiebreaker
- Tasks without matching delegatee enter blocked status and are retried by reconciler
- ContractEnforcer narrows authority scope to intersection with PathPolicy/EnvPolicy and injects warnings into task metadata
- AutomatedCheckStep executes commands and captures stdout/stderr as evidence_json
- AiCriticStep spawns AgentJobRun with review prompt (system default or per-task override) and handles async callback
- AiCriticStep evidence capture attempts JSON parse for verdict/issues/confidence fields, falls back to raw text on parse failure
- HumanApprovalStep creates pending verification result and is resolved via API endpoint
- Human approvals expire after 4 hours and trigger escalation via reconciler
- RecoveryHandler executes 2 retry → 1 re-delegate → escalate chain on task failure
- Escalation notifications are sent to graph owner only
- Delegation-spawned runs appear in main jobs list with delegation source tag
- Clone operation with failed_subtree mode retains original history as metadata
- Graceful cancellation waits up to 15 minutes, then uses hybrid force-kill (status update + process signal)
- DelegateeMetrics are recomputed on events with 60s throttle and every 15 minutes via scheduler
- DelegationReconciler runs every 2 minutes and detects stuck tasks, missed completions, expired approvals, blocked tasks
- Broadcast events are queued and delivered to per-graph and per-user private channels
- All 6 seeded capabilities (code_execution, review, testing, documentation, deployment, monitoring) are created by seeder
- All API endpoints require authentication and delegation feature flag
- All existing tests remain green after implementation

