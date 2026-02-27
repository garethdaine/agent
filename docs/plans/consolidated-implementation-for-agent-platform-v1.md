# Implementation Plan

Derived from discovery session 2.

# Delegation Engine v1 — Implementation Plan

## Phase 1: Models & State Transitions

### 1.1 Assignment Area Models

**DelegateeProfile Model**

- File: `app/Models/DelegateeProfile.php`
- SoftDeletes trait; guarded = []
- Casts: `env_json` → array, `config_json` → array, `is_active` → boolean
- Relationships: `belongsTo User`, `belongsToMany DelegationCapability` via `delegatee_capabilities_pivot` table, `hasOne DelegateeMetric`
- Scopes: `scopeActive(Builder $query)` filters `is_active = true AND deleted_at IS NULL`
- Accessor: `capabilities` relationship uses pivot table with correct FK names

**DelegateeCapabilityPivot Model**

- File: `app/Models/DelegateeCapabilityPivot.php`
- Extends `Illuminate\Database\Eloquent\Relations\Pivot`
- Table: `delegatee_capabilities_pivot`
- Relationships: `belongsTo DelegateeProfile`, `belongsTo DelegationCapability`

**DelegateeMetric Model**

- File: `app/Models/DelegateeMetric.php`
- Casts: `window_24h_json` → array, `window_7d_json` → array, `last_recomputed_at` → datetime
- Relationships: `belongsTo DelegateeProfile`

### 1.2 Execution Area Models

**DelegationGraph Model**

- File: `app/Models/DelegationGraph.php`
- SoftDeletes trait; guarded = []
- Constants: `ACTIVE_STATUSES = ['running']`, `TERMINAL_STATUSES = ['succeeded', 'failed', 'partial', 'cancelled']`, `STATUS_DRAFT = 'draft'`, `STATUS_VALIDATING = 'validating'`, `STATUS_READY = 'ready'`, `STATUS_RUNNING = 'running'`, etc.
- Casts: `metadata_json` → array, `started_at` → datetime, `finished_at` → datetime, `max_parallel_tasks` → integer
- Relationships: `belongsTo User`, `hasMany DelegationTask`, `hasMany DelegationEvent`
- Scopes: `scopeActive(Builder $query)` filters `whereIn('status', self::ACTIVE_STATUSES)`, `scopeTerminal(Builder $query)` filters `whereIn('status', self::TERMINAL_STATUSES)`

**DelegationTask Model**

- File: `app/Models/DelegationTask.php`
- Constants: `STATUS_PENDING`, `STATUS_BLOCKED`, `STATUS_READY`, `STATUS_ASSIGNED`, `STATUS_RUNNING`, `STATUS_VERIFYING`, `STATUS_SUCCEEDED`, `STATUS_FAILED`, `STATUS_CANCELLED`
- Casts: `contract_json` → array, `assignment_reason_json` → array, `metadata_json` → array, `started_at` → datetime, `finished_at` → datetime, `sequence_order` → integer
- Relationships: `belongsTo DelegationGraph`, `hasMany DelegationAttempt`, `hasMany DelegationVerificationResult`, `belongsTo DelegateeProfile` (as `assignedProfile`, nullable), dependencies/dependents via pivot
- Method: `dependencies()` returns `belongsToMany(DelegationTask::class, 'delegation_task_dependencies', 'task_id', 'depends_on_task_id')`
- Method: `dependents()` returns `belongsToMany(DelegationTask::class, 'delegation_task_dependencies', 'depends_on_task_id', 'task_id')`

**DelegationTaskDependency Model**

- File: `app/Models/DelegationTaskDependency.php`
- Table: `delegation_task_dependencies`
- Relationships: `belongsTo DelegationTask` (as `task`), `belongsTo DelegationTask` (as `dependsOnTask`)

**DelegationAttempt Model**

- File: `app/Models/DelegationAttempt.php`
- Constants: `STATUS_RUNNING`, `STATUS_SUCCEEDED`, `STATUS_FAILED`
- Casts: `started_at` → datetime, `finished_at` → datetime, `duration_ms` → integer, `attempt_number` → integer, `metadata_json` → array
- Relationships: `belongsTo DelegationTask`, `belongsTo DelegateeProfile`, `belongsTo AgentJobRun` (nullable)

### 1.3 Verification Area Models

**DelegationVerificationResult Model**

- File: `app/Models/DelegationVerificationResult.php`
- Constants: `STEP_TYPE_AUTOMATED_CHECK`, `STEP_TYPE_AI_CRITIC`, `STEP_TYPE_HUMAN_APPROVAL`; `VERDICT_PASSED`, `VERDICT_FAILED`, `VERDICT_SKIPPED`, `VERDICT_PENDING`
- Casts: `evidence_json` → array, `step_order` → integer, `started_at` → datetime, `finished_at` → datetime
- Relationships: `belongsTo DelegationTask`, `belongsTo DelegationAttempt` (nullable)

