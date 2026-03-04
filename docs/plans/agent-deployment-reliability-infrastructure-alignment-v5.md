# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve the existing Laravel local-first runtime, queue topology, scheduler dedupe and watermark behavior, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature-flag architecture.
2. Keep Phase 1 scope to telemetry v1 ledger plus deterministic normalization, reliability scoring v1, cost governance, escalation and pause and resume governance, deployment counting contract, operator dashboard exposure, docs alignment, and engineering workflow templates.
3. Keep out of scope: new messenger surfaces, new provider onboarding work, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Lock external positioning copy to: 'We help companies deploy AI agents safely and keep them reliable in production.'
5. Enforce `workflow_key` everywhere with regex `^[a-z0-9._-]+[.]v[1-9][0-9]*$` and Postgres `CHECK` on every table containing `workflow_key`.
6. Keep API changes additive inside existing versioned route groups and serializers.
7. Apply a single server-side UTC time-source policy for all buckets, SLA calculations, dedupe windows, and projection lag calculations; client timestamps are audit-only.
Impacted components: `config/agent.php`, `app/Support/Agent/*`, `app/Support/Time/*`, `routes/*`, `database/migrations/*`, `docs/*`, `README.md`, `PROJECT-STATUS.md`.

## 2. Architecture Changes
1. Keep telemetry ledger as canonical truth with append-only semantics and deterministic async projections.
2. Pin normalization metadata per ledger row: `schema_hash`, `normalizer_version`, `registry_revision`.
3. Keep scoring at `run_id` granularity; `run_attempt_id` remains retry and branch lineage only.
4. Standardize governance enums at DB and PHP layers: `gate_state` (`pass|warn|fail|paused|insufficient_data`) and `countability_state` (`countable|not_countable|insufficient_data`).
5. Require projection lineage metadata on all projection rows: `projection_version`, `source_high_watermark_id`, and `source_high_watermark_ingested_at`.
6. Centralize escalation trigger taxonomy in config with explicit blocking and non-blocking lists; do not duplicate logic across services.
7. Keep projection tables write-protected to projector services only.
8. Keep low-volume behavior deterministic: `gate_state=insufficient_data`, no auto-pause from insufficient-data alone, non-countable deployment.
Impacted components: `app/Services/Telemetry/*`, `app/Services/Reliability/*`, `app/Services/Cost/*`, `app/Services/DeploymentHealth/*`, `app/Support/Time/AgentClock.php`, `config/agent.php`.

## 3. Data Model and Migrations
1. Create Postgres enum types plus PHP enums:
- `agent_gate_state_enum`: `pass,warn,fail,paused,insufficient_data`.
- `agent_countability_state_enum`: `countable,not_countable,insufficient_data`.
- `agent_sequence_violation_reason_enum`: `decrease,duplicate,gap_at_terminalization`.
2. Create append-only `telemetry_event_ledger` with canonical envelope, pinned normalizer fields, identity fields, observability flags, and ingest timing fields.
3. Add explicit sequence reason constraint: `CHECK ((sequence_violation = false AND sequence_violation_reason IS NULL) OR (sequence_violation = true AND sequence_violation_reason IS NOT NULL))`.
4. Add rule constraint for terminalization reason: `sequence_violation_reason='gap_at_terminalization'` is allowed only for projector-generated terminalization audit events.
5. Keep `run_attempts` projection with `terminal_sequence_gap_detected` as attempt-level summary flag and `projection_version` plus high-watermark fields.
6. Keep `run_classifications` with canonical outcome fields, assisted SLA fields, and degraded evidence payload.
7. Keep reliability tables `workflow_reliability_windows` and `workflow_reliability_current` with gate and projection lineage metadata.
8. Keep escalation tables with UTC day-bucket dedupe key `${workflow_key}:${trigger_type}:${YYYY-MM-DD}` and partial unique index for one open escalation per dedupe key.
9. Keep `manual_override_audits` with required fields and strict timestamp parity check between canonical column and compatibility mirror.
10. Keep cost tables `model_rate_cards`, `workflow_budget_policies`, `workflow_cost_rollups` with projection lineage fields.
11. Keep `deployment_registrations` with deterministic countability state and reasons.
12. Enforce deterministic countability transitions: insufficient-data when evidence threshold not met; not-countable when gate not pass, workflow paused, budget enforced, or blocking escalation open; countable only when gate pass and all controls satisfied and no blocking escalation open.
13. Migration sequence: enums and key remediation, ledger and constraints, projections, classifier tables, reliability tables, escalation and override tables, cost tables, deployment tables, indexes and final checks.
Impacted components: `database/migrations/*`, `app/Models/*`, `app/Enums/*`.

