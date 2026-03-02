# Implementation Plan

Derived from discovery session 3.

# Agent Org Layer (AI Workforce Orchestration) — Implementation Plan

## Phase A: Org Foundation

### A.1 Configuration and Feature Flags

**Objective:** Establish hierarchical feature flag structure and configuration for org layer.

**Implementation:**
1. Add `org` section to `config/agent.php` with global toggle and sub-flags:
   - `org.enabled` (global toggle, default false)
   - `org.features.profiles` (sub-flag for org agent profiles)
   - `org.features.rituals` (sub-flag for ritual automation)
   - `org.features.councils` (sub-flag for council workflows)
   - `org.features.cost_governance` (sub-flag for cost tracking)
   - `org.hierarchy_max_depth` = 3
   - `org.reviewer_max_attempts` = 3
   - `org.retention_days` = 90
   - `org.default_notification_level` = 'escalations_only'
   - `org.budget_hard_stop_behavior` = 'block_undispatched'

2. Extend `FeatureFlagManager` with org-layer constants:
   - `ORG_ENABLED`, `ORG_PROFILES_ENABLED`, `ORG_RITUALS_ENABLED`, `ORG_COUNCILS_ENABLED`, `ORG_COST_ENABLED`
   - Add definitions array entries with labels and descriptions
   - Implement hierarchical check: sub-flags only apply when global flag enabled

3. Create `OrgFeatureGate` middleware following `DelegationFeatureGate` pattern:
   - Check `ORG_ENABLED` flag; return 404 with `FEATURE_DISABLED` error code if disabled
   - Register middleware alias `org` in kernel