**DelegationEvent Model**

- File: `app/Models/DelegationEvent.php`
- Casts: `payload_json` → array, `event_ts` → datetime, `sequence` → integer
- Relationships: `belongsTo DelegationGraph`, `belongsTo DelegationTask` (nullable)

### 1.4 State Transition Services

**GraphStateTransitionService**

- File: `app/Support/Delegation/GraphStateTransitionService.php`
- Method: `transition(int $graphId, array $fromStatuses, string $toStatus, array $attributes = []): bool`
- Pattern: Mirrors `RunStateTransitionService` — uses `DelegationGraph::query()->whereKey($graphId)->whereIn('status', $fromStatuses)->update($payload)` with merged attributes + timestamp
- Returns true if exactly 1 row updated

**TaskStateTransitionService**

- File: `app/Support/Delegation/TaskStateTransitionService.php`
- Method: `transition(int $taskId, array $fromStatuses, string $toStatus, array $attributes = []): bool`
- Same atomic pattern for `DelegationTask`

## Phase 2: Graph Building & Validation

### 2.1 DelegationGraphBuilder Service

**File:** `app/Support/Delegation/DelegationGraphBuilder.php`

**Input Formats:**

- DAG JSON: `{"tasks": [{"name": "...", "contract": {...}, "depends_on": ["task_name_1"]}]}`
- Linear-chain shorthand: `[{"name": "...", "contract": {...}}, ...]` (implicit sequential dependencies)

**Build Flow:**

1. Parse input, detect format (DAG vs linear-chain)
2. Validate task count ≤ 25 (config: `delegation.max_tasks_per_graph`)
3. Build adjacency list from dependencies
4. Run Kahn's algorithm for cycle detection; throw `DelegationGraphCycleException` if cycle detected
5. Assign `sequence_order` from topological depth (0 for root tasks, max dependency depth otherwise)
6. DB transaction: create `DelegationGraph`, create `DelegationTask` records, create `DelegationTaskDependency` records
7. Set root tasks (no dependencies) to `STATUS_READY`, others to `STATUS_PENDING`
8. Return created `DelegationGraph` with relationships loaded

**Kahn's Algorithm Implementation:**

- Build in-degree map from adjacency list
- Queue all nodes with in-degree 0
- Process queue: for each node, decrement in-degree of dependents, add newly zero-degree nodes to queue
- If processed nodes < total nodes → cycle exists

### 2.2 ContractValidator Service

**File:** `app/Support/Delegation/ContractValidator.php`

**Method:** `validate(array $contractJson): ValidationResult`

**Validation Rules:**

1. `required_capability` must reference an active `DelegationCapability` (by slug or ID)
2. `authority_scope.max_runtime_seconds` ≤ 86400 (24 hours)
3. `criticality` must be valid enum: `low`, `medium`, `high`, `critical`
4. `time_constraints.deadline_ts` if present must be future timestamp
5. `verification_strategy` if present must have valid step configurations:
  - `automated_check`: requires `check_profile` reference that exists in `config('delegation.check_profiles')`
  - `ai_critic`: optional `prompt_template` override
  - `human_approval`: requires `timeout_hours` ≤ 4
6. Either `prompt` or `task_markdown_path` must be present (not both)
7. If `task_markdown_path` present, must be within `config('agent.allowed_task_markdown_bases')`

**Return:** `ValidationResult` DTO with `isValid()`, `errors()`, `warnings()`

### 2.3 ContractEnforcer Service

**File:** `app/Support/Delegation/ContractEnforcer.php`

**Method:** `enforce(array $contractJson, DelegateeProfile $profile): EnforcementResult`

**Enforcement Logic:**

1. Intersect `authority_scope.allowed_paths` with `PathPolicy` boundaries
2. Intersect `authority_scope.env_whitelist` with `EnvPolicy` boundaries
3. Cap `authority_scope.max_runtime_seconds` to profile limit or system ceiling
4. Narrow any scope dimension that exceeds policy bounds
5. Record warnings in `EnforcementResult` for any narrowed scopes

**Return:** `EnforcementResult` with `narrowedConfig()`, `warnings()`, `wasNarrowed()`

## Phase 3: Assignment & Execution Core

### 3.1 DelegateeAssigner Service

**File:** `app/Support/Delegation/DelegateeAssigner.php`

**Method:** `assign(DelegationTask $task): ?AssignmentResult`

**Assignment Algorithm:**

1. Extract `required_capability` from task's `contract_json`
2. Query active `DelegateeProfile` records for current user with matching capability via pivot
3. For each candidate, fetch `DelegateeMetric` 24h window stats
4. Calculate ranking score: `success_rate_24h` (higher is better)
5. Tiebreaker: `current_load` = count of `DelegationAttempt` in `STATUS_RUNNING` for that profile (lower is better)
6. Return top candidate with assignment reasoning JSON

**When No Match:**

