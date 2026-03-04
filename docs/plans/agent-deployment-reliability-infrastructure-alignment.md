# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve current Laravel local-first runtime, queue topology, scheduler dedupe/watermark behavior, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature-flag architecture.
2. In scope: telemetry v1 ledger + normalization, reliability scoring v1, cost governance service, escalation/pause/resume governance, deployment counting contract, operator dashboard surfaces, docs alignment, and engineering workflow templates.
3. Out of scope for this plan: new messenger channels, new model providers, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Enforce single external positioning string in operator-facing docs and UI copy: We help companies deploy AI agents safely and keep them reliable in production.
Impacted components: config/agent.php, docs/, README.md, PROJECT-STATUS.md, app/ domain services.

## 2. Architecture Changes
1. Add Telemetry domain as canonical source of truth: append-only ledger + async projections.
2. Add Reliability domain: deterministic classifier, scorer, gate evaluator, and escalation engine at run_id granularity.
3. Add Cost Governance domain: canonical token-cost service, monthly workflow budgets, enforcement controls, billed-cost reconciliation.
4. Add Deployment Health domain: per-workflow projections for ReliabilityScore, DegradedRate, HardFailRate, BudgetUtilization, EscalationEvents.
5. Add System Narrative domain: canonical system-overview document used by README and PROJECT-STATUS.
Impacted files/components:
- app/Services/Telemetry/*
- app/Services/Reliability/*
- app/Services/Cost/*
- app/Services/DeploymentHealth/*
- app/Models/* new projection and policy models
- app/Console/Commands/* and app/Jobs/* for ingestion/projection jobs

## 3. Data Model and Migrations
1. Create telemetry ledger table (immutable, append-only): telemetry_event_ledger.
- Required columns: id, event_id, schema_name, schema_version, run_id, run_attempt_id, parent_run_id, workflow_id, agent_id, provider, model, prompt_version, event_type, event_ts, sequence_no, payload_json, estimated_flags_json, telemetry_delayed, ingested_at.
- Constraints/indexes: unique(event_id, run_attempt_id), index(run_id, sequence_no), index(workflow_id, event_ts), index(schema_name, schema_version).
2. Create run attempt lineage table: run_attempts.
- Columns: run_attempt_id, run_id, parent_run_id, retry_ordinal, branch_path, started_at, ended_at, terminal_state.
- Constraint: run_attempt_id PK; index(run_id).
3. Create run classification table: run_classifications.
- Columns: run_id PK, classification, failure_class, failure_reason_code, hard_fail, assisted_sla_deadline_at, assisted_sla_state, classified_at, classified_by, verification_passed, human_intervention.
- Include deterministic trigger evidence JSON for degraded mapping.
4. Create reliability snapshot tables:
- workflow_reliability_windows: workflow_id, evaluated_at, window_kind (14d|50run), weighted_reliability, degraded_rate, hard_fail_rate, gate_state.
- workflow_reliability_current: one row per workflow with stricter-window result and active gate.
5. Create escalation and governance tables:
- workflow_escalations: id, workflow_id, run_id, trigger_type, trigger_payload, status, opened_at, resolved_at.
- manual_override_audits: id, actor_id, timestamp, run_id, previous_state, new_state, reason.
6. Create cost tables:
- model_rate_cards: provider, model, effective_version, input_token_rate, output_token_rate, currency, effective_from, effective_to.
- workflow_budget_policies: workflow_id PK, monthly_budget_amount, warn_threshold_pct, enforce_threshold_pct, active.
- workflow_cost_rollups: workflow_id, period_key, canonical_cost_total, billed_cost_total, variance_total, utilization_pct.
7. Create deployment counting table:
- deployment_registrations: workflow_id PK, controls_enabled_at, gate_passed_at, compliance_hold_passed_at, counted_at, optimization_loop_count.
8. Migration sequencing (strict dependency order): ledger -> lineage -> classification -> windows -> escalation/override -> cost -> deployment counting.
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
1. Keep versioned API base under /agent/api/v1 with X-Agent-Api-Version header.
2. Add/extend endpoints:
- GET /agent/api/v1/workflows/{workflow}/health
- GET /agent/api/v1/workflows/{workflow}/reliability
- GET /agent/api/v1/workflows/{workflow}/cost
- GET /agent/api/v1/workflows/{workflow}/escalations
- POST /agent/api/v1/workflows/{workflow}/resume
- POST /agent/api/v1/workflows/{workflow}/pause
- GET /agent/api/v1/deployments/counting
- POST /agent/api/v1/telemetry/events (internal ingestion contract)
3. Resume/pause endpoints require override payload with actor_id, timestamp, run_id, previous_state, new_state, reason.
4. Runner emission contract update:
- Runners emit typed raw events only.
- Normalization/classification/costing happens server-side in central pipeline.
5. Backward compatible response strategy:
- Preserve existing fields.
- Add v1 reliability/cost/governance blocks under additive keys.
Impacted files/components:
- routes/api.php
- app/Http/Controllers/Api/V1/*
- app/Http/Requests/Agent/*
- app/Http/Middleware/AgentApiVersionHeader.php
- app/Support/Agent/* contract validators

## 5. Event Contracts and Ingestion Semantics
1. Enforce strict envelope on every telemetry event: schema_name and schema_version mandatory.
2. Canonical identity fields mandatory on every event: run_id, run_attempt_id, parent_run_id.
3. Ingestion guarantees:
- At-least-once ingest.
- Idempotent dedupe on (event_id, run_attempt_id).
- Monotonic per-run sequence validation via sequence_no.
- Immutable ledger writes only.
4. Normalization guarantees:
- Deterministic mapping from provider-native event payloads to canonical event taxonomy.
- Missing usage/cost fields estimated deterministically and flagged in estimated flags.
5. Outage behavior:
- Fail-open with durable buffering and retry.
- Mark telemetry_delayed when delayed ingest occurs.
- Auto-protect when unobservable condition threshold breaches.
6. Retention topology:
- Raw stream retention 7d.
- Normalized and aggregate projections retention 12m.
Impacted files/components:
- app/Services/Telemetry/IngestionService.php
- app/Services/Telemetry/Normalizer/*
- app/Services/Telemetry/Sequencer.php
- app/Jobs/ProcessTelemetryEvent.php
- app/Jobs/RebuildTelemetryProjections.php

## 6. Reliability Scoring, Classification, and Lifecycle
1. Implement canonical classification weights: Success=1.00, Assisted=0.70, Degraded=0.50, Failed=0.00.
2. Enforce skipped as control_flow and exclude skipped from weighted numerator/denominator.
3. Enforce explicit run success contract checks before assigning Success.
4. Implement deterministic degraded triggers:
- schema violation
- output contract mismatch
- partial objective completion
- fallback path execution
- retry-recovery success
- non-blocking guardrail violation
5. Implement hard-fail mapping rule:
- hard_fail true when failure_class=hard_fail OR policy_blocked/guardrail_blocked terminal outcome.
6. Implement assisted SLA behavior:
- assisted requires human approval metadata and verification within <=24h.
- SLA expiry auto-reclassifies to Failed.
7. Implement reliability evaluation lifecycle after each classification update:
Run -> Classified -> Scored -> Aggregated -> Window Evaluated -> Breach Check -> Escalate -> Pause -> Investigate -> Resume.
8. Implement stricter-window gate enforcement:
- evaluate 14d and 50run windows
- enforce lower-scoring result
- threshold >=95 weighted reliability
- degraded companion gate <=3
9. Implement hard-fail burst override:
- 2 consecutive hard_fail OR 3 hard_fail in rolling 24h triggers immediate hard gate.
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
1. Build canonical cost calculator from model_rate_cards with provider/model/version matching and deterministic token math.
2. Store provider billed cost separately; never use billed cost for enforcement logic.
3. Enforce monthly per-workflow budgets as authoritative key.
4. Implement alert-only subwindows for daily/weekly projections.
5. Enforce guardrails:
- warn at 80 percent utilization
- hard-enforce at 100 percent utilization
- allow in-flight completion
- block new runs until approved resume.
6. Emit budget_breach reason code into failure taxonomy when enforcement triggers.
Impacted files/components:
- app/Services/Cost/CanonicalCostCalculator.php
- app/Services/Cost/BudgetPolicyEvaluator.php
- app/Services/Cost/CostRollupProjector.php
- app/Jobs/ProjectWorkflowCosts.php
- config/agent.php cost governance section

## 8. Authorization and Scope Enforcement
1. Implement RBAC capabilities for governance actions:
- central_on_call: incident response and pause authority
- workflow_owner_or_delegate: resume approval authority
- platform_admin: override authority
2. Enforce policy checks on pause/resume/escalation resolution endpoints.
3. Make reason mandatory for all manual overrides and persist audit row.
4. Expose audit trail in operator UI and API.
Impacted files/components:
- app/Policies/WorkflowGovernancePolicy.php
- app/Providers/AuthServiceProvider.php
- app/Http/Controllers/Api/V1/WorkflowGovernanceController.php
- app/Models/ManualOverrideAudit.php

## 9. Failure and Retry Behavior
1. Runtime retry policy for execution stays deterministic at 2 retries with exponential backoff 30s and 2m plus jitter.
2. Ingestion retry policy uses queue retries with idempotent writes.
3. Assisted SLA expiry job reclassifies unresolved assisted runs to Failed.
4. Telemetry outage auto-protect:
- trigger on 3 consecutive telemetry_unobservable or telemetry_delayed runs for same workflow OR 15m continuous outage
- auto-pause new runs
- page central on-call
5. Preserve in-flight run completion during any gate pause.
Impacted files/components:
- app/Jobs/RunAgentWorkflowJob.php
- app/Jobs/ProcessTelemetryEvent.php
- app/Services/Reliability/GateEnforcer.php
- app/Services/Alerting/*

## 10. User and Operator Surface Exposure
1. Add operator pages and navigation entries (not API-only completion):
- /agent/deployments
- /agent/deployments/{workflow}
- /agent/escalations
- /agent/budgets
- /agent/system-overview
2. Add in-app navigation discoverability:
- top-level Agent Deployments nav entry for authorized users
- contextual links from run detail to workflow health and escalation history
- breadcrumb paths between deployments, workflow detail, escalations, and budget pages
3. Add UI widgets for required dashboard contract per workflow:
- ReliabilityScore
- DegradedRate
- HardFailRate
- BudgetUtilization
- EscalationEvents
4. Add deployment lifecycle status ribbon:
Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop.
5. Discoverability acceptance checks:
- authorized user can reach each page from main nav in <=2 clicks
- run detail exposes direct health link
- governance actions are visible only with policy permission
- all operator actions create visible audit trail entries
Impacted files/components:
- routes/web.php
- app/Http/Controllers/* web controllers
- resources/js/Pages/Agent/Deployments/Index.vue
- resources/js/Pages/Agent/Deployments/Show.vue
- resources/js/Pages/Agent/Escalations/Index.vue
- resources/js/Pages/Agent/Budgets/Index.vue
- resources/js/Pages/Agent/SystemOverview/Show.vue
- resources/js/Layouts/* navigation components

## 11. Core/Beta/Experimental Stability Matrix
1. Produce explicit classification matrix and publish in docs + admin page.
2. Core (production ready): scheduler dispatch, run state machine, policy guardrails, telemetry ledger ingest, reliability classifier/scorer, budget enforcement, escalation/pause/resume governance.
3. Beta (stable evolving): deployment counting automation, optimization loop analytics, billed-cost variance reconciliation views.
4. Experimental (internal only): advanced delegation recovery heuristics, non-essential messenger automations, non-critical workflow templates.
5. Gate release behavior by matrix class via feature flags and policy checks.
Impacted files/components:
- docs/system-overview.md
- PROJECT-STATUS.md
- config/agent.php feature matrix flags
- resources/js/Pages/Agent/SystemOverview/Show.vue

## 12. Documentation Alignment and Canonical Narrative
1. Build docs drift register comparing actual implementation vs docs claims (including org/messenger/feature-flag state).
2. Update PROJECT-STATUS.md as authoritative implementation truth.
3. Align README.md to canonical System Overview link and external USP.
4. Add docs governance rule: docs updates required in same change set for runtime contract changes.
5. Add acceptance checklist in PR template for docs parity.
Impacted files/components:
- PROJECT-STATUS.md
- README.md
- docs/system-overview.md
- .github/pull_request_template.md

## 13. Phase 1 Engineering Workflow Templates
1. Ensure template registry includes and enables controls by default:
- eng.repo-analysis.v1
- eng.code-implementation.v1
- eng.pr-quality-gate.v1
- eng.dependency-update-triage.v1
- eng.release-readiness.v1
2. For each template: enforce telemetry v1 envelope, run classification hooks, cost tracking, escalation hooks, and budget policy binding.
3. Add template readiness checks before workflow activation.
Impacted files/components:
- config/agent.php templates section
- app/Services/WorkflowTemplates/*
- database seeders for template metadata

## 14. Backward Compatibility Strategy
1. Introduce schema versioning contract:
- major bump for breaking changes
- compatibility window supports previous major in ingest adapter
2. Dual-write period for existing AgentRunEvent and new telemetry ledger; ledger is scoring source, old stream remains readable during transition.
3. Build replay command to reconstruct projections from ledger and verify determinism.
4. Keep existing API consumers stable via additive response fields and fallback serializers.
Impacted files/components:
- app/Services/Telemetry/VersionedSchemaRegistry.php
- app/Services/Telemetry/LegacyAdapter.php
- app/Console/Commands/ReplayTelemetryLedger.php
- app/Transformers/*

## 15. Rollout and Rollback Controls
1. Add feature flags:
- agent.telemetry_v1_ingest
- agent.reliability_v1_scoring
- agent.cost_governance_v1
- agent.deployment_counting_v1
- agent.operator_dashboard_v1
2. Rollout sequence by dependency:
- enable telemetry ingest
- enable normalization projections
- enable scoring/gates
- enable cost enforcement
- enable deployment counting
- expose operator pages
3. Rollback controls:
- disable downstream flags first (dashboard -> counting -> cost -> scoring)
- keep ledger ingest on unless storage incident requires hard stop
- maintain append-only ledger integrity and rerun projections after rollback stabilization
Impacted files/components:
- config/agent.php
- app/Support/FeatureFlags/*
- app/Console/Commands/* toggle helpers

## 16. Test Strategy
1. Unit tests:
- classifier mapping for all failure_class and failure_reason_code paths
- weighted reliability formula and skipped exclusion
- stricter-window selection logic
- hard-fail burst detection logic
- canonical cost calculations and estimation flags
2. Feature tests:
- ingest endpoint idempotency on duplicate (event_id, run_attempt_id)
- monotonic sequence rejection/handling
- pause/resume authorization and mandatory reason persistence
- assisted SLA expiry auto-reclassification
- budget enforcement behavior with in-flight completion and new-run block
3. Integration tests:
- end-to-end run -> event ingest -> classification -> scoring -> gate -> escalation -> pause -> resume
- replay ledger reproduces identical health state
- outage policy triggers auto-pause and alerting behavior
4. UI tests:
- operator pages route access and navigation discoverability checks
- dashboard metrics render per workflow
- audit trail visibility for governance actions
5. Data integrity verification tests:
- immutable event updates rejected
- append-only run transitions enforced
- scoring and health derivable solely from replay
Impacted files/components:
- tests/Unit/Telemetry/*
- tests/Unit/Reliability/*
- tests/Unit/Cost/*
- tests/Feature/Agent/*
- tests/Integration/DeploymentReliability/*
- tests/Feature/AgentUi/*

## 17. Ordered Task DAG
1. D1: Establish canonical docs baseline and drift register.
Dependencies: none.
2. D2: Define schema registry and telemetry v1 envelope contracts.
Dependencies: D1.
3. D3: Ship ledger + lineage migrations and models.
Dependencies: D2.
4. D4: Build ingestion service with idempotent dedupe and monotonic sequencing.
Dependencies: D3.
5. D5: Build normalization pipeline and provider adapters with estimation flags.
Dependencies: D4.
6. D6: Implement failure taxonomy enums and classification engine.
Dependencies: D5.
7. D7: Implement run-level scoring engine, window evaluator, hard-fail overrides.
Dependencies: D6.
8. D8: Implement escalation engine and pause/protect controls.
Dependencies: D7.
9. D9: Implement assisted SLA expiry reclassification command/job.
Dependencies: D6.
10. D10: Implement canonical cost calculator, rate cards, budget policies, enforcement.
Dependencies: D5.
11. D11: Implement deployment counting state machine and compliance hold logic.
Dependencies: D7, D10, D8.
12. D12: Build operator APIs for health, cost, escalations, governance actions.
Dependencies: D8, D10, D11.
13. D13: Build operator web pages/routes/navigation and discoverability links.
Dependencies: D12.
14. D14: Add template controls for five engineering workflows.
Dependencies: D7, D10, D8.
15. D15: Add replay tooling and backward compatibility adapters.
Dependencies: D3, D5.
16. D16: Complete full test suite (unit/feature/integration/UI/data-integrity).
Dependencies: D4 through D15.
17. D17: Final docs publication: PROJECT-STATUS, README, system-overview, stability matrix, scorecard protocol.
Dependencies: D13, D14, D16.

## 18. Acceptance Closure Mapping
1. Telemetry closure: envelope, identity fields, dedupe, monotonic sequence, append-only ledger, deterministic normalization, estimation flags, delayed markers, outage auto-protect.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run_id scoring semantics, weighted formula, stricter-window gate, degraded companion gate, hard-fail burst overrides.
3. Governance closure: escalation and auto-protect, RBAC pause/resume authority contract, mandatory manual override audit schema.
4. Cost closure: canonical enforcement cost, billed-cost reconciliation separation, monthly budget enforcement, 80/100 thresholds.
5. Operator closure: dashboard contract metrics present, deployment lifecycle states visible, discoverable routes/pages/nav, audit visibility.
6. Deployment closure: counted deployment contract enforced, optimization loop measurable, five seed engineering templates runnable with controls enabled.
7. Documentation closure: drift resolved, PROJECT-STATUS and README aligned to canonical system-overview and locked positioning.

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

- Legacy status mappings may conflict with canonical failure taxonomy, causing inconsistent classification during transition.
- Dual-write period can introduce divergence between AgentRunEvent history and telemetry ledger projections if adapters are incomplete.
- run_attempt_id lineage may be incorrectly treated as scored units by downstream consumers, inflating reliability denominator.
- Monotonic sequencing enforcement can drop or quarantine valid late-arriving provider events if ordering policy is too strict.
- Deterministic cost estimation for missing usage fields can drift from billed reality and trigger stakeholder trust issues unless variance is transparent.
- RBAC role mapping for central_on_call, workflow owner/delegate, and platform admin may not cleanly match existing identity model.
- Auto-protect triggers can cause over-pausing if telemetry_delayed noise is high, impacting workflow throughput.
- Hard-fail burst overrides can create frequent gate flips without clear operator triage UX, leading to alert fatigue.
- Replay determinism can fail if normalization logic depends on mutable external configuration not version-pinned in events.
- Operator UI may expose governance actions without enough contextual diagnostics, increasing unsafe resume decisions.
- Template standardization may uncover hidden workflow-specific assumptions that break when controls are enforced by default.
- Documentation parity can regress after launch without enforcement in PR workflow and ownership controls.


## Assumptions

- Current Laravel 12 + Jetstream + Inertia stack remains the execution and control-plane foundation.
- PostgreSQL remains available as the durable system of record for append-only telemetry ledger and projections.
- Redis + Horizon remains the queue runtime for ingestion, projection, and enforcement jobs.
- Existing policy guardrails (CommandPolicy, PathPolicy, EnvPolicy) stay authoritative for execution gating.
- Workflow identity and ownership metadata can be resolved for all Phase 1 engineering templates.
- Feature-flag framework is available and can gate new telemetry, scoring, cost, and UI surfaces independently.
- Alerting channel for central on-call paging exists and can receive escalation events from platform services.
- Provider adapters can access enough raw metadata to populate canonical envelope and estimation flags deterministically.
- Existing run event production can be extended to emit run_attempt_id and sequence_no without runtime rewrite.
- Manual override actor identity is available at governance action time for complete audit row persistence.
- Deployment counting and optimization-loop updates can be derived from ledger-driven projections without bespoke per-client logic.
- Documentation files PROJECT-STATUS.md, README.md, and canonical system overview are writable and part of normal review flow.

