# Requirements Discovery Summary

Session: 3

# Agent Org Layer (AI Workforce Orchestration) — Requirements Summary

## Overview

The Org Layer adds a first-class organizational operating layer on top of Agent, enabling users to define named AI employees, reporting lines, councils, and recurring rituals while preserving deterministic execution, strict policy boundaries, and local-first deployment. This is a composition feature that reuses existing Delegation DAG execution, memory architecture, and MCP contracts rather than introducing parallel runtimes.

---

## Core Entities and Data Model

### org_agent_profiles
- `id` (UUID primary key)
- `user_id` (foreign key to users — **user-scoped only in v1, no workspace sharing**)
- `name` (unique within user scope)
- `role_slug` and `role_description`
- `delegatee_profile_id` (foreign key — **1:1 mapping to exactly one delegatee profile**)
- `capability_bindings` (JSON — delegation capability references)
- `authority_overrides` (JSON — **narrowing-only**, cannot expand permissions)
- `default_output_schema` (JSON — output contract)
- `parent_agent_id` (nullable self-reference for reporting edge)
- `archived_at` (nullable timestamp — **soft delete with archive/restore**)
- `created_at`, `updated_at`

### org_reporting_edges
- `id` (UUID primary key)
- `subordinate_agent_id` (foreign key to org_agent_profiles)
- `manager_agent_id` (foreign key to org_agent_profiles)
- `user_id` (foreign key for scoping)
- **Maximum hierarchy depth: 3 levels** (agent → manager → senior manager)
- **Runtime semantics: Escalation routing only** — edges determine escalation paths when thresholds are hit; no authority inheritance

### org_ritual_templates
- `id` (UUID primary key)
- `user_id` (foreign key)
- `name`, `description`
- `cron_expression` (canonical runtime format), `timezone`
- `nl_source_metadata` (optional — original natural language for auditability)
- `phase_graph` (JSON — ordered or DAG structure, **unconditional execution only**, no conditional branching)
- `phase_role_mappings` (JSON — maps phases to org agent roles)
- `context_inputs` (JSON — memory references, previous run outputs, external evidence)
- `verification_strategy` (JSON — per-phase verification requirements)
- `delivery_targets` (JSON — messenger channels, MCP status, exports)
- `escalation_timeout_seconds` (integer — **configurable timeout with auto-fail**)
- `notification_level` (enum: `escalations_only` | `lifecycle` | `verbose` — **default: escalations_only**)
- `archived_at` (nullable — soft delete)
- `created_at`, `updated_at`
- **No import/export in v1** — API-only template management

### org_ritual_runs
- `id` (UUID primary key)
- `ritual_template_id` (foreign key)
- `user_id` (foreign key)
- `state` (enum: `draft` | `scheduled` | `queued` | `running` | `waiting_approval` | `reviewing` | `succeeded` | `failed` | `cancelled` | `partial`)
- `phase_outputs` (JSON — persisted for **retry failed phases only** capability)
- `started_at`, `completed_at`
- `correlation_id` (for cross-service tracing)
- `created_at`, `updated_at`
- **Retention: 90 days** before automatic cleanup

### org_council_templates
- `id` (UUID primary key)
- `user_id` (foreign key)
- `name`, `description`
- `member_list` (JSON — org agent IDs with **perspective labels as text strings only**)
- `evidence_payload_schema` (JSON)
- `member_response_schema` (JSON)
- `synthesis_mode` (enum: `majority` | `weighted` | `chair_decides` — **deterministic rules-first**, model synthesis only when explicitly requested)
- `report_sections` (JSON — agreements, conflicts, recommended actions)
- `archived_at` (nullable — soft delete)
- `created_at`, `updated_at`

### org_cost_ledgers
- `id` (UUID primary key)
- `user_id` (foreign key)
- `org_agent_id` (nullable — for per-agent tracking)
- `ritual_run_id` (nullable — for per-run rollup)
- `token_count`, `runtime_ms`, `estimated_cost_usd`
- `recorded_at`
- **Granularity: Both per-agent and per-ritual-run with rollup**
- **Retention: 90 days**