- Return null; caller transitions task to `STATUS_BLOCKED`
- Task will be retried by `DelegationReconciler`

### 3.2 AttemptSpawner Service

**File:** `app/Support/Delegation/AttemptSpawner.php`

**Method:** `spawn(DelegationTask $task, DelegateeProfile $profile): DelegationAttempt`

**Spawn Flow:**

1. Use `ContractEnforcer` to get narrowed config
2. Inject scope warnings into task's `metadata_json`
3. Create `DelegationAttempt` record with `STATUS_RUNNING`, `attempt_number` incremented
4. Create transient `AgentJob` from `DelegateeProfile` config:
  - Set `source` metadata to `'delegation'` for filtering
  - Copy `runner_type`, `command_template`, `working_directory`, `env_json`
5. Create `AgentJobRun` linked to the transient job
6. Link attempt to the `AgentJobRun`
7. Dispatch `ExecuteAgentRunJob` via `Bus::chain` with completion callback on `'delegation'` queue:
  ```php
   Bus::chain([
       new ExecuteAgentRunJob($run),
       new DelegationAttemptCompletedJob($attempt->id),
   ])->onQueue('agent')->dispatch();
  ```
8. Return created attempt

### 3.3 DelegationCoordinator Event Subscriber

**File:** `app/Listeners/DelegationCoordinator.php`

**Implements:** Laravel event subscriber pattern via `EventServiceProvider`

**Subscribed Events:**

- `DelegationGraphStarted` → find ready root tasks → assign → spawn
- `DelegationAttemptCompleted` (success) → trigger verification pipeline
- `DelegationTaskVerified` (passed) → mark task succeeded → check graph completion → fire ready tasks
- `DelegationTaskVerified` (failed) → delegate to `RecoveryHandler`
- `DelegationGraphCompleted` → record final timestamp, emit completion event

**Happy Path Flow:**

1. On `GraphStarted`: query tasks with `status = ready AND sequence_order = 0`, for each: assign → spawn
2. On `AttemptCompleted(success)`: transition task to `STATUS_VERIFYING`, run `VerificationPipeline`
3. On `TaskVerified(passed)`: transition task to `STATUS_SUCCEEDED`, update graph progress, check for newly ready tasks (dependencies satisfied), dispatch next batch respecting `max_parallel_tasks`
4. On all tasks completed: transition graph to terminal status based on task outcomes

## Phase 4: Verification Pipeline

### 4.1 VerificationPipeline Service

**File:** `app/Support/Delegation/VerificationPipeline.php`

**Method:** `execute(DelegationTask $task, DelegationAttempt $attempt): void`

**Pipeline Execution:**

1. Parse `verification_strategy` from task's `contract_json`
2. If no strategy defined → pass immediately, fire `DelegationTaskVerified(passed)`
3. Get ordered steps from strategy (by `step_order`)
4. Track `current_step` in task metadata for resumability
5. Execute steps in order:
  - `automated_check` → `AutomatedCheckStep::execute()`
  - `ai_critic` → `AiCriticStep::execute()`
  - `human_approval` → `HumanApprovalStep::execute()`
6. On first failure → short-circuit, fire `DelegationTaskVerified(failed)`
7. On `pending` (async steps) → store state, await `DelegationTaskVerified` event to resume
8. On all passed → fire `DelegationTaskVerified(passed)`

### 4.2 AutomatedCheckStep

**File:** `app/Support/Delegation/Verification/AutomatedCheckStep.php`

**Method:** `execute(DelegationTask $task, DelegationAttempt $attempt, array $stepConfig): VerificationStepResult`

**Execution:**

1. Resolve `check_profile` from `config('delegation.check_profiles')`
2. Execute commands sequentially in task's working directory
3. Capture stdout/stderr as `evidence_json`
4. Any non-zero exit → return `VerificationStepResult::failed()`
5. All pass → return `VerificationStepResult::passed()`
6. Create `DelegationVerificationResult` record with evidence

### 4.3 AiCriticStep

**File:** `app/Support/Delegation/Verification/AiCriticStep.php`

**Method:** `execute(DelegationTask $task, DelegationAttempt $attempt, array $stepConfig): VerificationStepResult`

**Execution:**

1. Build review prompt:
  - Base: `config('delegation.ai_critic_default_prompt_template')`
  - Override: `$stepConfig['prompt_template']` if present
2. Substitute task context into prompt
3. Create transient `AgentJob` for review
4. Create `AgentJobRun` for review execution
5. Dispatch via `Bus::chain` with callback `AiCriticCompletedJob`
6. Return `VerificationStepResult::pending()`

**Callback Handler (`AiCriticCompletedJob`):**

1. Fetch review run output
2. Parse evidence (hybrid: attempt JSON parse for `verdict`, `issues`, `confidence` fields; fall back to raw text)
3. Create `DelegationVerificationResult` with evidence
4. Fire `DelegationTaskVerified` event to resume pipeline