**Files:**
- `config/agent.php` — add org configuration section
- `app/Support/Agent/FeatureFlagManager.php` — add org flag constants and definitions
- `app/Http/Middleware/OrgFeatureGate.php` — new middleware for org routes
- `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 12) — register middleware alias

**Acceptance:**
- Feature settings UI at `/tools/features/settings` displays org flags when global flag registered
- Disabling global org flag returns 404 for `/agent/api/v1/org/*` endpoints
- Sub-flags have no effect when global flag disabled; have expected effect when global flag enabled

---

### A.2 Database Migrations — Org Agent Profiles

**Objective:** Create org_agent_profiles and org_reporting_edges tables.

**Implementation:**
1. Create migration `create_org_agent_profiles_table`:
   - `id` UUID primary key
   - `user_id` foreign key to users (ON DELETE CASCADE)
   - `name` string(255), unique within user scope via partial unique index
   - `role_slug` string(100)
   - `role_description` text
   - `delegatee_profile_id` foreign key to delegatee_profiles (ON DELETE RESTRICT)
   - `capability_bindings` JSON
   - `authority_overrides` JSON
   - `default_output_schema` JSON nullable
   - `parent_agent_id` nullable self-referencing UUID
   - `archived_at` timestamp nullable
   - `created_at`, `updated_at` timestamps
   - Unique constraint: `(user_id, name) WHERE archived_at IS NULL`

2. Create migration `create_org_reporting_edges_table`:
   - `id` UUID primary key
   - `subordinate_agent_id` foreign key to org_agent_profiles
   - `manager_agent_id` foreign key to org_agent_profiles
   - `user_id` foreign key to users (denormalized for efficient filtering)
   - `created_at`, `updated_at` timestamps
   - Unique constraint: `(subordinate_agent_id)` — each agent has at most one manager

**Files:**
- `database/migrations/2026_03_XX_create_org_agent_profiles_table.php`
- `database/migrations/2026_03_XX_create_org_reporting_edges_table.php`

**Acceptance:**
- `php artisan migrate` runs without errors
- `php artisan migrate:rollback` cleanly drops tables
- Constraints enforced: unique name per user, foreign key restrictions

---

### A.3 Org Agent Profile Model and Service

**Objective:** Implement OrgAgentProfile model, OrgReportingEdge model, and OrgAgentProfileService.

**Implementation:**
1. Create `OrgAgentProfile` model:
   - UUID primary key trait
   - Relationships: `belongsTo(User)`, `belongsTo(DelegateeProfile)`, `belongsTo(OrgAgentProfile, 'parent_agent_id')`, `hasMany(OrgAgentProfile, 'parent_agent_id')`, `hasOne(OrgReportingEdge, 'subordinate_agent_id')`
   - Casts: `capability_bindings`, `authority_overrides`, `default_output_schema` as arrays; `archived_at` as datetime
   - Scopes: `active()` (whereNull archived_at), `archived()`, `forUser(userId)`

2. Create `OrgReportingEdge` model:
   - UUID primary key
   - Relationships: `belongsTo(OrgAgentProfile, 'subordinate_agent_id')`, `belongsTo(OrgAgentProfile, 'manager_agent_id')`, `belongsTo(User)`

3. Create `OrgAgentProfileService`:
   - `create(User $user, array $data): OrgAgentProfile` — validates 1:1 delegatee binding, authority narrowing
   - `update(OrgAgentProfile $profile, array $data): OrgAgentProfile` — re-validates constraints
   - `archive(OrgAgentProfile $profile): void` — sets archived_at
   - `restore(OrgAgentProfile $profile): void` — clears archived_at, re-validates name uniqueness
   - `validateAuthorityNarrowing(DelegateeProfile $delegatee, array $overrides): bool` — compares capability sets

4. Create `OrgReportingEdgeService`:
   - `setManager(OrgAgentProfile $subordinate, OrgAgentProfile $manager): OrgReportingEdge`
   - `removeManager(OrgAgentProfile $subordinate): void`
   - `validateHierarchyDepth(OrgAgentProfile $proposed): bool` — enforces max 3 levels
   - `detectCycle(OrgAgentProfile $subordinate, OrgAgentProfile $manager): bool`
   - `getEscalationPath(OrgAgentProfile $agent): Collection` — returns ordered manager chain

**Files:**
- `app/Models/OrgAgentProfile.php`
- `app/Models/OrgReportingEdge.php`
- `app/Support/Org/OrgAgentProfileService.php`
- `app/Support/Org/OrgReportingEdgeService.php`
- `database/factories/OrgAgentProfileFactory.php`
- `database/factories/OrgReportingEdgeFactory.php`

**Acceptance:**
- Creating profile with non-owned delegatee_profile throws validation exception
- Creating profile with authority_overrides that widen permissions throws validation exception
- Setting manager that would create depth 4 throws validation exception
- Setting manager that would create cycle throws validation exception
- Archived profiles excluded from active scope; included when explicitly queried

---

### A.4 Org Agent Profile API Controller

**Objective:** Implement REST API for org agent profile management.

**Implementation:**
1. Create `OrgAgentController` extending Controller:
   - `index(Request $request)` — list user's profiles with archived filter
   - `store(StoreOrgAgentRequest $request)` — create profile
   - `show(string $id)` — get profile details with relations
   - `update(UpdateOrgAgentRequest $request, string $id)` — update profile
   - `destroy(string $id)` — archive (soft delete)
   - `restore(string $id)` — restore archived profile

2. Create form requests:
   - `StoreOrgAgentRequest` — validates required fields, delegatee_profile_id exists and owned by user
   - `UpdateOrgAgentRequest` — validates updates, re-checks authority narrowing

3. Create `OrgAgentPolicy`:
   - `view`, `update`, `delete`, `restore` — user_id matches authenticated user

4. Register routes under `/agent/api/v1/org/agents` with `org` middleware group

**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgAgentController.php`
- `app/Http/Requests/Org/StoreOrgAgentRequest.php`
- `app/Http/Requests/Org/UpdateOrgAgentRequest.php`
- `app/Policies/OrgAgentProfilePolicy.php`
- `routes/api.php` — add org route group

**Acceptance:**
- POST `/agent/api/v1/org/agents` creates profile and returns 201
- PUT endpoint updates profile and validates constraints
- DELETE archives profile (archived_at set)
- POST restore endpoint clears archived_at
- GET with `?include_archived=true` includes archived profiles
- Policy denies access to other users' profiles

---

### A.5 Org Events and Audit Infrastructure

**Objective:** Establish event classes and audit emission for org layer.

**Implementation:**
1. Create event classes in `app/Events/Org/`:
   - `OrgAgentProfileCreated`, `OrgAgentProfileUpdated`, `OrgAgentProfileArchived`, `OrgAgentProfileRestored`
   - Each carries: `profile_id`, `user_id`, `correlation_id`, `actor_id`, `timestamp`

2. Create `OrgAuditService`:
   - `logMutation(string $action, Model $resource, ?array $before, array $after, User $actor, string $correlationId): void`
   - Writes to `agent_audit_logs` table with `resource_type` = 'org_agent_profile', etc.

3. Integrate audit emission in OrgAgentProfileService create/update/archive/restore methods

**Files:**
- `app/Events/Org/OrgAgentProfileCreated.php`
- `app/Events/Org/OrgAgentProfileUpdated.php`
- `app/Events/Org/OrgAgentProfileArchived.php`
- `app/Events/Org/OrgAgentProfileRestored.php`
- `app/Support/Org/OrgAuditService.php`

**Acceptance:**
- Profile creation emits `OrgAgentProfileCreated` event
- `agent_audit_logs` contains entry for each mutation with actor, correlation_id, before/after JSON

---

### A.6 Unit and Feature Tests — Phase A

**Objective:** Test coverage for org foundation components.

**Tests:**
1. Unit tests:
   - `OrgAgentProfileServiceTest` — authority narrowing validation, 1:1 binding enforcement
   - `OrgReportingEdgeServiceTest` — hierarchy depth validation, cycle detection, escalation path resolution
   - `OrgFeatureFlagTest` — hierarchical flag behavior

2. Feature tests:
   - `OrgAgentControllerTest` — CRUD operations, policy enforcement, soft delete/restore
   - `OrgFeatureGateTest` — 404 when disabled, 200 when enabled

**Files:**
- `tests/Unit/Support/Org/OrgAgentProfileServiceTest.php`
- `tests/Unit/Support/Org/OrgReportingEdgeServiceTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgAgentControllerTest.php`
- `tests/Feature/OrgFeatureGateTest.php`

**Acceptance:**
- All tests pass with `php artisan test`
- Coverage includes edge cases: max depth 3, cycle prevention, name uniqueness

---

## Phase B: Ritual Runtime Integration

### B.1 Database Migrations — Rituals

**Objective:** Create org_ritual_templates and org_ritual_runs tables.

**Implementation:**
1. Create migration `create_org_ritual_templates_table`:
   - `id` UUID primary key
   - `user_id` foreign key
   - `name`, `description` strings
   - `cron_expression` string(100)
   - `timezone` string(50)
   - `nl_source_metadata` text nullable
   - `phase_graph` JSON (ordered array or DAG structure)
   - `phase_role_mappings` JSON
   - `context_inputs` JSON
   - `verification_strategy` JSON
   - `delivery_targets` JSON
   - `escalation_timeout_seconds` integer default 3600
   - `notification_level` enum ('escalations_only', 'lifecycle', 'verbose') default 'escalations_only'
   - `is_paused` boolean default false
   - `archived_at` timestamp nullable
   - `created_at`, `updated_at`

2. Create migration `create_org_ritual_runs_table`:
   - `id` UUID primary key
   - `ritual_template_id` foreign key
   - `user_id` foreign key
   - `state` enum matching ritual run states
   - `delegation_graph_id` foreign key nullable (links to existing delegation_graphs)
   - `phase_outputs` JSON
   - `started_at`, `completed_at` timestamps nullable
   - `correlation_id` UUID
   - `created_at`, `updated_at`

**Files:**
- `database/migrations/2026_03_XX_create_org_ritual_templates_table.php`
- `database/migrations/2026_03_XX_create_org_ritual_runs_table.php`

**Acceptance:**
- Migrations run successfully
- State enum includes all specified states

---

### B.2 Ritual Template Model and Service

**Objective:** Implement OrgRitualTemplate model and OrgRitualTemplateService.

**Implementation:**
1. Create `OrgRitualTemplate` model:
   - UUID primary key
   - Relationships: `belongsTo(User)`, `hasMany(OrgRitualRun)`
   - Casts: JSON fields as arrays, `archived_at` as datetime, `is_paused` as boolean
   - Scopes: `active()`, `scheduled()` (not paused and not archived)

2. Create `OrgRitualRun` model:
   - UUID primary key
   - Relationships: `belongsTo(OrgRitualTemplate)`, `belongsTo(User)`, `belongsTo(DelegationGraph)` nullable
   - State machine constants matching enum
   - Casts: `phase_outputs` as array, timestamps

3. Create `OrgRitualTemplateService`:
   - `create(User $user, array $data): OrgRitualTemplate` — validates cron expression, phase graph structure
   - `update(OrgRitualTemplate $template, array $data): OrgRitualTemplate`
   - `archive(OrgRitualTemplate $template): void`
   - `restore(OrgRitualTemplate $template): void`
   - `pause(OrgRitualTemplate $template): void` — sets is_paused
   - `resume(OrgRitualTemplate $template): void` — clears is_paused
   - `validatePhaseGraph(array $graph): bool` — DAG validation, no conditionals

4. Create `PhaseGraphValidator`:
   - Validates unconditional execution structure
   - Reuses Kahn's algorithm pattern from `DelegationGraphBuilder` for cycle detection
   - Validates phase-to-role mappings reference existing org agents

**Files:**
- `app/Models/OrgRitualTemplate.php`
- `app/Models/OrgRitualRun.php`
- `app/Support/Org/OrgRitualTemplateService.php`
- `app/Support/Org/PhaseGraphValidator.php`
- `database/factories/OrgRitualTemplateFactory.php`
- `database/factories/OrgRitualRunFactory.php`

**Acceptance:**
- Ritual templates validate cron expressions using existing NL schedule parser patterns
- Phase graphs reject conditional branching structures
- Phase-role mappings validated against user's org agents

---

### B.3 Ritual Run Service and DelegationGraph Integration

**Objective:** Implement OrgRitualRunService that instantiates DelegationGraph from ritual templates.

**Implementation:**
1. Create `OrgRitualRunService`:
   - `createRun(OrgRitualTemplate $template): OrgRitualRun` — creates run in 'queued' state
   - `startRun(OrgRitualRun $run): void` — converts phase graph to DelegationGraph input, calls `DelegationGraphBuilder::build()`
   - `completePhase(OrgRitualRun $run, string $phaseId, array $output): void` — persists to phase_outputs
   - `handleRunCompletion(OrgRitualRun $run, string $status): void` — sets terminal state
   - `retryFailedPhases(OrgRitualRun $run): OrgRitualRun` — creates new run with preserved successful outputs

2. Create `RitualToDelegationMapper`:
   - `mapPhasesToTasks(OrgRitualTemplate $template, OrgRitualRun $run): array` — produces DelegationGraphBuilder input format
   - Maps phase-role bindings to delegatee profile assignments
   - Includes context inputs in task contracts

3. Integrate with existing delegation events:
   - Listen for `DelegationGraphCompleted` to update OrgRitualRun state
   - Map DelegationTask completions to phase outputs

**Files:**
- `app/Support/Org/OrgRitualRunService.php`
- `app/Support/Org/RitualToDelegationMapper.php`
- `app/Listeners/Org/RitualDelegationListener.php`

**Acceptance:**
- Starting ritual run creates corresponding DelegationGraph
- DelegationGraph completion triggers ritual run state transition
- Failed phases can be retried while preserving successful phase outputs

---

### B.4 Ritual Scheduler Integration

**Objective:** Integrate ritual scheduling with existing cron infrastructure.

**Implementation:**
1. Create `OrgRitualSchedulerService`:
   - `getDueRituals(): Collection` — queries active, non-paused templates due per cron expression
   - Uses existing cron evaluation logic from scheduler

2. Create `OrgDispatchDueRitualsJob`:
   - Runs on schedule (configurable, default every minute)
   - Queries due rituals, dispatches `OrgExecuteRitualJob` for each

3. Create `OrgExecuteRitualJob`:
   - `ShouldQueue` with `supervisor-org-rituals` queue
   - Creates `OrgRitualRun`, starts execution via `OrgRitualRunService`
   - Emits `org_ritual_started` event

4. Add Horizon supervisor for org rituals:
   - `supervisor-org-rituals` with appropriate timeout and retry settings

**Files:**
- `app/Support/Org/OrgRitualSchedulerService.php`
- `app/Jobs/Org/OrgDispatchDueRitualsJob.php`
- `app/Jobs/Org/OrgExecuteRitualJob.php`
- `app/Console/Commands/OrgDispatchDueCommand.php`
- `config/horizon.php` — add supervisor-org-rituals
- `routes/console.php` — schedule command

**Acceptance:**
- Due rituals dispatch correctly based on cron expression and timezone
- Paused rituals do not dispatch
- Job failures handled with appropriate retry backoff

---

### B.5 Ritual API Endpoints

**Objective:** Implement REST API for ritual template and run management.

**Implementation:**
1. Create `OrgRitualController`:
   - `index`, `store`, `show`, `update`, `destroy` (archive), `restore` for templates
   - `run` — trigger immediate run
   - `pause`, `resume` — schedule control (not in-flight runs)

2. Create `OrgRitualRunController`:
   - `index` — list runs with filtering (by template, state, date range)
   - `show` — get run details with phase outputs
   - `retry` — retry failed phases only

3. Create form requests and policies

4. Register routes under `/agent/api/v1/org/rituals` and `/agent/api/v1/org/ritual-runs`

**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgRitualController.php`
- `app/Http/Controllers/Api/V1/Org/OrgRitualRunController.php`
- `app/Http/Requests/Org/StoreOrgRitualRequest.php`
- `app/Http/Requests/Org/UpdateOrgRitualRequest.php`
- `app/Policies/OrgRitualTemplatePolicy.php`
- `app/Policies/OrgRitualRunPolicy.php`

**Acceptance:**
- Templates CRUD works with validation
- Run-now triggers immediate execution
- Pause/resume affects schedule, not running graphs
- Retry creates new run with preserved successful outputs

---

### B.6 Ritual Events

**Objective:** Implement ritual-specific events.

**Implementation:**
Create events in `app/Events/Org/`:
- `OrgRitualScheduled`
- `OrgRitualStarted`
- `OrgRitualPhaseCompleted`
- `OrgRitualCompleted`
- `OrgRitualPartial`

Emit events from `OrgRitualRunService` at appropriate state transitions.

**Files:**
- `app/Events/Org/OrgRitualScheduled.php`
- `app/Events/Org/OrgRitualStarted.php`
- `app/Events/Org/OrgRitualPhaseCompleted.php`
- `app/Events/Org/OrgRitualCompleted.php`
- `app/Events/Org/OrgRitualPartial.php`

**Acceptance:**
- Events emitted at correct lifecycle points
- Events carry correlation_id for tracing

---

### B.7 Unit and Feature Tests — Phase B

**Tests:**
1. Unit tests:
   - `OrgRitualTemplateServiceTest` — phase graph validation, cron validation
   - `RitualToDelegationMapperTest` — correct task structure generation
   - `PhaseGraphValidatorTest` — rejects conditionals, detects cycles

2. Feature tests:
   - `OrgRitualControllerTest` — CRUD, pause/resume, run-now
   - `OrgRitualRunControllerTest` — listing, retry
   - `OrgRitualSchedulerTest` — due rituals dispatched correctly

**Files:**
- `tests/Unit/Support/Org/OrgRitualTemplateServiceTest.php`
- `tests/Unit/Support/Org/RitualToDelegationMapperTest.php`
- `tests/Unit/Support/Org/PhaseGraphValidatorTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgRitualControllerTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgRitualRunControllerTest.php`
- `tests/Feature/Org/OrgRitualSchedulerTest.php`

---

## Phase C: Council and Quality Gates

### C.1 Database Migrations — Councils and Reviews

**Objective:** Create org_council_templates, org_artifact_reviews tables.

**Implementation:**
1. Create migration `create_org_council_templates_table`:
   - `id` UUID primary key
   - `user_id` foreign key
   - `name`, `description`
   - `member_list` JSON (array of {agent_id, perspective_label})
   - `evidence_payload_schema` JSON
   - `member_response_schema` JSON
   - `synthesis_mode` enum ('majority', 'weighted', 'chair_decides')
   - `use_model_synthesis` boolean default false
   - `report_sections` JSON
   - `archived_at` timestamp nullable
   - `created_at`, `updated_at`

2. Create migration `create_org_artifact_reviews_table`:
   - `id` UUID primary key
   - `ritual_run_id` foreign key
   - `phase_id` string
   - `artifact_type` enum ('summary', 'plan')
   - `attempt_number` integer
   - `reviewer_output` JSON
   - `state` enum ('pending', 'passed', 'failed', 'clarification_needed')
   - `evidence_references` JSON
   - `created_at`

**Files:**
- `database/migrations/2026_03_XX_create_org_council_templates_table.php`
- `database/migrations/2026_03_XX_create_org_artifact_reviews_table.php`

---

### C.2 Council Model and Service

**Objective:** Implement council template management and execution.

**Implementation:**
1. Create `OrgCouncilTemplate` model with relationships

2. Create `OrgCouncilService`:
   - `create`, `update`, `archive`, `restore` for templates
   - `executeCouncil(OrgCouncilTemplate $template, array $evidence): CouncilResult`
   - `fanOutToMembers(OrgCouncilTemplate $template, array $evidence): Collection` — creates member tasks
   - `synthesize(OrgCouncilTemplate $template, Collection $responses): SynthesisResult` — deterministic synthesis
   - `modelSynthesize(OrgCouncilTemplate $template, Collection $responses): SynthesisResult` — LLM-assisted (opt-in only)

3. Create synthesis strategy classes:
   - `MajoritySynthesisStrategy`
   - `WeightedSynthesisStrategy`
   - `ChairDecidesSynthesisStrategy`

4. Create `CouncilResult` and `SynthesisResult` DTOs

**Files:**
- `app/Models/OrgCouncilTemplate.php`
- `app/Support/Org/OrgCouncilService.php`
- `app/Support/Org/Synthesis/MajoritySynthesisStrategy.php`
- `app/Support/Org/Synthesis/WeightedSynthesisStrategy.php`
- `app/Support/Org/Synthesis/ChairDecidesSynthesisStrategy.php`
- `app/Support/Org/CouncilResult.php`
- `app/Support/Org/SynthesisResult.php`

**Acceptance:**
- Deterministic synthesis used by default
- Model synthesis only invoked when `use_model_synthesis` is true
- Conflict logs generated when member responses disagree

---

### C.3 Artifact Review Integration

**Objective:** Integrate adversarial reviewer with org ritual workflows.

**Implementation:**
1. Create `OrgArtifactReview` model

2. Create `OrgArtifactReviewService`:
   - `reviewArtifact(OrgRitualRun $run, string $phaseId, string $type, array $artifact): ReviewResult`
   - Delegates to existing `AdversarialReviewerService`
   - Tracks attempts (max 3), persists results
   - Routes clarification for summary type only

3. Integrate into ritual phase completion:
   - Before marking phase complete, check if artifact requires review
   - If review fails after 3 attempts, mark phase failed with evidence

**Files:**
- `app/Models/OrgArtifactReview.php`
- `app/Support/Org/OrgArtifactReviewService.php`
- `database/factories/OrgArtifactReviewFactory.php`

**Acceptance:**
- Summary artifacts allow clarification routing
- Plan artifacts fail after 3 revision attempts (no clarification)
- Reviewer output schema-validated per existing AdversarialReviewerService contracts

---

### C.4 Council API Endpoints

**Objective:** Implement REST API for council management.

**Implementation:**
1. Create `OrgCouncilController`:
   - `index`, `store`, `show`, `update`, `destroy` (archive)

2. Create form requests and policy

3. Register routes under `/agent/api/v1/org/councils`

**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgCouncilController.php`
- `app/Http/Requests/Org/StoreOrgCouncilRequest.php`
- `app/Http/Requests/Org/UpdateOrgCouncilRequest.php`
- `app/Policies/OrgCouncilTemplatePolicy.php`

---

### C.5 Review API Endpoint

**Objective:** Expose artifact review details via API.

**Implementation:**
1. Create `OrgReviewController`:
   - `show(string $id)` — returns review details with evidence references

2. Register route `/agent/api/v1/org/reviews/{id}`

**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgReviewController.php`

---

### C.6 Review and Council Events

**Objective:** Implement events for review and council workflows.

**Implementation:**
Create events:
- `SummaryReviewStarted`, `SummaryReviewPassed`, `SummaryReviewFailed`, `SummaryReviewClarificationNeeded`
- `PlanReviewStarted`, `PlanReviewPassed`, `PlanReviewFailed`

**Files:**
- `app/Events/Org/SummaryReviewStarted.php`
- `app/Events/Org/SummaryReviewPassed.php`
- `app/Events/Org/SummaryReviewFailed.php`
- `app/Events/Org/SummaryReviewClarificationNeeded.php`
- `app/Events/Org/PlanReviewStarted.php`
- `app/Events/Org/PlanReviewPassed.php`
- `app/Events/Org/PlanReviewFailed.php`

---

### C.7 Unit and Feature Tests — Phase C

**Tests:**
1. Unit tests:
   - `OrgCouncilServiceTest` — synthesis modes, conflict detection
   - `MajoritySynthesisStrategyTest`, `WeightedSynthesisStrategyTest`, `ChairDecidesSynthesisStrategyTest`
   - `OrgArtifactReviewServiceTest` — attempt tracking, clarification routing

2. Feature tests:
   - `OrgCouncilControllerTest` — CRUD operations
   - `OrgArtifactReviewIntegrationTest` — pass/revise/clarification flows

**Files:**
- `tests/Unit/Support/Org/OrgCouncilServiceTest.php`
- `tests/Unit/Support/Org/Synthesis/*Test.php`
- `tests/Unit/Support/Org/OrgArtifactReviewServiceTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgCouncilControllerTest.php`
- `tests/Feature/Org/OrgArtifactReviewIntegrationTest.php`

---

## Phase D: Governance and Surfaces

### D.1 Database Migrations — Cost and Escalations

**Objective:** Create org_cost_ledgers and org_escalations tables.

**Implementation:**
1. Create migration `create_org_cost_ledgers_table`:
   - `id` UUID primary key
   - `user_id` foreign key
   - `org_agent_id` foreign key nullable
   - `ritual_run_id` foreign key nullable
   - `token_count` bigint
   - `runtime_ms` bigint
   - `estimated_cost_usd` decimal(10,6)
   - `recorded_at` timestamp
   - Indexes for rollup queries

2. Create migration `create_org_escalations_table`:
   - `id` UUID primary key
   - `ritual_run_id` foreign key
   - `escalation_type` enum ('budget', 'verification', 'risk', 'approval')
   - `escalated_to_agent_id` foreign key
   - `state` enum ('pending', 'approved', 'rejected', 'timed_out')
   - `timeout_at` timestamp
   - `resolved_by` string nullable (actor identifier)
   - `resolved_at` timestamp nullable
   - `resolution_notes` text nullable
   - `created_at`

**Files:**
- `database/migrations/2026_03_XX_create_org_cost_ledgers_table.php`
- `database/migrations/2026_03_XX_create_org_escalations_table.php`

---

### D.2 Cost Governance Service

**Objective:** Implement cost tracking and budget threshold enforcement.

**Implementation:**
1. Create `OrgCostLedger` model

2. Create `OrgCostGovernanceService`:
   - `recordCost(OrgRitualRun $run, ?OrgAgentProfile $agent, CostData $data): void`
   - `getAgentCosts(User $user, OrgAgentProfile $agent, DateRange $range): CostSummary`
   - `getRitualRunCosts(OrgRitualRun $run): CostSummary`
   - `getUserCostSummary(User $user, DateRange $range): CostSummary`
   - `evaluateThreshold(User $user, CostSummary $costs): ThresholdResult`
   - `enforceHardStop(OrgRitualRun $run): void` — blocks undispatched branches

3. Create `CostThresholdEvaluator`:
   - Evaluates against user-configured thresholds
   - Returns warning, approval_required, or hard_stop

**Files:**
- `app/Models/OrgCostLedger.php`
- `app/Support/Org/OrgCostGovernanceService.php`
- `app/Support/Org/CostThresholdEvaluator.php`
- `app/Support/Org/CostSummary.php`
- `database/factories/OrgCostLedgerFactory.php`

**Acceptance:**
- Costs recorded at both agent and run granularity
- Hard-stop blocks undispatched branches; running branches complete
- Threshold crossing emits events

---

### D.3 Escalation Service

**Objective:** Implement escalation creation, routing, and resolution.

**Implementation:**
1. Create `OrgEscalation` model

2. Create `OrgEscalationService`:
   - `createEscalation(OrgRitualRun $run, string $type, OrgAgentProfile $escalatedTo, int $timeoutSeconds): OrgEscalation`
   - `resolve(OrgEscalation $escalation, string $resolution, string $resolvedBy, ?string $notes): void`
   - `checkTimeouts(): Collection` — finds and processes expired escalations
   - `getEscalationTarget(OrgRitualRun $run): OrgAgentProfile` — uses reporting edge service

3. Create `OrgEscalationTimeoutJob`:
   - Scheduled job to process timed-out escalations
   - Sets state to 'timed_out', emits event

**Files:**
- `app/Models/OrgEscalation.php`
- `app/Support/Org/OrgEscalationService.php`
- `app/Jobs/Org/OrgEscalationTimeoutJob.php`
- `database/factories/OrgEscalationFactory.php`

**Acceptance:**
- Escalations route through reporting edge chain
- Timeout results in auto-fail, not auto-approve
- Resolution resumes run without replaying successful phases

---

### D.4 Cost and Escalation API Endpoints

**Objective:** Expose cost summaries and escalation management via API.

**Implementation:**
1. Create `OrgCostController`:
   - `summary(Request $request)` — returns per-agent and per-ritual rollups

2. Create `OrgEscalationController`:
   - `index(Request $request)` — list pending escalations
   - `resolve(ResolveEscalationRequest $request, string $id)` — approve or reject

3. Register routes

**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgCostController.php`
- `app/Http/Controllers/Api/V1/Org/OrgEscalationController.php`
- `app/Http/Requests/Org/ResolveEscalationRequest.php`

---

### D.5 Escalation and Budget Events

**Objective:** Implement governance-related events.

**Implementation:**
Create events:
- `OrgRitualEscalationRequested`
- `OrgRitualEscalationResolved`
- `OrgRitualEscalationTimedOut`
- `OrgBudgetThresholdWarning`
- `OrgBudgetThresholdExceeded`

**Files:**
- `app/Events/Org/OrgRitualEscalationRequested.php`
- `app/Events/Org/OrgRitualEscalationResolved.php`
- `app/Events/Org/OrgRitualEscalationTimedOut.php`
- `app/Events/Org/OrgBudgetThresholdWarning.php`
- `app/Events/Org/OrgBudgetThresholdExceeded.php`

---

### D.6 Messenger Integration

**Objective:** Add messenger commands for org layer operations.

**Implementation:**
1. Extend messenger command handlers for org operations:
   - `/org rituals list` — list active rituals
   - `/org rituals run {name}` — trigger immediate run
   - `/org rituals pause {name}`, `/org rituals resume {name}`
   - `/org escalations` — list pending escalations
   - `/org escalations resolve {id} approve|reject`

2. Create `OrgMessengerCommandHandler`:
   - Parses commands, validates permissions
   - Uses async mutation pattern with thread-aware progress updates
   - No raw command payloads accepted

3. Configure notification dispatching:
   - Default: escalations and failures only
   - Respects per-template notification_level setting

**Files:**
- `app/Support/Messenger/Commands/OrgMessengerCommandHandler.php`
- `app/Support/Org/OrgNotificationService.php`

**Acceptance:**
- Commands work via Slack and Telegram
- Destructive actions require confirmation
- Notifications respect granularity settings

---

### D.7 Web UI Routes and Navigation

**Objective:** Expose org layer management in web interface.

**Implementation:**
1. Add web routes in `routes/web.php`:
   - `/agent/org` — org layer index/dashboard
   - `/agent/org/agents` — list org agents
   - `/agent/org/agents/create` — create org agent form
   - `/agent/org/agents/{id}/edit` — edit org agent
   - `/agent/org/rituals` — list ritual templates
   - `/agent/org/rituals/create` — create ritual template
   - `/agent/org/rituals/{id}` — ritual detail with runs
   - `/agent/org/rituals/{id}/edit` — edit ritual
   - `/agent/org/councils` — list council templates
   - `/agent/org/escalations` — pending escalations dashboard
   - `/agent/org/costs` — cost dashboard

2. Create `OrgUiFeatureGate` middleware (following `DelegationUiFeatureGate` pattern)

3. Register Inertia page components for each route

4. Add navigation items when org layer enabled:
   - Main nav: "Org Layer" with submenu items
   - Conditional display based on feature flag

**Files:**
- `routes/web.php` — add org route group
- `app/Http/Middleware/OrgUiFeatureGate.php`
- `resources/js/Pages/Agent/Org/Index.vue` (stub)
- `resources/js/Pages/Agent/Org/Agents/*.vue` (stubs)
- `resources/js/Pages/Agent/Org/Rituals/*.vue` (stubs)
- `resources/js/Pages/Agent/Org/Councils/*.vue` (stubs)
- `resources/js/Pages/Agent/Org/Escalations/*.vue` (stubs)
- `resources/js/Pages/Agent/Org/Costs/*.vue` (stubs)
- `resources/js/Layouts/AppLayout.vue` — add navigation items

**Acceptance:**
- Navigation items appear when org layer feature flag enabled
- Routes protected by feature gate middleware
- Stub pages render without errors

---

### D.8 Unit and Feature Tests — Phase D

**Tests:**
1. Unit tests:
   - `OrgCostGovernanceServiceTest` — recording, rollups, threshold evaluation
   - `OrgEscalationServiceTest` — creation, routing, resolution, timeout

2. Feature tests:
   - `OrgCostControllerTest` — summary endpoint
   - `OrgEscalationControllerTest` — list, resolve
   - `OrgMessengerCommandHandlerTest` — command parsing, execution
   - `OrgUiRoutesTest` — routes accessible when enabled, 404 when disabled

**Files:**
- `tests/Unit/Support/Org/OrgCostGovernanceServiceTest.php`
- `tests/Unit/Support/Org/OrgEscalationServiceTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgCostControllerTest.php`
- `tests/Feature/Http/Controllers/Api/V1/Org/OrgEscalationControllerTest.php`
- `tests/Feature/Messenger/OrgMessengerCommandHandlerTest.php`
- `tests/Feature/OrgUiRoutesTest.php`

---

## Phase E: Hardening and Compatibility

### E.1 Retention Service and Scheduled Cleanup

**Objective:** Implement automated cleanup for org layer data.

**Implementation:**
1. Create `OrgRetentionService`:
   - `cleanupRitualRuns(int $daysOld = 90): int` — deletes runs older than retention
   - `cleanupCostLedgers(int $daysOld = 90): int`
   - `cleanupArtifactReviews(int $daysOld = 90): int`
   - Preserves archived templates and profiles indefinitely

2. Create `OrgRetentionCleanupCommand`:
   - Artisan command: `org:cleanup-retention`
   - Schedule daily in `routes/console.php`

**Files:**
- `app/Support/Org/OrgRetentionService.php`
- `app/Console/Commands/OrgRetentionCleanupCommand.php`
- `routes/console.php` — schedule command

**Acceptance:**
- Records older than 90 days removed
- Archived templates and profiles preserved
- Command outputs counts of deleted records

---

### E.2 Memory Integration Graceful Degradation

**Objective:** Ensure org workflows handle memory system unavailability gracefully.

**Implementation:**
1. Modify `RitualToDelegationMapper` to handle memory context injection failures:
   - Wrap memory retrieval in try-catch
   - Log warning, continue without memory context
   - Never block ritual run completion due to memory failure

2. Add degradation tests confirming ritual completion without memory

**Files:**
- `app/Support/Org/RitualToDelegationMapper.php` — add graceful degradation
- `tests/Feature/Org/OrgMemoryDegradationTest.php`

**Acceptance:**
- Ritual runs complete successfully when memory system unavailable
- Warning logged when memory context unavailable
- No memory failures propagate as run failures

---

### E.3 MCP Scope Enforcement

**Objective:** Ensure org MCP endpoints comply with MCP v5 contracts.

**Implementation:**
1. Verify org controllers inherit standard scope check middleware
2. Verify single-resource operations only (no batch endpoints)
3. Verify scope mismatch returns deny (existing pattern)
4. Add explicit batch rejection for any multi-resource payloads

**Files:**
- `app/Http/Controllers/Api/V1/Org/*Controller.php` — verify patterns
- `tests/Feature/Org/OrgMcpScopeEnforcementTest.php`

**Acceptance:**
- All org mutations require auth and scope validation
- Batch requests return 400 Bad Request
- Scope mismatch returns appropriate deny response

---

### E.4 Observability and Telemetry

**Objective:** Add comprehensive logging and metrics for org layer.

**Implementation:**
1. Add structured logging with correlation IDs:
   - Log at ritual lifecycle points
   - Log at escalation points
   - Log at reviewer loop iterations

2. Create `OrgMetricsService`:
   - Ritual lifecycle and phase timings
   - Escalation frequency and resolution latency
   - Reviewer loop metrics (attempt count, outcomes)
   - Queue depth visibility

3. Add Horizon queue wait time thresholds for org queues

**Files:**
- `app/Support/Org/OrgMetricsService.php`
- `config/horizon.php` — add org queue wait thresholds

**Acceptance:**
- Structured logs include correlation_id, user_id, resource identifiers
- Metrics queryable for dashboards

---

### E.5 Regression Tests

**Objective:** Confirm disabling org feature flags leaves existing behavior unchanged.

**Implementation:**
1. Create regression test suite:
   - With org flags disabled, verify delegation layer unchanged
   - With org flags disabled, verify memory layer unchanged
   - With org flags disabled, verify messenger unchanged
   - With org flags disabled, verify scheduler unchanged

**Files:**
- `tests/Feature/Org/OrgDisabledRegressionTest.php`

**Acceptance:**
- All existing test suites pass with org flags disabled
- No side effects when org layer disabled

---

### E.6 End-to-End Integration Tests

**Objective:** Comprehensive E2E tests for core org workflows.

**Implementation:**
1. Create E2E test class:
   - `testCouncilRitualE2E` — full council workflow with synthesis
   - `testNonCouncilRitualE2E` — standard ritual through completion
   - `testEscalationResumeFlowE2E` — escalation, timeout/resolution, resume
   - `testDegradedMemoryPathE2E` — ritual completion without memory

**Files:**
- `tests/Feature/Org/OrgEndToEndTest.php`

**Acceptance:**
- All four E2E scenarios pass
- Tests use factory seeding for realistic data
- Tests verify final states and emitted events

---

### E.7 CI Compatibility Matrix

**Objective:** Ensure org layer compatibility with existing CI checks.

**Implementation:**
1. Verify existing CI pipeline includes org tests
2. Add org-specific Pint/PHPStan rules if needed
3. Verify database migration CI step includes org migrations

**Files:**
- `.github/workflows/ci.yml` or equivalent — verify inclusion

**Acceptance:**
- CI passes with org layer tests
- No regressions in existing test suites

## Sections

- Phase A: Org Foundation
- Phase B: Ritual Runtime Integration
- Phase C: Council and Quality Gates
- Phase D: Governance and Surfaces
- Phase E: Hardening and Compatibility


## Risks

- DelegationGraph coupling: Org ritual execution depends on DelegationGraphBuilder and existing delegation runtime; changes to delegation internals could break ritual-to-delegation mapping
- Adversarial reviewer integration: OrgArtifactReviewService depends on AdversarialReviewerService which uses subprocess execution; reviewer failures or timeouts could block ritual phase completion
- Cron scheduler race conditions: Multiple OrgDispatchDueRitualsJob instances could dispatch duplicate ritual runs if scheduler runs faster than job completion
- Reporting edge cascade: Soft-deleting org agents with subordinates could orphan reporting edges or create invalid escalation paths
- Memory system degradation paths: While designed for graceful degradation, untested edge cases in memory context injection could cause unexpected ritual failures
- Horizon supervisor capacity: New org-rituals queue competes with existing supervisors for worker processes; insufficient capacity could cause job backlog
- Feature flag interaction complexity: Hierarchical flags (global + sub-flags) increase configuration complexity and potential for misconfiguration
- Retention cleanup timing: 90-day retention with daily cleanup could conflict with in-progress escalation resolutions referencing soon-to-be-deleted runs
- Council synthesis determinism: Rules-based synthesis strategies must handle edge cases (ties, missing responses) consistently to maintain deterministic behavior
- MCP single-resource enforcement: Frontend or integration clients may expect batch operations; explicit 400 rejection requires clear documentation


## Assumptions

- DelegationGraphBuilder::build() interface remains stable and accepts the task definition format produced by RitualToDelegationMapper
- Existing AdversarialReviewerService methods (reviewSummary, reviewPlan) can be invoked from org context without modification
- PostgreSQL supports the required UUID, JSON, and enum column types in all deployment environments
- Horizon supervisor configuration changes do not require worker restart during deployment
- User-scoped data isolation (org_agent_profiles.user_id) is sufficient for v1; no workspace sharing required
- Cron expression evaluation uses standard POSIX cron syntax compatible with existing NL schedule parser output
- Existing messenger webhook handlers can be extended with org command parsing without breaking current command routing
- Web UI components use existing Inertia/Vue patterns; no new frontend build dependencies required
- AgentAuditLog table structure accommodates org resource types without schema modification
- Redis connection capacity sufficient for additional org-rituals queue without configuration changes
- Test database (pgsql_testing) supports same enum and UUID features as production PostgreSQL
- Feature flag table (agent_feature_settings) can store additional org-layer flag entries without migration