### org_escalations
- `id` (UUID primary key)
- `ritual_run_id` (foreign key)
- `escalation_type` (enum: `budget` | `verification` | `risk` | `approval`)
- `escalated_to_agent_id` (foreign key — determined by reporting edge routing)
- `state` (enum: `pending` | `approved` | `rejected` | `timed_out`)
- `timeout_at` (timestamp — **auto-fail when exceeded**)
- `resolved_by`, `resolved_at`, `resolution_notes`
- `created_at`

### org_artifact_reviews
- `id` (UUID primary key)
- `ritual_run_id` (foreign key)
- `phase_id` (string)
- `artifact_type` (enum: `summary` | `plan`)
- `attempt_number` (integer — **max 3 revisions** before failure)
- `reviewer_output` (JSON — schema-validated)
- `state` (enum: `pending` | `passed` | `failed` | `clarification_needed`)
- `evidence_references` (JSON)
- `created_at`
- **Retention: 90 days**

---

## Configuration

### config/agent.php additions
```php
'org' => [
    'enabled' => env('ORG_LAYER_ENABLED', false), // Global toggle
    'features' => [
        'profiles' => env('ORG_PROFILES_ENABLED', true),   // Sub-flag
        'rituals' => env('ORG_RITUALS_ENABLED', true),     // Sub-flag
        'councils' => env('ORG_COUNCILS_ENABLED', true),   // Sub-flag
        'cost_governance' => env('ORG_COST_ENABLED', true), // Sub-flag
    ],
    'hierarchy_max_depth' => 3,
    'reviewer_max_attempts' => 3,
    'retention_days' => 90,
    'default_notification_level' => 'escalations_only',
    'budget_hard_stop_behavior' => 'block_undispatched', // Let running branches complete
],
```

---

## Services

### OrgAgentProfileService
- CRUD for org agent profiles (user-scoped)
- Validates 1:1 delegatee profile binding
- Enforces authority narrowing (rejects any permission widening)
- Soft delete with archive/restore

### OrgReportingEdgeService
- Manages manager relationships
- Validates max depth of 3 levels
- Cycle detection
- Provides escalation path resolution

### OrgRitualTemplateService
- CRUD for ritual templates (user-scoped)
- Validates phase graph structure (ordered or DAG, no conditionals)
- Validates phase-to-role mappings against existing org agents
- Configures escalation timeout and notification level
- Soft delete with archive/restore

### OrgRitualRunService
- Instantiates DelegationGraph from ritual template
- Manages run state machine transitions
- Persists phase outputs for partial retry
- Dispatches notifications (escalations and failures only by default)
- Handles retry of failed phases using preserved outputs

### OrgCouncilService
- CRUD for council templates
- Fans out evidence to member tasks
- Executes deterministic synthesis (majority, weighted, chair_decides)
- Model-mediated synthesis only when explicitly enabled
- Generates conflict logs and structured reports

### OrgCostGovernanceService
- Tracks per-agent and per-ritual-run costs
- Evaluates warning, approval-required, and hard-stop thresholds
- **Hard-stop behavior: Block undispatched branches, let running branches complete**
- Generates budget threshold events

### OrgEscalationService
- Creates escalation records based on reporting edge routing
- Manages timeout tracking with auto-fail
- Processes approval/rejection resolutions
- Resumes pending workflows without replaying successful phases

### OrgArtifactReviewService
- Integrates with adversarial reviewer for summary/plan artifacts
- Tracks revision attempts (max 3)
- Persists reviewer findings with evidence references
- Routes clarification requests through open-question queue (summary only)

### OrgRetentionService
- Scheduled cleanup of ritual runs, cost ledgers, and artifact reviews older than 90 days
- Preserves archived templates and profiles indefinitely

---

## API Surface

All endpoints under `/agent/api/v1/org/` — **single-resource mutations only**, no batch operations.