### 4.4 HumanApprovalStep

**File:** `app/Support/Delegation/Verification/HumanApprovalStep.php`

**Method:** `execute(DelegationTask $task, DelegationAttempt $attempt, array $stepConfig): VerificationStepResult`

**Execution:**

1. Create `DelegationVerificationResult` with `VERDICT_PENDING`
2. Record `expires_at` = now + 4 hours (config: `delegation.human_approval_timeout_hours`)
3. Return `VerificationStepResult::pending()`

**Resolution:** API endpoint resolves pending approval, fires `DelegationTaskVerified`

## Phase 5: Recovery & Metrics

### 5.1 RecoveryHandler Listener

**File:** `app/Listeners/DelegationRecoveryHandler.php`

**Subscribed Events:** `DelegationAttemptCompleted` (with failure status)

**Recovery Chain:**

1. Classify error: `timed_out` → transient; `skipped` → non-transient; `failed/killed` → lookup in `config('delegation.transient_error_codes')` / `config('delegation.non_transient_error_codes')`
2. Count previous attempts on same delegatee for this task
3. Decision chain:
  - If attempts on same delegatee < 2 AND error is transient → retry (create new attempt, spawn)
  - Else if re-delegations < 1 → re-delegate (assign to different delegatee, spawn)
  - Else if criticality < threshold → escalate (notify graph owner, mark task failed with escalation metadata)
  - Else → abort (mark task failed, potentially fail graph)

**Escalation Notification:**

- Send to graph owner only (via existing notification infrastructure or event)
- Record in task metadata: `escalation_notified_at`, `escalation_reason`

### 5.2 DelegateeMetricsRecomputer Service

**File:** `app/Support/Delegation/DelegateeMetricsRecomputer.php`

**Event Listener:** Triggered by `DelegationAttemptCompleted`

**Throttling:** Use cache lock with 60s TTL (`config('delegation.metrics_event_throttle_seconds')`)

**Scheduled Job:** Also runs every 15 minutes (`config('delegation.metrics_recomputation_interval_minutes')`)

**Computation:**

1. Query `DelegationAttempt` records for profile in last 24h / 7d windows
2. Calculate: `total_attempts`, `successful_attempts`, `success_rate`, `avg_duration_ms`
3. Upsert `DelegateeMetric` record

### 5.3 DelegationReconciler Command

**File:** `app/Console/Commands/DelegationReconciler.php`

**Schedule:** Every 2 minutes (`config('delegation.reconciler_interval_minutes')`)

**Reconciliation Tasks:**

1. **Stuck running graphs:** Graphs in `STATUS_RUNNING` with no running tasks and incomplete completion → fire completion events
2. **Stuck running tasks:** Tasks in `STATUS_RUNNING` past timeout with no active attempt → investigate or fail
3. **Expired human approvals:** `DelegationVerificationResult` with `VERDICT_PENDING` and `expires_at < now()` → mark failed, fire `DelegationTaskVerified(failed)`
4. **Blocked tasks awaiting delegatee:** Tasks in `STATUS_BLOCKED` → retry assignment via `DelegateeAssigner`
5. **Graceful cancellation timeout:** Graphs in cancelled state with running tasks past 15-minute window → force-kill via `HybridForceKill`

**HybridForceKill Logic:**

1. Update linked `AgentJobRun` status to `cancelled`
2. If run has active process, send signal via Horizon worker (if applicable)

## Phase 6: Event Writing & Broadcasting

### 6.1 DelegationEventWriter Service

**File:** `app/Support/Delegation/DelegationEventWriter.php`

**Pattern:** Mirrors `RunEventWriter`

**Method:** `write(DelegationGraph $graph, string $eventType, array $payload, ?DelegationTask $task = null): void`

**Implementation:**

1. Auto-increment sequence per graph (query max sequence + 1)
2. Create `DelegationEvent` record with `event_ts = now()`
3. Fire internal domain event for broadcast subscriber

### 6.2 DelegationBroadcastSubscriber Listener

**File:** `app/Listeners/DelegationBroadcastSubscriber.php`

**Implements:** `ShouldQueue` for non-blocking broadcast

**Queue:** `delegation`

**Subscribed Events:** All delegation domain events

**Broadcast Targets:**

1. Per-graph channel: `PrivateChannel('delegation.graph.{graphId}')`
2. Per-user summary channel: `PrivateChannel('delegation.user.{userId}')`

**Broadcast Events:**

- `DelegationGraphBroadcast` → implements `ShouldBroadcast`, enriched payload with graph status, task counts
- `DelegationUserSummaryBroadcast` → aggregated status update

### 6.3 Channel Authorization

**File:** `routes/channels.php` (additions)

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

## Phase 7: Controllers & API

### 7.1 DelegationGraphController

**File:** `app/Http/Controllers/Api/V1/DelegationGraphController.php`

**CRUD Endpoints:**