## 4. API and Tool Contracts
1. Extend `routes/api.php` additively under existing v1 group.
2. Validate `{workflowKey}` via canonical regex in request validators, route binding, and policy guard checks.
3. Provide and harden endpoints: `GET workflows/{workflowKey}/health`, `GET workflows/{workflowKey}/reliability`, `GET workflows/{workflowKey}/cost`, `GET workflows/{workflowKey}/escalations`, `POST workflows/{workflowKey}/pause`, `POST workflows/{workflowKey}/resume`, `GET deployments/counting`, `POST telemetry/events`.
4. Pause and resume payload requires `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`.
5. Expose canonical fields in responses: `gate_state`, `gate_state_reason`, `countability_state`, `countability_reason`, `projection_version`, `source_high_watermark_id`, `source_high_watermark_ingested_at`, `telemetry_delayed`, `telemetry_unobservable`, `unobservable_reason`.
6. Keep runner contract as raw typed emission only; ingestion, normalization, classification, and scoring remain server-side.
Impacted components: `routes/api.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Requests/Agent/*`, `app/Support/Agent/*`.

## 5. Event Contracts and Ingestion Semantics
1. Require canonical envelope fields on every event: `schema_name`, `schema_version`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`.
2. Resolve schema registry metadata at ingest and pin `schema_hash`, `normalizer_version`, `registry_revision` immutably.
3. Keep ingest semantics: at-least-once delivery, idempotent dedupe by `(event_id, run_attempt_id)`, append-only ledger writes.
4. Enforce per-attempt sequence rules: decrease violation when `sequence_no` regresses; duplicate violation when same `sequence_no` maps to different `event_id`.
5. Terminalization gap rule: gap is computed when attempt terminalizes; authoritative attempt summary lands in `run_attempts.terminal_sequence_gap_detected`; projector also writes one synthetic terminalization audit ledger row carrying `sequence_violation_reason=gap_at_terminalization` when a gap exists.
6. Deterministic projection ordering: `sequence_no`, then `event_ts`, then `ingested_at`, then `ledger_id`.
7. Split observability semantics: delayed means reconstructable late telemetry; unobservable means required telemetry cannot be reconstructed.
8. Trigger delayed SLA breach when ingest latency exceeds configured threshold and record explicit flag.
9. Outage auto-protect trigger: configured unobservable streak, configured delayed-SLA-breach streak, or configured continuous ingest observability outage window.
10. Keep retention topology: short retention for raw ledger and long retention for normalized and aggregate projections.
Impacted components: `app/Services/Telemetry/IngestionService.php`, `app/Services/Telemetry/ProjectionOrdering.php`, `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Services/Telemetry/TerminalizationGapProjector.php`, `app/Jobs/ProcessTelemetryEvent.php`, `app/Jobs/RebuildTelemetryProjections.php`.

## 6. Reliability Scoring and Lifecycle
1. Implement locked classification weights: Success `1.00`, Assisted `0.70`, Degraded `0.50`, Failed `0.00`; skip remains control-flow neutral and excluded from scoring denominator and numerator.
2. Enforce success contract before Success classification: no hard fail, no terminal blocking guardrail or policy breach, objective verification pass, no human intervention.
3. Enforce deterministic degraded triggers: schema violation, output contract mismatch, partial objective completion, fallback execution, retry-recovery success, non-blocking guardrail violation.
4. Enforce hard-fail mapping: `failure_class=hard_fail` or terminal `policy_blocked` or `guardrail_blocked` outcome.
5. Enforce assisted SLA contract with deterministic expiry reclassification to Failed when verification deadline passes.
6. Evaluate windows after each classification update and compute both rolling windows; enforce stricter result.
7. Enforce gate thresholds and degraded companion threshold, plus hard-fail burst override.
8. Persist paused scheduler ticks as skipped with explicit reason and exclude from scoring denominator.
9. Define blocking escalation trigger types once in config and use those types for countability blocking and pause enforcement: `reliability_breach`, `hard_fail_burst`, `budget_enforced`, `telemetry_unobservable_outage`.
10. Define non-blocking triggers in config and ensure they alert without changing countability or pause state.
Impacted components: `app/Services/Reliability/*`, `app/Enums/*`, `app/Console/Commands/EnforceAssistedSlaExpiry.php`, `config/agent.php`, `app/Services/Scheduler/*`.

## 7. Cost Instrumentation and Governance
1. Build canonical cost calculator from internal versioned `model_rate_cards` and deterministic token accounting.
2. Store provider billed cost separately for reconciliation and variance only; billed values never drive enforcement.
3. Keep monthly per-workflow budget as enforcement key and treat shorter windows as alert-only.
4. Apply guardrails: warning threshold, enforcement threshold, in-flight completion allowed, new runs blocked until authorized resume.
5. Emit `budget_breach` with policy snapshot and route it through escalation engine using centralized trigger taxonomy.
6. Add stable anomaly groundwork fields in rollups for future statistical detection without contract churn.
Impacted components: `app/Services/Cost/*`, `app/Jobs/ProjectWorkflowCosts.php`, `config/agent.php`.

## 8. Authorization and Scope Enforcement
1. Enforce RBAC capabilities: `central_on_call` pause and incident response, `workflow_owner_or_delegate` resume approval, `platform_admin` override authority.
2. Policy-gate pause, resume, escalation resolution, and countability override actions in API and web controllers.
3. Require explicit reason on every governance action and persist full audit metadata.
4. Ensure all governance actions are traceable in UI and API with actor, timestamp, previous and new state, linked workflow and run context.
5. Prevent concurrent conflicting resolutions for one open dedupe key via transactional lock and unique open-escalation guard.
Impacted components: `app/Policies/WorkflowGovernancePolicy.php`, `app/Providers/AuthServiceProvider.php`, `app/Http/Controllers/Api/V1/WorkflowGovernanceController.php`, `app/Http/Controllers/Agent/*`, `app/Models/ManualOverrideAudit.php`.

## 9. Failure and Retry Behavior
1. Keep runtime retry behavior deterministic with configured retry count, backoff ladder, and jitter strategy.
2. Ensure ingestion retries stay idempotent and never create duplicate scored runs.
3. Run assisted-SLA expiry classifier as deterministic reconciliation job.
4. Auto-protect pauses new runs only; in-flight runs complete.
5. Keep scheduler pause behavior auditable through skipped reason coding.
6. Ensure failure and retry handlers consume centralized trigger taxonomy and centralized UTC time source.
Impacted components: `app/Jobs/RunAgentWorkflowJob.php`, `app/Jobs/ProcessTelemetryEvent.php`, `app/Services/Reliability/GateEnforcer.php`, `app/Services/Alerting/*`, `app/Services/Scheduler/*`, `app/Support/Time/AgentClock.php`.

## 10. Observability and Operations
1. Add metrics: `ingest_latency_ms`, `projection_lag_seconds`, `sequence_violation_rate`, delayed and unobservable streak metrics, gate flip metrics, escalation open duration, and projection high-watermark gap.
2. Define `projection_lag_seconds` deterministically as server UTC now minus `source_high_watermark_ingested_at` of the projection row; this measures projection staleness relative to ledger ingest time.
3. Use one server UTC time source for lag, SLA windows, outage windows, and UTC bucketing; never derive operational state from client timestamps.
4. Add structured logs for classifier decisions, dedupe decisions, gate transitions, escalation transitions, and budget enforcement.
5. Expose replay lineage fields (`projection_version`, high-watermark fields) in operator payloads and troubleshooting endpoints.
6. Operator pages: `/agent/system-overview` shows ingest health, delayed vs unobservable split, lag, and high-watermark recency; `/agent/escalations` shows dedupe key, trigger evidence, trigger type blocking status, and lag at trigger.
7. Alert routing supports central on-call and workflow-owner subscriptions with dedupe suppression keyed by dedupe key and trigger type.
Impacted components: `app/Services/Observability/*`, `app/Services/Alerting/*`, `app/Support/Time/AgentClock.php`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Pages/Agent/Escalations/Index.vue`.

## 11. User and Operator Surface Exposure
1. Add and verify web routes and pages: `/agent/deployments`, `/agent/deployments/{workflowKey}`, `/agent/escalations`, `/agent/budgets`, `/agent/system-overview`.
2. Ensure navigation discoverability: top-level `Agent Deployments`, workflow detail deep links to reliability, cost, attempts lineage, escalation history, and breadcrumbs across pages.
3. Render required workflow widgets: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
4. Render deployment lifecycle ribbon in workflow detail: created, beta, reliability observed, gate passed, deployment counted, optimization loop.
5. Render deterministic badges for `gate_state` and `countability_state` enum values and explicit copy `not countable (incident open)` when a blocking escalation is open.
6. Render delayed and unobservable statuses separately and show trigger rationale tooltips from canonical reason fields.
7. Expose governance controls only to authorized roles and show immediate audit-row confirmation after action.
8. Discoverability acceptance checks: every operator page reachable from main nav in two clicks or fewer for authorized users; run detail has direct workflow health link; governance visibility respects policy; every governance action creates visible audit evidence.
Impacted components: `routes/web.php`, `app/Http/Controllers/Agent/*`, `resources/js/Pages/Agent/Deployments/*`, `resources/js/Pages/Agent/Escalations/Index.vue`, `resources/js/Pages/Agent/Budgets/Index.vue`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Layouts/*`.

## 12. Core/Beta/Experimental Stability Matrix
1. Publish and enforce stability matrix in docs and admin surfaces.
2. Core includes scheduler dispatch and pause-skip audit behavior, run state machine, policy guardrails, telemetry ingest and schema pinning, classifier and scorer, budget enforcement, and escalation governance.
3. Beta includes deployment counting automation, optimization-loop analytics, billed-cost variance views.
4. Experimental includes advanced delegation heuristics and non-essential messenger automations.
5. Block UI and API exposure by feature flags and policy checks according to matrix class.
Impacted components: `docs/system-overview.md`, `PROJECT-STATUS.md`, `config/agent.php`, `resources/js/Pages/Agent/SystemOverview/Show.vue`.

## 13. Documentation Alignment and Canonical Narrative
1. Maintain docs drift register mapped to implementation reality including org, messenger, and feature-flag state.
2. Keep `PROJECT-STATUS.md` as canonical implementation truth and `README.md` as aligned entry narrative.
3. Publish canonical contracts in docs: workflow key regex, identity model, failure taxonomy, sequence rules, UTC bucketing, blocking trigger taxonomy, projection lag definition, countability transitions, and pause semantics.
4. Require docs updates in same change set for any runtime contract change.
5. Add PR checks for docs parity, route and page discoverability impact, additive API compatibility, and trigger taxonomy consistency.
Impacted components: `PROJECT-STATUS.md`, `README.md`, `docs/system-overview.md`, `.github/pull_request_template.md`.

## 14. Phase 1 Engineering Workflow Templates
1. Ensure template registry includes `eng.repo-analysis.v1`, `eng.code-implementation.v1`, `eng.pr-quality-gate.v1`, `eng.dependency-update-triage.v1`, `eng.release-readiness.v1`.
2. Enforce workflow-key regex validation at registry, seed, and runtime selection layers.
3. Enforce controls by default per template: telemetry envelope, schema pinning, classification hooks, cost tracking, escalation hooks, budget binding.
4. Add readiness gate per template: schema compliance, scoring enabled, cost policy attached, governance roles bound, countability initialized as insufficient-data.
Impacted components: `config/agent.php`, `app/Services/WorkflowTemplates/*`, `database/seeders/*`.

## 15. Backward Compatibility Strategy
1. Keep schema registry major-version compatibility rules and adapters.
2. Dual-write legacy events during transition; ledger remains scoring source of truth.
3. Backfill schema pin metadata for legacy events with deterministic derived markers.
4. Provide replay command to rebuild projections from ledger and verify deterministic parity by pinned metadata and high-watermark lineage.
5. Keep API compatibility via additive fields and serializer extensions.
6. Migrate run-attempt projections via replay and block direct writes outside projector services.
7. Keep manual-override payload compatibility by accepting and returning `timestamp` while persisting canonical `acted_at`.
8. Run workflow-key remediation script before enabling regex checks to prevent migration lockouts.
Impacted components: `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Services/Telemetry/LegacyAdapter.php`, `app/Console/Commands/ReplayTelemetryLedger.php`, `app/Transformers/*`, `app/Services/Telemetry/RunAttemptProjector.php`, `database/migrations/*`.

## 16. Rollout and Rollback Controls
1. Keep feature flags: `agent.telemetry_v1_ingest`, `agent.reliability_v1_scoring`, `agent.cost_governance_v1`, `agent.deployment_counting_v1`, `agent.operator_dashboard_v1`.
2. Enable in dependency order: ingest, normalization and projections, scoring and gates, cost enforcement, deployment counting, operator surfaces.
3. Roll back in reverse dependency order while preserving ledger ingest unless storage incident requires stop.
4. Recovery control: rerun projectors and replay from high-watermark checkpoints.
5. Pre-enable checks: enum availability in DB and PHP maps, workflow-key remediation completion, escalation dedupe key normalization, centralized trigger taxonomy loaded, centralized UTC clock service enabled.
Impacted components: `config/agent.php`, `app/Support/FeatureFlags/*`, `app/Console/Commands/*`, `app/Support/Time/*`.

## 17. Test Strategy
1. Unit coverage: classification mapping, weighted reliability math, skipped exclusion, stricter-window selection, hard-fail burst detection, canonical cost calculations, run-level denominator enforcement with multiple attempts, workflow-key regex validation, enum serialization, UTC bucket derivation, projection lag formula, and trigger taxonomy blocking vs non-blocking behavior.
2. Feature coverage: ingest dedupe by `(event_id, run_attempt_id)`, out-of-order ingest acceptance, explicit sequence reasons (`decrease`, `duplicate`, `gap_at_terminalization`), pause and resume authorization and audit persistence, assisted-SLA expiry reclassification, budget enforcement behavior, route binding and additive response compatibility, escalation dedupe enforcing one open record per dedupe key.
3. Integration coverage: run to ingest to classification to scoring to gate to escalation to pause to resume lifecycle, replay parity for reliability and health state, outage auto-protect behavior, projection determinism under randomized insert order, late-arrival convergence without double scoring, schema pin determinism across registry revisions, open blocking escalation preventing countability.
4. UI coverage: route access and nav discoverability, required workflow widgets rendering, role-based governance visibility, audit-trail visibility after overrides, insufficient-data rendering correctness, distinct delayed vs unobservable indicators, deployment detail rendering of `not countable (incident open)`.
5. Data-integrity coverage: immutable ledger enforcement, append-only run transition enforcement, scoring and health derivable from replay only, projection high-watermark monotonicity.
6. CI gates: formatting, static analysis, test suite, and coverage threshold enforcement.
Impacted components: `tests/Unit/*`, `tests/Feature/*`, `tests/Integration/*`, `tests/Feature/AgentUi/*`, `.github/workflows/*`.

## 18. Ordered Task DAG
1. D1: Lock canonical docs baseline, drift register schema, and positioning text.
2. D2: Implement workflow-key remediation and regex checks across all workflow-key tables.
3. D3: Introduce DB enums and PHP enums for gate state, countability state, and sequence-violation reasons.
4. D4: Ship telemetry ledger migration with explicit sequence constraints including reason-required check.
5. D5: Implement ingestion dedupe, schema pinning, ingest latency capture, and UTC-clock integration.
6. D6: Implement sequence violation detector and terminalization gap projector plus run-attempt summary flagging.
7. D7: Implement normalizer registry and pinned normalizer execution.
8. D8: Implement projection pipelines with mandatory projection lineage fields including high-watermark ingest timestamp.
9. D9: Implement failure taxonomy enums and classification engine.
10. D10: Implement run-level scorer, stricter-window evaluator, low-volume gate handling, and blocking-trigger-aware countability projection.
11. D11: Implement escalation engine with centralized trigger taxonomy, UTC day-bucket dedupe, and open-incident constraints.
12. D12: Implement outage detector with delayed vs unobservable split and auto-protect trigger logic.
13. D13: Implement pause and protect controls and scheduler paused-tick skip persistence.
14. D14: Implement assisted SLA expiry reconciliation job.
15. D15: Implement canonical cost service, budget policy projector, and enforcement actions.
16. D16: Implement deployment registration projector with deterministic countability transitions and incident-open block.
17. D17: Implement API endpoints and validators for health, reliability, cost, escalations, pause, resume, counting, and telemetry ingest.
18. D18: Implement web routes, pages, navigation, badges, lifecycle ribbon, discoverability checks, and role-gated governance controls.
19. D19: Implement replay tooling, dual-write adapters, and additive compatibility serializers.
20. D20: Implement observability dashboards, structured logs, alert routing, and projection lag display.
21. D21: Complete unit, feature, integration, UI, and data-integrity tests plus CI enforcement.
22. D22: Publish canonical docs and acceptance scorecard mapping to implemented controls.

## 19. Acceptance Closure Mapping
1. Telemetry closure: canonical envelope and identity fields, schema pinning, idempotent dedupe, explicit sequence-reason constraints, terminalization gap handling, append-only ledger, delayed vs unobservable semantics, and outage auto-protect behavior are active.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run-level scoring semantics, strict formula implementation, stricter-window gating, insufficient-data handling, hard-fail burst override, and escalation dedupe are active.
3. Governance closure: RBAC pause and resume authority enforced, manual override audits persisted with required fields, one-open-incident dedupe enforced, and blocking-trigger taxonomy consistently applied.
4. Cost closure: canonical enforcement cost active, billed-cost reconciliation separated, budget policy thresholds enforced, and rollups include projection lineage.
5. Deployment closure: deterministic countability transitions active, blocking incident prevents counting, lifecycle states visible in control plane, optimization loop counters measurable.
6. Operator closure: required routes and pages are reachable through navigation, required widgets and lifecycle ribbon are visible, enum statuses render correctly, governance controls are policy-gated, and audit entries are immediately visible post-action.
7. Documentation closure: drift register resolved and canonical docs align with implemented contracts, route and page discoverability, and positioning language.

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

- Existing rows with nonconforming `workflow_key` values can block regex-check migrations until remediation is complete and verified.
- DB enum additions can drift from PHP enum maps and break casts, validation, or serializer contracts if released out of sequence.
- If any service computes UTC buckets from local timezone or client timestamp, dedupe and escalation windows can diverge.
- If terminalization synthetic events are not generated consistently, `gap_at_terminalization` evidence can become incomplete between ledger and attempt projections.
- Central blocking-trigger taxonomy can drift if hardcoded values remain in legacy services instead of config-backed resolution.
- `projection_lag_seconds` can misreport if projections omit `source_high_watermark_ingested_at` or compute lag against inconsistent timestamps.
- Open escalation dedupe constraints can fail during migration when legacy open incidents do not have normalized dedupe keys.
- Dual-write transition can expose temporary endpoint disagreement if read paths mix legacy and ledger-backed projections without source pinning.
- Large variance between canonical and provider-billed cost can reduce operator trust without explicit reconciliation UI messaging.
- RBAC role seeding or policy wiring gaps can either over-permit override actions or block valid responders.
- Auto-protect triggers can cause unnecessary pauses if delayed and unobservable reason classification is inconsistent across ingestion and projector layers.
- Docs parity can regress without enforced PR checks that cover runtime contracts plus route and page discoverability requirements.


## Assumptions

- PostgreSQL remains the source-of-record store and supports required enum types, `CHECK` constraints, partial unique indexes, and transactional DDL strategy used by this plan.
- Redis and Horizon remain available to run ingestion, projection, scoring, and governance jobs.
- Laravel 12 with Jetstream and Inertia remains the control-plane stack for operator pages and authorization surfaces.
- Existing policy guardrails (`CommandPolicy`, `PathPolicy`, `EnvPolicy`) remain authoritative and unchanged semantically.
- All production workflows can be remediated to stable regex-compliant `workflow_key` values before strict checks are enforced.
- Runtime event producers can emit stable `run_id`, `run_attempt_id`, and `parent_run_id` for all relevant workflow executions.
- Feature flags can gate ingest, scoring, cost, counting, and UI surfaces independently.
- Schema registry metadata is available at ingestion time for deterministic pinning of schema and normalizer metadata.
- A central on-call channel exists and can receive deduped escalation notifications.
- Authenticated actor context is available for all governance actions that require audit persistence.
- Operator UI can add the required routes, pages, and navigation entries without framework replacement.
- Server clocks are synchronized sufficiently for deterministic UTC bucketing, SLA checks, lag computation, and outage-window evaluation.