### Org Agents
- `GET /agents` — list user's org agents (supports archived filter)
- `POST /agents` — create org agent
- `GET /agents/{id}` — get org agent details
- `PUT /agents/{id}` — update org agent
- `DELETE /agents/{id}` — soft delete (archive)
- `POST /agents/{id}/restore` — restore archived agent

### Rituals
- `GET /rituals` — list user's ritual templates
- `POST /rituals` — create ritual template
- `GET /rituals/{id}` — get ritual template details
- `PUT /rituals/{id}` — update ritual template
- `DELETE /rituals/{id}` — soft delete (archive)
- `POST /rituals/{id}/restore` — restore archived template
- `POST /rituals/{id}/run` — trigger immediate run
- `POST /rituals/{id}/pause` — pause schedule (not in-flight runs)
- `POST /rituals/{id}/resume` — resume schedule

### Ritual Runs
- `GET /ritual-runs` — list runs with filtering
- `GET /ritual-runs/{id}` — get run details with phase outputs
- `POST /ritual-runs/{id}/retry` — retry failed phases only

### Councils
- `GET /councils` — list council templates
- `POST /councils` — create council template
- `GET /councils/{id}` — get council template details
- `PUT /councils/{id}` — update council template
- `DELETE /councils/{id}` — soft delete (archive)

### Cost and Governance
- `GET /costs/summary` — per-agent and per-ritual cost rollups
- `GET /escalations` — list pending escalations
- `POST /escalations/{id}/resolve` — approve or reject escalation

### Reviews
- `GET /reviews/{id}` — get artifact review details with evidence

---

## Events

All events use structured correlation IDs and follow existing monotonic sequencing:

- `org_ritual_scheduled`
- `org_ritual_started`
- `org_ritual_phase_completed`
- `org_ritual_escalation_requested`
- `org_ritual_escalation_resolved`
- `org_ritual_escalation_timed_out`
- `summary_review_started`
- `summary_review_passed`
- `summary_review_failed`
- `summary_review_clarification_needed`
- `plan_review_started`
- `plan_review_passed`
- `plan_review_failed`
- `org_budget_threshold_warning`
- `org_budget_threshold_exceeded`
- `org_ritual_completed`
- `org_ritual_partial`

---

## Messenger Integration

- Default notification level: **escalations and failures only**
- Supported channels: Slack, Telegram (per messenger v4 contract)
- Commands: list rituals, trigger run, pause/resume, resolve escalations
- Thread-aware progress updates for escalation resolutions
- No raw command payloads accepted

---

## MCP Integration

- Org endpoints follow MCP v5 schema/version discipline
- Scope dimensions: tenant, environment, role
- Read/list operations support scoped filtering
- Mutating operations deny on scope mismatch
- **Single-resource operations only** — no batch endpoints

---

## Locked Contract Compliance

1. **Delegation Runtime**: Org workflows execute through existing DelegationGraph; no authority widening; inherits max-parallel limits
2. **MCP Contract**: Scope dimensions, evaluation order, transport semantics unchanged
3. **Memory Contract**: Graceful degradation by capability mode; failures never block completion
4. **Messenger Contract**: Slack/Telegram channels, signed token flow, confirmation requirements
5. **NL Scheduling Contract**: Cron canonical, rule-based parser first, LLM fallback for low confidence
6. **Adversarial Reviewer Contract**: Bounded review loops (3 attempts), schema-validated output, feature flags preserved

## Goals