- `GET /graphs` → index with status filters, pagination
- `POST /graphs` → store (validates via `ContractValidator`, builds via `DelegationGraphBuilder`)
- `GET /graphs/{id}` → show with tasks, attempts, events
- `PUT /graphs/{id}` → update (only draft status)
- `DELETE /graphs/{id}` → soft delete (only terminal status)

**Action Endpoints:**

- `POST /graphs/{id}/restore` → restore soft-deleted graph
- `POST /graphs/{id}/validate` → dry-run validation without persistence
- `POST /graphs/{id}/start` → transition `ready → running`, fire `DelegationGraphStarted`
- `POST /graphs/{id}/cancel` → initiate graceful cancellation
- `POST /graphs/{id}/clone` → clone graph with mode: `all` (full clone) or `failed_subtree` (retry failed branch); retains original history as metadata reference

**Events Endpoint:**

- `GET /graphs/{id}/events` → paginated `DelegationEvent` listing

### 7.2 DelegationTaskController

**File:** `app/Http/Controllers/Api/V1/DelegationTaskController.php`

**Endpoints:**

- `GET /graphs/{graphId}/tasks` → list tasks with attempts and verification results
- `GET /graphs/{graphId}/tasks/{taskId}` → detailed task view
- `POST /graphs/{graphId}/tasks/{taskId}/verification/resolve` → resolve pending human approval

**Verification Resolution:**

- Validate user owns graph
- Validate verification result is pending
- Accept `verdict: passed | failed` and `evidence_json`
- Update `DelegationVerificationResult`, fire `DelegationTaskVerified`

### 7.3 DelegateeProfileController

**File:** `app/Http/Controllers/Api/V1/DelegateeProfileController.php`

**CRUD Endpoints:**

- `GET /delegatee-profiles` → index with capability filters
- `POST /delegatee-profiles` → store with capability bindings
- `GET /delegatee-profiles/{id}` → show with metrics
- `PUT /delegatee-profiles/{id}` → update
- `DELETE /delegatee-profiles/{id}` → soft delete
- `POST /delegatee-profiles/{id}/restore` → restore

### 7.4 Route Registration

**File:** `routes/api.php` (additions within auth group, under v1 prefix)

```php
Route::prefix('delegation')->middleware(['delegation'])->group(function () {
    // Graphs
    Route::get('/graphs', [DelegationGraphController::class, 'index']);
    Route::post('/graphs', [DelegationGraphController::class, 'store'])->middleware('throttle:agent-mutations');
    Route::get('/graphs/{id}', [DelegationGraphController::class, 'show']);
    Route::put('/graphs/{id}', [DelegationGraphController::class, 'update'])->middleware('throttle:agent-mutations');
    Route::delete('/graphs/{id}', [DelegationGraphController::class, 'destroy'])->middleware('throttle:agent-mutations');
    Route::post('/graphs/{id}/restore', [DelegationGraphController::class, 'restore'])->middleware('throttle:agent-mutations');
    Route::post('/graphs/{id}/validate', [DelegationGraphController::class, 'validate']);
    Route::post('/graphs/{id}/start', [DelegationGraphController::class, 'start'])->middleware('throttle:agent-mutations');
    Route::post('/graphs/{id}/cancel', [DelegationGraphController::class, 'cancel'])->middleware('throttle:agent-mutations');
    Route::post('/graphs/{id}/clone', [DelegationGraphController::class, 'clone'])->middleware('throttle:agent-mutations');
    Route::get('/graphs/{id}/events', [DelegationGraphController::class, 'events']);

    // Tasks
    Route::get('/graphs/{graphId}/tasks', [DelegationTaskController::class, 'index']);
    Route::get('/graphs/{graphId}/tasks/{taskId}', [DelegationTaskController::class, 'show']);
    Route::post('/graphs/{graphId}/tasks/{taskId}/verification/resolve', [DelegationTaskController::class, 'resolveVerification'])->middleware('throttle:agent-mutations');

    // Profiles
    Route::get('/delegatee-profiles', [DelegateeProfileController::class, 'index']);
    Route::post('/delegatee-profiles', [DelegateeProfileController::class, 'store'])->middleware('throttle:agent-mutations');
    Route::get('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'show']);
    Route::put('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'update'])->middleware('throttle:agent-mutations');
    Route::delete('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'destroy'])->middleware('throttle:agent-mutations');
    Route::post('/delegatee-profiles/{id}/restore', [DelegateeProfileController::class, 'restore'])->middleware('throttle:agent-mutations');
});
```

**Middleware Alias:** Register `'delegation'` alias for `DelegationFeatureGate` in `bootstrap/app.php`

### 7.5 Authorization Policies

**DelegationGraphPolicy**

