# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve current Laravel local-first runtime, queue topology, scheduler dedupe/watermark behavior, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature-flag architecture.
2. In scope: telemetry v1 ledger + normalization, reliability scoring v1, cost governance service, escalation/pause/resume governance, deployment counting contract, operator dashboard surfaces, docs alignment, and engineering workflow templates.
3. Out of scope: new messenger channels, new model providers, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Lock operator-facing positioning copy to: "We help companies deploy AI agents safely and keep them reliable in production."
5. Keep API/versioning changes additive to current routing conventions; do not introduce disruptive path migrations.
Impacted components: config/agent.php, docs/, README.md, PROJECT-STATUS.md, app/ domain services.

## 2. Architecture Changes
1. Keep Telemetry as canonical truth: append-only ledger plus async deterministic projections.
2. Define workflow identity contract: `workflow_key` is canonical cross-domain identifier; numeric `workflow_id` is optional internal surrogate only where needed.
3. Keep Reliability domain at `run_id` scoring granularity; `run_attempt_id` remains lineage/diagnostic only.
4. Keep Cost Governance domain for canonical enforcement cost, billed-cost reconciliation, and monthly budget enforcement.
5. Keep Deployment Health domain for per-workflow projections: ReliabilityScore, DegradedRate, HardFailRate, BudgetUtilization, EscalationEvents.
6. Keep System Narrative domain as canonical source for README and PROJECT-STATUS publication.
7. Treat `run_attempts` as projection/dimension materialized from ledger and existing run lifecycle data; never as independent source of truth.
Impacted files/components:
- app/Services/Telemetry/*
- app/Services/Reliability/*
- app/Services/Cost/*
- app/Services/DeploymentHealth/*
- app/Services/SystemNarrative/*
- app/Models/* new projection and governance models
- app/Console/Commands/* and app/Jobs/* for ingestion/projection jobs

## 3. Data Model and Migrations
1. Create immutable append-only `telemetry_event_ledger`.
- Required columns: id, event_id, schema_name, schema_version, run_id, run_attempt_id, parent_run_id, workflow_key, agent_id, provider, model, prompt_version, event_type, event_ts, sequence_no, sequence_violation, payload_json, estimated_flags_json, telemetry_delayed, ingested_at.
- Constraints/indexes: unique(event_id, run_attempt_id), index(run_id, sequence_no), index(workflow_key, event_ts), index(schema_name, schema_version).
2. Create `run_attempts` as projection table, not ingest-write table.
- Columns: run_attempt_id PK, run_id, parent_run_id, workflow_key, retry_ordinal, branch_path, started_at, ended_at, terminal_state, projection_version, source_high_watermark_id.
- Write rule: rebuilt/updated only by projection jobs (`RebuildTelemetryProjections` and incremental projector).
3. Create `run_classifications`.
- Columns: run_id PK, workflow_key, classification, failure_class, failure_reason_code, hard_fail, assisted_sla_deadline_at, assisted_sla_state, classified_at, classified_by, verification_passed, human_intervention, degraded_trigger_evidence_json.
4. Create reliability snapshots.
- `workflow_reliability_windows`: workflow_key, evaluated_at, window_kind (14d|50run), weighted_reliability, degraded_rate, hard_fail_rate, gate_state.
- `workflow_reliability_current`: workflow_key PK, stricter_window_kind, weighted_reliability, degraded_rate, hard_fail_rate, gate_state, evaluated_at.
5. Create escalation and governance tables.
- `workflow_escalations`: id, workflow_key, run_id, trigger_type, trigger_payload, status, opened_at, resolved_at.
- `manual_override_audits`: id, actor_id, acted_at, run_id, workflow_key, previous_state, new_state, reason, timestamp (compatibility mirror for v1 contract, constrained equal to acted_at).
6. Create cost tables.
- `model_rate_cards`: provider, model, effective_version, input_token_rate, output_token_rate, currency, effective_from, effective_to.
- `workflow_budget_policies`: workflow_key PK, monthly_budget_amount, warn_threshold_pct, enforce_threshold_pct, active.
- `workflow_cost_rollups`: workflow_key, period_key, canonical_cost_total, billed_cost_total, variance_total, utilization_pct.
7. Create deployment counting table.
- `deployment_registrations`: workflow_key PK, controls_enabled_at, gate_passed_at, compliance_hold_passed_at, counted_at, optimization_loop_count.
8. Migration dependency order: ledger -> run_attempt projection structures -> classification -> reliability windows -> escalation/override -> cost -> deployment counting.
Impacted files/components:
- database/migrations/*
- app/Models/TelemetryEventLedger.php
- app/Models/RunAttempt.php
- app/Models/RunClassification.php
- app/Models/WorkflowReliabilityWindow.php
- app/Models/WorkflowReliabilityCurrent.php
- app/Models/WorkflowEscalation.php
- app/Models/ManualOverrideAudit.php
- app/Models/ModelRateCard.php
- app/Models/WorkflowBudgetPolicy.php
- app/Models/WorkflowCostRollup.php
- app/Models/DeploymentRegistration.php

## 4. API and Tool Contracts
1. Extend the existing versioned API group in `routes/api.php`; keep pathing additive and consistent with current app conventions.
2. Standardize path identity to `{workflowKey}` and map internally to workflow records.
3. Add/extend endpoints:
- GET workflows/{workflowKey}/health
- GET workflows/{workflowKey}/reliability
- GET workflows/{workflowKey}/cost
- GET workflows/{workflowKey}/escalations
- POST workflows/{workflowKey}/resume
- POST workflows/{workflowKey}/pause
- GET deployments/counting
- POST telemetry/events (internal ingestion contract)
4. Pause/resume payload contract: actor_id, timestamp, run_id, previous_state, new_state, reason. Server persists `acted_at` and mirrors `timestamp` for contract compatibility.
5. Runner emission contract: runners emit typed raw events only; normalization/classification/costing remain server-side.
6. Backward-compatible response strategy: preserve existing fields and add reliability/cost/governance blocks under additive keys.
Impacted files/components:
- routes/api.php
- app/Http/Controllers/Api/V1/*
- app/Http/Requests/Agent/*
- app/Http/Middleware/AgentApiVersionHeader.php
- app/Support/Agent/* validators and contract mappers

## 5. Event Contracts and Ingestion Semantics
1. Enforce event envelope fields: `schema_name`, `schema_version`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`.
2. Ingestion guarantees:
- at-least-once ingest
- idempotent dedupe on (event_id, run_attempt_id)
- immutable ledger writes only
3. Sequencing policy:
- accept out-of-order events; do not drop valid late arrivals
- set `sequence_violation=true` when sequence is non-monotonic or gaps are detected
- keep deterministic projection ordering: sequence_no (when available) -> event_ts -> ingested_at -> ledger id
4. Deterministic normalization:
- provider-native payloads map to canonical taxonomy via versioned normalizers
- missing usage/cost fields are estimated deterministically and flagged in estimated flags
5. Outage behavior:
- fail-open with durable buffering/retry
- mark `telemetry_delayed` for delayed ingestion
- auto-protect on unobservable breach thresholds
6. Retention:
- raw ledger retention 7d for raw payload partition
- normalized and aggregate projection retention 12m
Impacted files/components:
- app/Services/Telemetry/IngestionService.php
- app/Services/Telemetry/Normalizer/*
- app/Services/Telemetry/Sequencer.php
- app/Services/Telemetry/ProjectionOrdering.php
- app/Jobs/ProcessTelemetryEvent.php
- app/Jobs/RebuildTelemetryProjections.php

## 6. Reliability Scoring and Lifecycle
1. Implement locked weights: Success=1.00, Assisted=0.70, Degraded=0.50, Failed=0.00.
2. Enforce `skipped` as `control_flow`; exclude from weighted numerator and denominator.
3. Explicit scoring unit rule: reliability scoring occurs at `run_id` level only; retries/attempts never create additional denominator entries.
4. Enforce success contract checks before assigning Success.
5. Implement deterministic degraded triggers: schema violation, output contract mismatch, partial objective completion, fallback path execution, retry-recovery success, non-blocking guardrail violation.
6. Hard-fail mapping: `hard_fail=true` when `failure_class=hard_fail` or terminal `policy_blocked`/`guardrail_blocked`.
7. Assisted SLA behavior: assisted requires human approval metadata and verification within <=24h; expiry auto-reclassifies to Failed.
8. Lifecycle enforcement after each classification update: Run -> Classified -> Scored -> Aggregated -> Window Evaluated -> Breach Check -> Escalate -> Pause -> Investigate -> Resume.
9. Gate policy:
- evaluate rolling 14-day and rolling 50-run windows
- enforce stricter result
- threshold: weighted reliability >=95
- companion gate: degraded rate <=3
10. Burst override: immediate hard gate on 2 consecutive hard_fail or 3 hard_fail in rolling 24h.
11. Escalation wording and logic: trigger escalation when weighted reliability (stricter window) drops below 95 within rolling 24h.
Impacted files/components:
- app/Enums/RunClassification.php
- app/Enums/FailureClass.php
- app/Enums/FailureReasonCode.php
- app/Services/Reliability/ClassificationEngine.php
- app/Services/Reliability/ScoringEngine.php
- app/Services/Reliability/WindowEvaluator.php
- app/Services/Reliability/GateEnforcer.php
- app/Console/Commands/EnforceAssistedSlaExpiry.php

## 7. Cost Instrumentation and Governance
1. Build canonical cost calculator from `model_rate_cards` with provider/model/version lookup and deterministic token math.
2. Store billed provider cost separately and use it only for reconciliation/variance views.
3. Enforce monthly per-workflow budgets as authoritative policy key.
4. Keep daily/weekly windows alert-only.
5. Enforce guardrails:
- warn at 80 percent utilization
- hard-enforce at 100 percent utilization
- allow in-flight completion
- block new runs until authorized resume
6. Emit `budget_breach` reason code when enforcement triggers.
Impacted files/components:
- app/Services/Cost/CanonicalCostCalculator.php
- app/Services/Cost/BudgetPolicyEvaluator.php
- app/Services/Cost/CostRollupProjector.php
- app/Jobs/ProjectWorkflowCosts.php
- config/agent.php cost governance section

## 8. Authorization and Scope Enforcement
1. Implement RBAC capabilities:
- `central_on_call`: incident response and pause authority
- `workflow_owner_or_delegate`: resume approval authority
- `platform_admin`: override authority
2. Enforce policy checks on pause/resume/escalation resolution endpoints and corresponding UI actions.
3. Require `reason` for every manual override and persist complete audit row.
4. Expose audit trail via API and operator UI with actor, acted_at/timestamp, and state transition context.
Impacted files/components:
- app/Policies/WorkflowGovernancePolicy.php
- app/Providers/AuthServiceProvider.php
- app/Http/Controllers/Api/V1/WorkflowGovernanceController.php
- app/Models/ManualOverrideAudit.php

## 9. Failure and Retry Behavior
1. Runtime retry policy remains deterministic: 2 retries with backoff 30s then 2m plus jitter.
2. Ingestion retries use queue retry semantics with idempotent writes and no duplicate scoring side effects.
3. Assisted SLA expiry job reclassifies unresolved assisted runs to Failed.
4. Telemetry outage auto-protect:
- trigger on 3 consecutive `telemetry_unobservable` or `telemetry_delayed` runs for same workflow, or 15m continuous outage
- auto-pause new runs
- page central on-call
5. Preserve in-flight completion during any pause/gate action.
Impacted files/components:
- app/Jobs/RunAgentWorkflowJob.php
- app/Jobs/ProcessTelemetryEvent.php
- app/Services/Reliability/GateEnforcer.php
- app/Services/Alerting/*

## 10. Observability and Operations
1. Add operational metrics:
- ingest_latency_ms
- projection_lag_seconds
- sequence_violation_rate
- unobservable_run_streak
- gate_flip_count
- escalation_open_duration
2. Add structured logs for classifier decisions, gate triggers, pause/resume actions, and budget enforcement decisions.
3. Add reliability/cost/escalation traces in job pipeline to support deterministic incident replay.
4. Expose observability summaries in operator-facing surfaces:
- `/agent/system-overview` panels for ingestion health and gate health
- `/agent/escalations` detail includes trigger evidence and projection lag at trigger time
5. Add alert routing controls for central on-call and workflow owners with suppression windows for duplicate alert storms.
Impacted files/components:
- app/Services/Observability/*
- app/Jobs/* instrumentation hooks
- resources/js/Pages/Agent/SystemOverview/Show.vue
- resources/js/Pages/Agent/Escalations/Index.vue

## 11. User and Operator Surface Exposure
1. Add operator pages/routes:
- /agent/deployments
- /agent/deployments/{workflowKey}
- /agent/escalations
- /agent/budgets
- /agent/system-overview
2. Add in-app navigation discoverability:
- top-level `Agent Deployments` nav for authorized users
- contextual links from run detail to workflow health, attempts lineage, and escalation history
- breadcrumbs connecting deployments, workflow detail, escalations, budgets, and system overview
3. Render required per-workflow dashboard contract widgets: ReliabilityScore, DegradedRate, HardFailRate, BudgetUtilization, EscalationEvents.
4. Add lifecycle ribbon: Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop.
5. Discoverability acceptance checks:
- authorized user reaches each page from main nav in <=2 clicks
- run detail includes direct workflow health link
- governance actions only visible when policy allows
- each operator action creates visible audit trail entry
Impacted files/components:
- routes/web.php
- app/Http/Controllers/* web controllers
- resources/js/Pages/Agent/Deployments/Index.vue
- resources/js/Pages/Agent/Deployments/Show.vue
- resources/js/Pages/Agent/Escalations/Index.vue
- resources/js/Pages/Agent/Budgets/Index.vue
- resources/js/Pages/Agent/SystemOverview/Show.vue
- resources/js/Layouts/* navigation components

## 12. Core/Beta/Experimental Stability Matrix
1. Publish explicit matrix in docs and admin page.
2. Core: scheduler dispatch, run state machine, policy guardrails, telemetry ledger ingest, reliability classifier/scorer, budget enforcement, escalation/pause/resume governance.
3. Beta: deployment counting automation, optimization-loop analytics, billed-cost variance reconciliation views.
4. Experimental: advanced delegation recovery heuristics, non-essential messenger automations, non-critical workflow templates.
5. Enforce matrix via feature flags and policy checks before surface exposure.
Impacted files/components:
- docs/system-overview.md
- PROJECT-STATUS.md
- config/agent.php feature matrix flags
- resources/js/Pages/Agent/SystemOverview/Show.vue

## 13. Documentation Alignment and Canonical Narrative
1. Build and maintain docs drift register against implementation reality, including org/messenger/feature-flag state.
2. Update `PROJECT-STATUS.md` as authoritative implementation truth.
3. Align `README.md` to canonical system overview link and locked positioning statement.
4. Document identity conventions (`workflow_key`, `run_id`, `run_attempt_id`) and scoring semantics in canonical docs.
5. Add docs governance rule requiring docs updates in same change set for runtime contract changes.
6. Add PR template checklist items for docs parity, operator discoverability, and API compatibility checks.
Impacted files/components:
- PROJECT-STATUS.md
- README.md
- docs/system-overview.md
- .github/pull_request_template.md

## 14. Phase 1 Engineering Workflow Templates
1. Ensure template registry includes and enables controls by default:
- eng.repo-analysis.v1
- eng.code-implementation.v1
- eng.pr-quality-gate.v1
- eng.dependency-update-triage.v1
- eng.release-readiness.v1
2. For each template, enforce telemetry v1 envelope emission, classification hooks, cost tracking, escalation hooks, and budget policy binding.
3. Add template readiness checks before activation: telemetry schema compliance, scoring enabled, cost policy attached, governance roles bound.
Impacted files/components:
- config/agent.php templates section
- app/Services/WorkflowTemplates/*
- database/seeders/* template metadata

## 15. Backward Compatibility Strategy
1. Keep schema registry with major-version breaking change rules and compatibility ingest adapters for prior major.
2. Maintain dual-write from existing `AgentRunEvent` to telemetry ledger during transition; ledger remains scoring source.
3. Add replay command to reconstruct projections from ledger and verify determinism.
4. Keep existing API consumer contracts stable through additive fields and serializers.
5. Run-attempt migration policy: populate `run_attempts` projection from ledger replay; reject direct writes outside projector services.
6. Keep manual override API compatibility by accepting/returning `timestamp` while persisting `acted_at` as internal canonical field.
Impacted files/components:
- app/Services/Telemetry/VersionedSchemaRegistry.php
- app/Services/Telemetry/LegacyAdapter.php
- app/Console/Commands/ReplayTelemetryLedger.php
- app/Transformers/*
- app/Services/Telemetry/RunAttemptProjector.php

## 16. Rollout and Rollback Controls
1. Add feature flags:
- agent.telemetry_v1_ingest
- agent.reliability_v1_scoring
- agent.cost_governance_v1
- agent.deployment_counting_v1
- agent.operator_dashboard_v1
2. Enable sequence by dependency:
- telemetry ingest
- normalization/projections
- scoring/gates
- cost enforcement
- deployment counting
- operator surfaces
3. Rollback controls:
- disable downstream flags first (dashboard -> counting -> cost -> scoring)
- keep ledger ingest active unless storage incident mandates stop
- rerun projections after rollback stabilization to re-derive state from ledger
Impacted files/components:
- config/agent.php
- app/Support/FeatureFlags/*
- app/Console/Commands/* flag tooling

## 17. Test Strategy
1. Unit tests:
- classification mapping for all failure_class and failure_reason_code paths
- weighted reliability formula with skipped exclusion
- stricter-window selection logic
- hard-fail burst detection
- canonical cost calculations and estimation flags
- run_id-only denominator enforcement when multiple attempts exist
2. Feature tests:
- ingest idempotency on duplicate (event_id, run_attempt_id)
- out-of-order sequence acceptance with `sequence_violation` marking
- pause/resume authorization and mandatory reason persistence
- assisted SLA expiry auto-reclassification
- budget enforcement with in-flight completion and new-run blocking
- workflow_key route binding and API response compatibility
3. Integration tests:
- end-to-end run -> ingest -> classification -> scoring -> gate -> escalation -> pause -> resume
- replay reproduces identical reliability/health state
- outage policy triggers auto-pause and alerting
- projection determinism with randomized ledger insert order
- late-arriving event convergence: ingest 1,2,4,5 then 3 and confirm deterministic final state and no double-count scoring
4. UI tests:
- operator page route access and nav discoverability checks
- dashboard metric rendering per workflow
- governance action visibility by role
- audit trail visibility after pause/resume overrides
5. Data integrity tests:
- immutable ledger updates rejected
- append-only run transition enforcement
- scoring and health derivable solely from replay
6. CI quality gates:
- Pint
- static analysis (PHPStan or Psalm per repository standard)
- Pest test suite
- coverage threshold gate configured and enforced
Impacted files/components:
- tests/Unit/Telemetry/*
- tests/Unit/Reliability/*
- tests/Unit/Cost/*
- tests/Feature/Agent/*
- tests/Integration/DeploymentReliability/*
- tests/Feature/AgentUi/*
- CI workflow config in .github/workflows/*

## 18. Ordered Task DAG
1. D1: Establish canonical docs baseline and drift register.
Dependencies: none.
2. D2: Define workflow identity contract (`workflow_key`) and schema registry rules.
Dependencies: D1.
3. D3: Ship telemetry ledger migrations/models including sequencing violation markers.
Dependencies: D2.
4. D4: Implement ingest service with idempotent dedupe and out-of-order acceptance policy.
Dependencies: D3.
5. D5: Build deterministic normalizers/provider adapters and estimation flag handling.
Dependencies: D4.
6. D6: Build run_attempt projection pipeline and rebuild tooling; block non-projector writes.
Dependencies: D3, D5.
7. D7: Implement failure taxonomy enums and classification engine.
Dependencies: D5.
8. D8: Implement run-level scoring engine, stricter-window evaluator, and burst overrides.
Dependencies: D7.
9. D9: Implement escalation engine and pause/protect controls.
Dependencies: D8.
10. D10: Implement assisted SLA expiry enforcement and reclassification job.
Dependencies: D7.
11. D11: Implement canonical cost calculator, rate cards, budget policies, and enforcement.
Dependencies: D5.
12. D12: Implement deployment counting state machine and compliance hold logic.
Dependencies: D8, D11, D9.
13. D13: Implement operator APIs for health, cost, escalations, governance actions.
Dependencies: D9, D11, D12.
14. D14: Implement operator web pages/routes/navigation/discoverability and audit views.
Dependencies: D13.
15. D15: Add template controls and readiness checks for five engineering workflows.
Dependencies: D8, D11, D9.
16. D16: Implement replay tooling, dual-write adapters, and compatibility serializers.
Dependencies: D3, D5, D6.
17. D17: Implement observability metrics/logging/alerts and system-overview exposure.
Dependencies: D9, D11, D13.
18. D18: Complete unit/feature/integration/UI/data-integrity tests and CI gates.
Dependencies: D4 through D17.
19. D19: Publish final docs set: PROJECT-STATUS, README, system-overview, stability matrix, scorecard protocol.
Dependencies: D14, D15, D18.

## 19. Acceptance Closure Mapping
1. Telemetry closure: envelope and identity fields implemented, idempotent dedupe active, out-of-order accepted with sequence_violation flag, append-only ledger verified, deterministic normalization and estimation flags active, delayed markers and outage auto-protect active.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run_id scoring semantics, weighted formula, stricter-window gate, degraded companion gate, hard-fail burst override, and weighted reliability breach escalation rule implemented.
3. Governance closure: escalation and auto-protect active, RBAC pause/resume authority contract enforced, manual override audits persisted with actor/state/reason and acted_at+timestamp compatibility.
4. Cost closure: canonical enforcement cost active, billed-cost reconciliation separated, monthly budget enforcement and 80/100 thresholds active.
5. Operator closure: dashboard metrics and lifecycle ribbon visible per workflow, pages/routes/navigation discoverable, governance actions permission-gated, audit entries visible in UI.
6. Deployment closure: counted deployment contract enforced, optimization loop measurable, five engineering templates runnable with controls enabled.
7. Documentation closure: drift register resolved, PROJECT-STATUS and README aligned to canonical system overview and locked positioning statement.

## Sections

- Scope Boundary
- Architecture Changes
- Data Model and Migrations
- API and Tool Contracts
- Event Contracts and Ingestion Semantics
- Reliability Scoring and Lifecycle
- Cost Instrumentation and Governance
- Authorization and Scope Enforcement
- Failure and Retry Behavior
- Observability and Operations
- User and Operator Surface Exposure
- Core/Beta/Experimental Stability Matrix
- Documentation Alignment and Canonical Narrative
- Phase 1 Engineering Workflow Templates
- Backward Compatibility Strategy
- Rollout and Rollback Controls
- Test Strategy
- Ordered Task DAG
- Acceptance Closure Mapping


## Risks

- Legacy workflow identity references may remain `workflow_id`-centric in older services and cause partial join failures after `workflow_key` standardization.
- Compatibility handling for `acted_at` and `timestamp` can diverge if serializers/controllers do not enforce a single mapping rule.
- `run_attempts` projection can silently drift if projector lag monitoring is missing or rebuild procedures are not routinely validated.
- Out-of-order acceptance can hide producer sequencing defects unless `sequence_violation` rates are surfaced and alerted.
- Dual-write transition can create inconsistent operator views if old and new projections are read by different endpoints.
- Stricter-window gating may trigger unexpected pauses for low-volume workflows if denominator population is not clearly communicated in UI.
- Hard-fail burst overrides may cause repeated pause/resume cycles without escalation deduplication and clear incident ownership.
- Deterministic cost estimation may diverge from billed totals enough to erode trust if variance reporting is not visible and explainable.
- RBAC role mapping may not align with existing org/user structures, leading to blocked approvals or over-privileged overrides.
- Replay determinism can break if normalizer behavior depends on mutable configuration not version-pinned by schema registry.
- Operator actions may remain hard to interpret if escalation pages lack trigger evidence and related run lineage context.
- Documentation parity can regress unless PR checks enforce drift-register updates for each contract or schema change.


## Assumptions

- Laravel 12 + Jetstream + Inertia remains the runtime/control-plane foundation.
- PostgreSQL is available for append-only ledger persistence and projection storage.
- Redis + Horizon remains available for ingestion, projection, classification, and enforcement jobs.
- Existing execution guardrails (CommandPolicy, PathPolicy, EnvPolicy) remain authoritative and unchanged in this phase.
- Workflow templates can be uniquely and stably identified by `workflow_key` values.
- Existing run lifecycle events can supply or be adapted to supply `run_id`, `run_attempt_id`, and sequencing metadata.
- Feature-flag framework supports independent toggling of telemetry, scoring, cost governance, deployment counting, and operator UI surfaces.
- Central on-call paging channel exists and can receive platform-generated escalation events.
- Provider adapters can deterministically normalize required telemetry fields for CLI and API providers.
- Manual override actions have authenticated actor context available at write time.
- Existing operator UI stack can add new pages/nav and policy-gated actions without framework changes.
- PROJECT-STATUS.md, README.md, and docs/system-overview.md are part of standard review workflow and can be updated with implementation changes.