- Deliver user-scoped org agent profiles with 1:1 delegatee profile binding and narrowing-only authority overrides
- Implement recurring ritual automation using existing cron scheduler and DelegationGraph execution with unconditional phase execution
- Build deterministic council synthesis with majority, weighted, and chair_decides modes; model synthesis only when explicitly requested
- Integrate adversarial reviewer gates for summary and plan artifacts with maximum 3 revision attempts before failure
- Provide dual-granularity cost tracking (per-agent and per-ritual-run) with warn, approval-required, and hard-stop thresholds
- Implement escalation routing based on reporting edges with configurable timeout and auto-fail behavior
- Support partial ritual run recovery with retry of failed phases only using preserved successful phase outputs
- Deliver messenger notifications with default escalations-and-failures-only granularity to minimize alert fatigue
- Expose single-resource MCP org endpoints consistent with v5 schema and scope enforcement
- Implement soft delete with archive/restore for org agents, ritual templates, and council templates
- Enforce 90-day retention for ritual runs, cost ledgers, and artifact reviews with automated cleanup
- Implement hierarchical feature flags with global toggle plus sub-flags for profiles, rituals, councils, and cost governance


## Constraints

- Org agent profiles are user-scoped only in v1; no workspace sharing
- Each org agent binds to exactly one delegatee profile (1:1 mapping)
- Authority overrides may only narrow permissions, never widen
- Reporting hierarchy maximum depth is 3 levels (agent → manager → senior manager)
- Reporting edges provide escalation routing only, no authority inheritance
- Ritual phases execute unconditionally in defined order/DAG; no conditional branching
- Council synthesis uses deterministic rules by default; model synthesis requires explicit opt-in
- Council perspectives are text labels only, no structured configuration
- Adversarial reviewer allows maximum 3 revision attempts before phase failure
- Hard-stop budget threshold blocks undispatched branches but lets running branches complete
- Escalation timeout results in auto-fail; no auto-approve option
- Messenger notifications default to escalations and failures only
- All MCP mutations are single-resource operations; no batch endpoints
- No ritual template import/export in v1; API-only management
- Session-to-session orchestration remains internal to delegation runtime; not exposed as user tools
- Soft delete with archive/restore for org agents, templates, and councils; hard delete not exposed
- 90-day retention period for ritual runs, cost ledgers, and artifact reviews
- Feature flags are hierarchical: global toggle must be enabled for sub-flags to apply
- All mutations require audit emission with actor, correlation_id, and resource identifiers
- No raw command payloads accepted from messenger or MCP org endpoints
- Existing delegation, memory, MCP, messenger, and NL scheduling contracts must not be violated


## Acceptance Criteria

- Users can create, read, update, archive, and restore org agent profiles scoped to their user account
- Org agent profile creation validates 1:1 delegatee profile binding and rejects authority widening
- Reporting edges enforce maximum 3-level hierarchy depth with cycle detection
- Escalations route through reporting edge chain with configurable timeout and auto-fail on expiry
- Ritual templates can be created with cron schedule, phase graph, role mappings, and notification level
- Ritual runs instantiate DelegationGraph and execute all phases unconditionally in defined order
- Partial ritual run failures allow retry of failed phases using preserved successful phase outputs
- Council templates execute deterministic synthesis (majority/weighted/chair_decides) and produce structured reports with conflict logs
- Model-mediated council synthesis only executes when explicitly enabled in template configuration
- Summary and plan artifacts pass through adversarial reviewer with maximum 3 revision attempts
- Messenger delivers notifications for escalations and failures only by default; other levels configurable per-template
- MCP org endpoints enforce scope checks and reject mutations on mismatch
- All MCP org mutations operate on single resources; batch requests return 400 Bad Request
- Cost tracking records both per-agent and per-ritual-run granularity with rollup queries available
- Hard-stop budget threshold prevents new branch dispatch but allows running branches to complete
- Soft-deleted org agents and templates are archived and restorable; historical run references remain intact
- Automated cleanup removes ritual runs, cost ledgers, and artifact reviews older than 90 days
- Disabling global org feature flag results in 404 for all org endpoints with no side effects
- Sub-feature flags (profiles, rituals, councils, cost_governance) only apply when global flag is enabled
- All org-layer mutations emit audit events with actor, correlation_id, timestamp, and resource identifiers
- Memory integration degrades gracefully by capability mode and never blocks ritual run completion
- End-to-end tests pass for council ritual, non-council ritual, escalation-resume flow, and degraded-memory path