- File: `app/Policies/DelegationGraphPolicy.php`
- `viewAny(User $user)` → true (user-scoped queries handle visibility)
- `view(User $user, DelegationGraph $graph)` → `$graph->user_id === $user->id`
- `create(User $user)` → true
- `update(User $user, DelegationGraph $graph)` → ownership + `$graph->status === 'draft'`
- `delete(User $user, DelegationGraph $graph)` → ownership + terminal status
- `restore(User $user, DelegationGraph $graph)` → ownership
- `start(User $user, DelegationGraph $graph)` → ownership + `$graph->status === 'ready'`
- `cancel(User $user, DelegationGraph $graph)` → ownership + active status

**DelegateeProfilePolicy**

- File: `app/Policies/DelegateeProfilePolicy.php`
- All methods: ownership check `$profile->user_id === $user->id`

**Policy Registration:** Add to `AuthServiceProvider::$policies` array

## Phase 8: Frontend (7 Vue Pages)

### 8.1 Navigation Integration

**File:** `resources/js/Layouts/AppLayout.vue` (modification)

Add navigation link (conditional on feature flag passed via Inertia shared data):

```vue
<NavLink v-if="$page.props.delegationEnabled" :href="route('agent.delegation.index')" :active="route().current('agent.delegation.*')">
    Delegation
</NavLink>
```

**Inertia Shared Data:** Add `delegationEnabled: config('delegation.ui_enabled')` to `HandleInertiaRequests` middleware

### 8.2 Route Registration (Web)

**File:** `routes/web.php` (additions)

```php
// Delegation routes (guarded by delegation feature flag)
Route::middleware(['delegation.ui'])->group(function () {
    Route::get('/agent/delegation', fn () => Inertia::render('Agent/Delegation/Index'))
        ->name('agent.delegation.index');
    Route::get('/agent/delegation/create', fn () => Inertia::render('Agent/Delegation/Create'))
        ->name('agent.delegation.create');
    Route::get('/agent/delegation/{id}', fn (int $id) => Inertia::render('Agent/Delegation/Show', ['graphId' => $id]))
        ->name('agent.delegation.show');
    Route::get('/agent/delegation/{graphId}/tasks/{taskId}', fn (int $graphId, int $taskId) => Inertia::render('Agent/Delegation/TaskDetail', ['graphId' => $graphId, 'taskId' => $taskId]))
        ->name('agent.delegation.task');
    Route::get('/agent/delegation/{graphId}/tasks/{taskId}/approve', fn (int $graphId, int $taskId) => Inertia::render('Agent/Delegation/VerificationApproval', ['graphId' => $graphId, 'taskId' => $taskId]))
        ->name('agent.delegation.task.approve');
    Route::get('/agent/delegatee-profiles', fn () => Inertia::render('Agent/Delegation/ProfileIndex'))
        ->name('agent.delegation.profiles.index');
    Route::get('/agent/delegatee-profiles/{id}/edit', fn (int $id) => Inertia::render('Agent/Delegation/ProfileForm', ['profileId' => $id]))
        ->name('agent.delegation.profiles.edit');
    Route::get('/agent/delegatee-profiles/create', fn () => Inertia::render('Agent/Delegation/ProfileForm'))
        ->name('agent.delegation.profiles.create');
});
```

**UI Feature Gate Middleware:** Create `DelegationUiFeatureGate` that checks `config('delegation.ui_enabled')`

### 8.3 Vue Pages

**DelegationIndex (`resources/js/Pages/Agent/Delegation/Index.vue`)**

- Graph listing table with status filters (tabs: All, Active, Completed, Failed)
- Columns: Name, Status, Tasks (completed/total), Created, Actions
- Row click navigates to Show page
- "Create Graph" button links to Create page

**DelegationGraphShow (`resources/js/Pages/Agent/Delegation/Show.vue`)**

- Graph header: name, status badge, timestamps
- Action buttons: Start (if ready), Cancel (if running), Clone
- Task list table (not DAG visualization — deferred): Name, Status, Assigned Profile, Verification Status
- Task row click navigates to TaskDetail
- Events panel (collapsible): chronological event log

**DelegationGraphCreate (`resources/js/Pages/Agent/Delegation/Create.vue`)**

- Tab toggle: "Linear Chain" | "DAG JSON"
- Linear Chain mode: ordered list with add/remove/reorder, each item has name and contract JSON editor
- DAG JSON mode: full JSON editor with syntax highlighting
- Inline validation display (errors from ContractValidator)
- "Validate" button (dry-run) and "Create" button
- On success, redirect to Show page

**DelegationTaskDetail (`resources/js/Pages/Agent/Delegation/TaskDetail.vue`)**

- Task header: name, status, assigned profile
- Contract display (JSON pretty-print)
- Attempts table: attempt number, status, duration, timestamps
- Verification history: step type, verdict, evidence viewer
- Link to approval page if pending human approval

**DelegationVerificationApproval (`resources/js/Pages/Agent/Delegation/VerificationApproval.vue`)**

- Task context display
- Latest attempt output viewer
- Approval form: verdict selector (Approve/Reject), evidence notes textarea
- Submit button calls resolve endpoint
- On success, redirect back to TaskDetail

**DelegateeProfileIndex (`resources/js/Pages/Agent/Delegation/ProfileIndex.vue`)**

- Profile listing table: Name, Runner Type, Capabilities, Active status, Actions
- "Create Profile" button
- Row actions: Edit, Deactivate, Delete

**DelegateeProfileForm (`resources/js/Pages/Agent/Delegation/ProfileForm.vue`)**

- Create/Edit form (detects from route param presence)
- Fields: Name, Runner Type dropdown, Command Template, Working Directory, Env JSON editor, Config JSON editor
- Capabilities multi-select (fetches from API)
- Active toggle
- Save/Cancel buttons

### 8.4 Discoverability Acceptance Checks

- Delegation link visible in navigation when `delegation.ui_enabled = true`
- Delegation link hidden in navigation when `delegation.ui_enabled = false`
- Graph listing page accessible at `/agent/delegation`
- Create page accessible at `/agent/delegation/create`
- Graph detail accessible at `/agent/delegation/{id}`
- Task detail accessible at `/agent/delegation/{graphId}/tasks/{taskId}`
- Approval page accessible at `/agent/delegation/{graphId}/tasks/{taskId}/approve`
- Profile listing accessible at `/agent/delegatee-profiles`
- Profile create accessible at `/agent/delegatee-profiles/create`
- Profile edit accessible at `/agent/delegatee-profiles/{id}/edit`

## Phase 9: Configuration & Infrastructure

### 9.1 Config Updates

**File:** `config/delegation.php` (updates)

Align with summary specifications:

```php
return [
    'enabled' => (bool) env('DELEGATION_ENABLED', false),
    'ui_enabled' => (bool) env('DELEGATION_UI_ENABLED', false),
    'max_tasks_per_graph' => (int) env('DELEGATION_MAX_TASKS_PER_GRAPH', 25),
    'max_parallel_tasks_default' => (int) env('DELEGATION_DEFAULT_MAX_PARALLEL_TASKS', 5),
    'max_parallel_tasks_ceiling' => 15,
    'human_approval_timeout_hours' => 4,
    'graceful_cancellation_timeout_minutes' => 15,
    'retry_same_delegatee_limit' => 2,
    'redelegate_limit' => 1,
    'metrics_event_throttle_seconds' => 60,
    'metrics_recompute_schedule_minutes' => 15,
    'reconciler_schedule_minutes' => 2,
    'ai_critic_default_prompt_template' => 'Review the following task output and determine if it meets the acceptance criteria...',
    'capabilities_seed' => [
        'code_execution', 'review', 'testing',
        'documentation', 'deployment', 'monitoring',
    ],
    'check_profiles' => [
        'laravel_standard' => ['php artisan test', './vendor/bin/pint --test'],
        'test_only' => ['php artisan test'],
        'lint_only' => ['./vendor/bin/pint --test'],
    ],
    'transient_error_codes' => ['TIMEOUT', 'RATE_LIMIT', 'CONNECTION_ERROR'],
    'non_transient_error_codes' => ['INVALID_OUTPUT', 'PERMISSION_DENIED', 'SYNTAX_ERROR'],
];
```

### 9.2 Horizon Supervisor

**File:** `config/horizon.php` (additions)

Add to `defaults`:

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

Add to `environments.production` and `environments.local`:

```php
'supervisor-delegation' => [
    'maxProcesses' => max(1, min(8, (int) env('HORIZON_DELEGATION_MAX_PROCESSES', 2))),
    'balanceMaxShift' => 1,
    'balanceCooldown' => 3,
],
```

### 9.3 Scheduler Registration

**File:** `routes/console.php` (additions)

```php
Schedule::command('delegation:reconcile')
    ->everyTwoMinutes()
    ->withoutOverlapping();

Schedule::command('delegation:recompute-metrics')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

### 9.4 Capability Seeder Update

**File:** `database/seeders/DelegationCapabilitySeeder.php`

Update to use summary-specified capabilities:

```php
$slugs = config('delegation.capabilities_seed', [
    'code_execution', 'review', 'testing',
    'documentation', 'deployment', 'monitoring',
]);
```

Run seeder as part of deployment: `php artisan db:seed --class=DelegationCapabilitySeeder`

### 9.5 Artisan Commands

**DelegationReconciler Command**

- File: `app/Console/Commands/DelegationReconcileCommand.php`
- Signature: `delegation:reconcile`
- Invokes `DelegationReconciler` service

**DelegateeMetricsRecomputer Command**

- File: `app/Console/Commands/DelegationRecomputeMetricsCommand.php`
- Signature: `delegation:recompute-metrics`
- Invokes `DelegateeMetricsRecomputer` service for all profiles

## Phase 10: Testing

### 10.1 Unit Tests

**Models:**

- Test all relationship methods
- Test status constants and scopes
- Test JSON casts

**Services:**

- `DelegationGraphBuilder`: test DAG parsing, linear-chain parsing, cycle detection, sequence_order assignment, max task limit
- `ContractValidator`: test all validation rules
- `ContractEnforcer`: test scope narrowing, warning injection
- `DelegateeAssigner`: test capability matching, ranking, tiebreaker, no-match handling
- State transition services: test atomic update behavior, concurrent update rejection

### 10.2 Feature Tests

**API Endpoints:**

- Full CRUD cycle for graphs, tasks, profiles
- Authorization policy enforcement
- Feature gate middleware blocking when disabled
- Graph lifecycle: create → validate → start → monitor → complete
- Cancellation flow with graceful timeout
- Clone flow for both modes

**Verification Pipeline:**

- Automated check step execution and evidence capture
- AI critic async flow with callback completion
- Human approval creation, expiration, resolution

**Recovery Handler:**

- Retry chain execution
- Re-delegation trigger
- Escalation notification

### 10.3 Integration Tests

**End-to-End Flows:**

- Linear-chain graph: create → start → all tasks succeed → graph completes
- DAG graph: create → start → parallel execution respects `max_parallel_tasks` → completion
- Task failure with recovery: fail → retry → succeed
- Task failure without recovery: fail → retry → re-delegate → fail → escalate
- Human approval timeout: create → wait → expire → task fails

### 10.4 Broadcast Tests

- Channel authorization for graph owner
- Channel authorization rejection for non-owner
- Event payload structure validation

## Dependency Order

1. **Phase 1** (Models & State Transitions) — no dependencies
2. **Phase 2** (Graph Building & Validation) — depends on Phase 1 models
3. **Phase 3** (Assignment & Execution Core) — depends on Phase 1, Phase 2
4. **Phase 4** (Verification Pipeline) — depends on Phase 1, Phase 3
5. **Phase 5** (Recovery & Metrics) — depends on Phase 1, Phase 3, Phase 4
6. **Phase 6** (Event Writing & Broadcasting) — depends on Phase 1
7. **Phase 7** (Controllers & API) — depends on Phases 1-6
8. **Phase 8** (Frontend) — depends on Phase 7
9. **Phase 9** (Configuration & Infrastructure) — can parallelize with Phases 1-6
10. **Phase 10** (Testing) — runs throughout, final validation after Phase 8

## Sections

- Phase 1: Models & State Transitions
- Phase 2: Graph Building & Validation
- Phase 3: Assignment & Execution Core
- Phase 4: Verification Pipeline
- Phase 5: Recovery & Metrics
- Phase 6: Event Writing & Broadcasting
- Phase 7: Controllers & API
- Phase 8: Frontend (7 Vue Pages)
- Phase 9: Configuration & Infrastructure
- Phase 10: Testing

## Risks

- Kahn's algorithm cycle detection complexity — edge case bugs could allow cyclic graphs through validation
- Bus::chain callback reliability — if callback job fails, attempt may remain orphaned in running status (mitigated by reconciler)
- Graceful cancellation hybrid force-kill depends on Horizon worker signal delivery — may not work if worker is unresponsive
- AI critic async flow introduces timing complexity — race conditions between callback and pipeline resumption
- Human approval 4-hour timeout is strict — users in different timezones may miss approvals (no configuration flexibility in v1)
- DelegateeMetric recomputation throttle (60s) may cause stale rankings during high-volume delegation
- Max 25 tasks per graph may be insufficient for complex workflows — no dynamic limit adjustment
- Frontend Vue pages add navigation entry to AppLayout — must be conditional on feature flag to avoid exposing disabled feature
- Broadcast event payloads may grow large for graphs with many tasks — no pagination or truncation strategy
- Recovery chain (2 retry + 1 re-delegate) is fixed — no per-task criticality-based adjustment in v1

## Assumptions

- Existing migrations for all 10 delegation tables are complete and correct — no new migrations required
- DelegationCapability model already exists with slug, name, is_active fields and active scope
- DelegationFeatureGate middleware already exists and checks config('delegation.enabled')
- RunStateTransitionService pattern (atomic whereKey/whereIn/update) is the approved pattern for state transitions
- RunEventWriter pattern (auto-increment sequence, chunking, lifecycle events) is the approved pattern for event writing
- Existing broadcast infrastructure (Reverb/Pusher) is operational for new delegation channels
- Bus::chain pattern is acceptable for spawning AgentJobRuns with completion callbacks
- PathPolicy, EnvPolicy, CommandPolicy services exist and provide validation/narrowing methods
- AgentJobRun model exists with status field and can be linked to DelegationAttempt
- Existing Inertia + Vue 3 + Tailwind stack is used for all frontend pages
- User model has standard id/email/name fields for ownership and notifications
- Existing throttle middleware groups (agent-mutations) apply to delegation mutations
- Feature flags checked via config() are the standard pattern (no separate feature flag service)

