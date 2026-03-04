# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve existing Laravel local-first runtime, scheduler dedupe and watermark behavior, Redis and Horizon execution, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature flags.
2. Keep Phase 1 scope to telemetry ledger v1, deterministic normalization, reliability scoring v1, cost governance, escalation and pause/resume governance, deployment counting, operator surfaces, documentation alignment, and engineering workflow templates.
3. Keep out of scope: messenger-surface expansion, provider expansion, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Lock external positioning copy to: We help companies deploy AI agents safely and keep them reliable in production.
5. Enforce `workflow_key` with regex `^[a-z0-9._-]+[.]v[1-9][0-9]*$` plus Postgres `CHECK` on every table containing `workflow_key`.
6. Keep API changes additive inside existing versioned groups and serializers.
7. Apply one server-side UTC clock service for all SLA windows, dedupe windows, and projection lag.
8. Keep primary KPI population as scheduled runs and secondary KPI as all production runs.
Impacted components: `config/agent.php`, `app/Support/Agent/*`, `app/Support/Time/*`, `routes/*`, `database/migrations/*`, `docs/*`, `README.md`, `PROJECT-STATUS.md`.

## 2. Architecture Changes
1. Keep append-only telemetry ledger as canonical source; projections remain derived, replaceable, and deterministic.
2. Pin normalization metadata per ledger row: `schema_hash`, `normalizer_version`, `registry_revision`, and `rate_card_version`.
3. Keep scoring at `run_id`; `run_attempt_id` remains lineage only.
4. Define event identity model explicitly: `event_id` is producer-generated and unique within a `run_attempt_id`; global uniqueness is not required.
5. Define terminalization explicitly: an attempt terminalizes only when an ingested event has `terminal=true`; terminal event types are config-driven and versioned.
6. Add replay isolation model: projector consumers can be switched to replay mode, replay writes to staging build scope, parity checks run, then atomic active-build swap occurs.
7. Keep escalation split between incident lifecycle and alert suppression: incident uniqueness is by open incident, suppression is by UTC day bucket.
8. Keep low-volume behavior deterministic: `gate_state=insufficient_data`, no auto-pause from insufficient data alone, and non-countable deployment.
Impacted components: `app/Services/Telemetry/*`, `app/Services/Reliability/*`, `app/Services/Cost/*`, `app/Services/DeploymentHealth/*`, `app/Services/Escalation/*`, `app/Support/Time/AgentClock.php`, `config/agent.php`.

## 3. Data Model and Migrations
1. Create Postgres and PHP enums: `agent_gate_state_enum`, `agent_countability_state_enum`, `agent_sequence_violation_reason_enum`, `agent_run_terminal_state_enum`, and `agent_escalation_status_enum`.
2. Create append-only `telemetry_event_ledger` with canonical envelope, identity fields, pinned normalizer metadata, pinned `rate_card_version`, observability flags, and ingest timing fields.
3. Keep dedupe key contract as unique `(event_id, run_attempt_id)` and document event-id scope in schema comments and docs.
4. Add sequence constraints: reason required when violation flag true; terminalization-gap reason allowed only for projector synthetic audit rows.
5. Keep `run_attempts` projection with `terminal_sequence_gap_detected`, `run_terminal_state`, `terminal_event_type`, and projection lineage fields.
6. Keep `run_classifications`, `workflow_reliability_windows`, and `workflow_reliability_current` with projection lineage and gate metadata.
7. Replace escalation uniqueness rule: create partial unique index for one unresolved incident per `(workflow_key, trigger_type)` where status in `open|investigating`; move day-bucket dedupe to `escalation_alert_suppressions`.
8. Add `workflow_gate_transitions` append-only audit table with actor, source trigger, previous and new gate states, and lineage metadata.
9. Keep `manual_override_audits` required fields and timestamp parity checks.
10. Keep cost tables `model_rate_cards`, `workflow_budget_policies`, `workflow_cost_rollups`; add immutable `rate_card_version` to rollups and run-cost projections.
11. Add replay-build control tables: `telemetry_projection_builds` and `telemetry_projection_build_state` to support staging rebuild and atomic activation.
12. Keep `deployment_registrations` with deterministic countability transitions and reasons.
13. Migration order: key remediation, enums, ledger, sequence constraints, projections, classifications, reliability, escalations and suppressions, overrides, cost tables, replay-build controls, deployment tables, final indexes and checks.
Impacted components: `database/migrations/*`, `app/Models/*`, `app/Enums/*`.

## 4. API and Tool Contracts
1. Extend `routes/api.php` additively under existing v1 group.
2. Validate `{workflowKey}` via canonical regex in route binding, validators, and policy checks.
3. Harden endpoints: `GET workflows/{workflowKey}/health`, `GET workflows/{workflowKey}/reliability`, `GET workflows/{workflowKey}/cost`, `GET workflows/{workflowKey}/escalations`, `POST workflows/{workflowKey}/pause`, `POST workflows/{workflowKey}/resume`, `GET deployments/counting`, `POST telemetry/events`.
4. Add additive operator endpoints: `GET workflows/{workflowKey}/gate-transitions`, `GET telemetry/replay/builds/{buildId}`, and `POST telemetry/replay/builds` for admin replay orchestration.
5. Require governance payload fields: `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`.
6. Expose canonical response fields: gate and countability states and reasons, projection lineage, terminalization metadata, observability flags, and pinned `rate_card_version` where relevant.
7. Keep runners as raw typed emitters only; ingestion, normalization, classification, scoring, costing, and escalation stay server-side.
Impacted components: `routes/api.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Requests/Agent/*`, `app/Support/Agent/*`.

## 5. Event Contracts and Ingestion Semantics
1. Require envelope fields on each event: `schema_name`, `schema_version`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`, `event_id`, `event_type`, `terminal`.
2. Define event identity scope: `event_id` must be unique per attempt and stable across retries of delivery for the same producer event.
3. Keep ingest semantics: at-least-once, idempotent dedupe by `(event_id, run_attempt_id)`, immutable append-only ledger writes.
4. Resolve schema registry at ingest and pin `schema_hash`, `normalizer_version`, `registry_revision`; pin active `rate_card_version` snapshot used for canonical cost.
5. Enforce per-attempt sequencing and explicit reasons: `decrease`, `duplicate`, `gap_at_terminalization`.
6. Define terminalization contract: gap checks execute only when terminal event ingests; terminal event catalog in config includes `run_completed`, `run_failed`, `run_aborted`, `run_cancelled`, `policy_blocked_terminal`, `guardrail_blocked_terminal`.
7. When terminal gap exists, write one synthetic terminalization audit event and set `run_attempts.terminal_sequence_gap_detected=true`.
8. Keep deterministic projection order: `sequence_no`, `event_ts`, `ingested_at`, `ledger_id`.
9. Keep observability split: delayed means reconstructable late telemetry; unobservable means required telemetry cannot be reconstructed.
10. Trigger auto-protect on configured unobservable streak, delayed streak breach, or continuous ingest observability outage window.
Impacted components: `app/Services/Telemetry/IngestionService.php`, `app/Services/Telemetry/ProjectionOrdering.php`, `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Services/Telemetry/TerminalizationGapProjector.php`, `app/Jobs/ProcessTelemetryEvent.php`, `config/agent.php`.

## 6. Reliability Scoring and Lifecycle
1. Implement locked weights: Success `1.00`, Assisted `0.70`, Degraded `0.50`, Failed `0.00`; skipped remains control-flow neutral and excluded from weighted denominator and numerator.
2. Enforce success contract: no hard fail, no terminal blocking guardrail or policy breach, objective verification pass, and no human intervention.
3. Enforce deterministic degraded triggers: schema violation, output contract mismatch, partial objective completion, fallback path execution, retry-recovery success, non-blocking guardrail violation.
4. Enforce hard-fail mapping: `failure_class=hard_fail` or terminal `policy_blocked` or `guardrail_blocked`.
5. Enforce assisted SLA expiry reclassification to Failed.
6. Evaluate reliability windows on each classification update and enforce stricter gate result.
7. Apply gate thresholds, degraded companion threshold, and hard-fail burst override.
8. Persist paused scheduler ticks as skipped with explicit reason code.
9. Persist every gate transition to `workflow_gate_transitions` and expose lineage to operators.
10. Use centralized trigger taxonomy for blocking and non-blocking triggers.
Impacted components: `app/Services/Reliability/*`, `app/Enums/*`, `app/Console/Commands/EnforceAssistedSlaExpiry.php`, `config/agent.php`, `app/Services/Scheduler/*`.

## 7. Cost Instrumentation and Governance
1. Build canonical cost calculator from internal `model_rate_cards` and deterministic token accounting.
2. Make `rate_card_version` immutable in event normalization output, run-cost projections, and workflow rollups.
3. Store provider billed cost separately for reconciliation and variance only; never use billed values for enforcement.
4. Keep authoritative budget enforcement keyed by workflow billing cycle; shorter windows are alert-only.
5. Apply guardrails: warning threshold, enforcement threshold, in-flight completion allowed, new runs blocked until authorized resume.
6. Emit `budget_breach` with policy snapshot into escalation engine via centralized trigger taxonomy.
7. Add anomaly groundwork fields in rollups without changing enforcement contracts.
Impacted components: `app/Services/Cost/*`, `app/Jobs/ProjectWorkflowCosts.php`, `config/agent.php`, `database/migrations/*`.

## 8. Authorization and Scope Enforcement
1. Enforce RBAC capabilities: `central_on_call` pause and incident response, `workflow_owner_or_delegate` resume approval, `platform_admin` override.
2. Policy-gate pause, resume, escalation transitions (`open->investigating->resolved`), replay-build creation, replay activation, and countability overrides.
3. Require explicit reason on all governance actions and persist full audit metadata.
4. Keep all governance actions traceable in API and UI with actor, timestamp, previous and new state, workflow context, and run context.
5. Prevent concurrent conflicting incident transitions with transactional locking and one-unresolved-incident unique index.
Impacted components: `app/Policies/WorkflowGovernancePolicy.php`, `app/Providers/AuthServiceProvider.php`, `app/Http/Controllers/Api/V1/WorkflowGovernanceController.php`, `app/Http/Controllers/Agent/*`, `app/Models/ManualOverrideAudit.php`, `app/Models/EscalationIncident.php`.

## 9. Failure and Retry Behavior
1. Keep runtime retry behavior deterministic with configured retry count, backoff ladder, and jitter strategy.
2. Keep ingestion retries idempotent and non-inflating for scored runs.
3. Run assisted-SLA expiry reconciliation as deterministic job.
4. Auto-protect blocks only new runs; in-flight runs complete.
5. Keep pause behavior auditable via skipped reason coding.
6. Ensure failure and retry handlers consume centralized UTC clock and trigger taxonomy.
Impacted components: `app/Jobs/RunAgentWorkflowJob.php`, `app/Jobs/ProcessTelemetryEvent.php`, `app/Services/Reliability/GateEnforcer.php`, `app/Services/Alerting/*`, `app/Services/Scheduler/*`, `app/Support/Time/AgentClock.php`.

## 10. Observability and Operations
1. Add metrics: `ingest_latency_ms`, `projection_lag_seconds`, `projection_backlog_events`, `sequence_violation_rate`, delayed and unobservable streaks, gate flip count, escalation open duration, and projection high-watermark gap.
2. Define `projection_lag_seconds` as server UTC now minus projection row `source_high_watermark_ingested_at`.
3. Add replay observability metrics: active projection build id, replay mode status, parity check result, and swap timestamp.
4. Add structured logs for classifier decisions, dedupe outcomes, gate transitions, escalation transitions, replay activation, and budget enforcement.
5. Expose lineage fields and `rate_card_version` in troubleshooting payloads.
6. Operator pages: `/agent/system-overview` shows ingest health, delayed vs unobservable split, lag, backlog, and replay-build status; `/agent/escalations` shows incident status lifecycle, trigger evidence, and suppression state.
7. Alert routing supports central on-call and workflow-owner subscriptions with suppression keyed by workflow, trigger, and UTC date.
Impacted components: `app/Services/Observability/*`, `app/Services/Alerting/*`, `app/Support/Time/AgentClock.php`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Pages/Agent/Escalations/Index.vue`.

## 11. User and Operator Surface Exposure
1. Add and verify routes and pages: `/agent/deployments`, `/agent/deployments/{workflowKey}`, `/agent/escalations`, `/agent/budgets`, `/agent/system-overview`.
2. Ensure navigation discoverability: top-level `Agent Deployments`, deep links to reliability, cost, attempt lineage, gate transitions, and escalation history.
3. Render required workflow widgets: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
4. Render deployment lifecycle ribbon: created, beta, reliability observed, gate passed, deployment counted, optimization loop.
5. Render deterministic badges for `gate_state`, `countability_state`, `run_terminal_state`, and escalation status; show explicit copy `not countable (incident open)` when unresolved blocking incident exists.
6. Render delayed and unobservable statuses separately with reason tooltips from canonical reason codes.
7. Expose governance controls only to authorized roles, including escalation lifecycle actions and replay controls for admins.
8. Discoverability acceptance checks: each operator page reachable from main nav in two clicks for authorized users; workflow detail has direct health link and gate transitions tab; every governance action creates visible audit evidence in-page.
Impacted components: `routes/web.php`, `app/Http/Controllers/Agent/*`, `resources/js/Pages/Agent/Deployments/*`, `resources/js/Pages/Agent/Escalations/Index.vue`, `resources/js/Pages/Agent/Budgets/Index.vue`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Layouts/*`.

## 12. Core/Beta/Experimental Stability Matrix
1. Publish and enforce matrix in docs and admin surfaces.
2. Core: scheduler dispatch and pause-skip audit, run state machine, policy guardrails, telemetry ingest and schema pinning, classification and scoring, cost enforcement, escalation governance, replay-safe projection infrastructure.
3. Beta: deployment counting automation, optimization-loop analytics, billed-cost variance views, replay control UX.
4. Experimental: advanced delegation heuristics and non-essential messenger automations.
5. Enforce exposure by feature flags and policy checks per matrix class.
Impacted components: `docs/system-overview.md`, `PROJECT-STATUS.md`, `config/agent.php`, `resources/js/Pages/Agent/SystemOverview/Show.vue`.

## 13. Documentation Alignment and Canonical Narrative
1. Maintain drift register mapped to implementation reality including org, messenger, and feature-flag state.
2. Keep `PROJECT-STATUS.md` as canonical implementation truth and `README.md` as aligned entry narrative.
3. Publish canonical contracts: workflow key regex, event-id scope, terminalization rules, failure taxonomy, sequence rules, UTC bucketing, trigger taxonomy, projection lag and backlog definitions, replay isolation semantics, countability transitions, pause semantics, and `rate_card_version` rules.
4. Require docs updates in same change set for any runtime contract change.
5. Add PR checks for docs parity, route and page discoverability impacts, additive API compatibility, and trigger-taxonomy consistency.
Impacted components: `PROJECT-STATUS.md`, `README.md`, `docs/system-overview.md`, `.github/pull_request_template.md`.

## 14. Phase 1 Engineering Workflow Templates
1. Ensure template registry includes `eng.repo-analysis.v1`, `eng.code-implementation.v1`, `eng.pr-quality-gate.v1`, `eng.dependency-update-triage.v1`, `eng.release-readiness.v1`.
2. Enforce workflow-key regex validation at registry, seed, and runtime selection layers.
3. Enforce controls by default per template: telemetry envelope, schema pinning, classification hooks, cost tracking with `rate_card_version`, escalation hooks, budget binding.
4. Add readiness gate per template: schema compliance, scoring enabled, cost policy attached, governance roles bound, and countability initialized as insufficient data.
Impacted components: `config/agent.php`, `app/Services/WorkflowTemplates/*`, `database/seeders/*`.

## 15. Backward Compatibility Strategy
1. Keep schema-registry major-version compatibility rules and adapters.
2. Dual-write legacy events during transition; ledger remains scoring source of truth.
3. Backfill pinned metadata for legacy events with deterministic derived markers including `rate_card_version` fallback marker.
4. Implement replay-safe rebuild: pause live projection consumers for target projection family, replay to staging build scope, verify parity, then atomically activate build pointer.
5. Keep API compatibility via additive fields and serializer extensions.
6. Migrate run-attempt projections via replay; block direct writes outside projector services.
7. Keep manual-override payload compatibility by accepting and returning `timestamp` while persisting canonical `acted_at`.
8. Run workflow-key remediation before strict regex checks.
Impacted components: `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Services/Telemetry/LegacyAdapter.php`, `app/Console/Commands/ReplayTelemetryLedger.php`, `app/Services/Telemetry/ProjectionBuildManager.php`, `app/Transformers/*`, `database/migrations/*`.

## 16. Rollout and Rollback Controls
1. Keep feature flags: `agent.telemetry_v1_ingest`, `agent.reliability_v1_scoring`, `agent.cost_governance_v1`, `agent.deployment_counting_v1`, `agent.operator_dashboard_v1`, `agent.projection_replay_v1`.
2. Enable in dependency order: ingest, normalization and projections, scoring and gates, cost enforcement, deployment counting, operator surfaces, replay controls.
3. Roll back in reverse dependency order while preserving ledger ingest unless storage incident requires stop.
4. Recovery controls: rerun projectors from high-watermark checkpoints and activate last known-good projection build pointer if parity fails.
5. Pre-enable checks: enum availability, workflow-key remediation complete, incident uniqueness index valid, suppression table active, trigger taxonomy loaded, UTC clock service enabled, terminal event catalog loaded, `rate_card_version` available.
Impacted components: `config/agent.php`, `app/Support/FeatureFlags/*`, `app/Console/Commands/*`, `app/Support/Time/*`.

## 17. Test Strategy
1. Unit tests: classification mapping, weighted reliability math, skipped exclusion, stricter-window selection, hard-fail burst detection, canonical cost calculations, `rate_card_version` pinning, run-level denominator with multiple attempts, workflow-key regex, enum serialization, UTC bucket derivation, projection lag and backlog calculations, terminalization event-type mapping, trigger taxonomy behavior.
2. Feature tests: ingest dedupe by `(event_id, run_attempt_id)`, out-of-order ingest acceptance, sequence violation reasons, terminalization-gap synthetic event generation, pause and resume authorization and audit persistence, assisted-SLA expiry, budget enforcement, additive API compatibility, one-unresolved-incident constraint, suppression-by-day behavior.
3. Integration tests: run to ingest to classify to score to gate to escalate to pause to resume lifecycle, replay parity and atomic activation, outage auto-protect, deterministic projection under randomized insert order, late-arrival convergence without double scoring, schema pin determinism across registry revisions, blocking incident prevents countability.
4. UI tests: route access and navigation discoverability, workflow widgets rendering, role-based governance visibility, audit visibility after overrides, insufficient-data rendering, delayed vs unobservable indicators, escalation lifecycle state transitions, gate transitions tab visibility, replay status visibility in system overview.
5. Data integrity tests: immutable ledger, append-only run transitions, replay-derivable scoring and health state, projection high-watermark monotonicity, one-active projection build pointer.
6. CI gates: formatting, static analysis, full test suite, coverage threshold enforcement.
Impacted components: `tests/Unit/*`, `tests/Feature/*`, `tests/Integration/*`, `tests/Feature/AgentUi/*`, `.github/workflows/*`.

## 18. Ordered Task DAG
1. D1: Lock canonical docs baseline, drift register schema, positioning text, and event-id scope statement.
2. D2: Implement workflow-key remediation and regex checks across workflow-key tables.
3. D3: Introduce DB and PHP enums for gate state, countability state, sequence-violation reason, run terminal state, and escalation status.
4. D4: Ship telemetry ledger migration with sequence constraints, terminalization audit constraints, and pinned `rate_card_version` field.
5. D5: Implement ingestion dedupe, schema pinning, ingest latency capture, and UTC clock integration.
6. D6: Implement terminal event catalog, sequence violation detector, terminalization gap projector, and attempt summary terminal fields.
7. D7: Implement normalizer registry and pinned normalizer execution.
8. D8: Implement projection pipelines with lineage fields and replay-build scoping support.
9. D9: Implement failure taxonomy enums and classification engine.
10. D10: Implement run-level scorer, stricter-window evaluator, low-volume gate behavior, and countability projection.
11. D11: Implement escalation engine with incident lifecycle uniqueness by `(workflow_key, trigger_type)` and suppression table by UTC day bucket.
12. D12: Implement outage detector with delayed vs unobservable split and auto-protect triggers.
13. D13: Implement pause/protect controls and scheduler paused-tick skip persistence.
14. D14: Implement assisted-SLA expiry reconciliation job.
15. D15: Implement canonical cost service, rate-card version pin flow, budget policy projector, and enforcement actions.
16. D16: Implement deployment registration projector with deterministic countability transitions and unresolved-incident block.
17. D17: Implement API endpoints and validators for health, reliability, cost, escalations, pause, resume, counting, telemetry ingest, gate transitions, and replay build controls.
18. D18: Implement web routes, pages, navigation, badges, lifecycle ribbon, discoverability checks, and role-gated governance controls.
19. D19: Implement replay tooling, dual-write adapters, additive serializers, staging parity validator, and atomic build activation.
20. D20: Implement observability dashboards, structured logs, alert routing, lag and backlog displays, and replay status displays.
21. D21: Complete unit, feature, integration, UI, and data-integrity tests with CI enforcement.
22. D22: Publish canonical docs and acceptance scorecard mapped to implemented controls.

## 19. Acceptance Closure Mapping
1. Telemetry closure: canonical envelope and identity fields, explicit event-id scope, schema and normalizer pinning, pinned `rate_card_version`, idempotent dedupe, sequence reason constraints, explicit terminalization rules, terminalization-gap handling, append-only ledger, and delayed versus unobservable semantics are active.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run-level scoring, strict formula implementation, stricter-window gating, insufficient-data behavior, hard-fail burst override, and gate transition audit history are active.
3. Governance closure: RBAC pause and resume authority enforced, escalation lifecycle states active, one unresolved incident per workflow and trigger enforced, manual overrides persisted with required fields, and blocking-trigger taxonomy consistently applied.
4. Cost closure: canonical enforcement cost active, billed-cost reconciliation isolated, policy thresholds enforced, and rollups persist immutable `rate_card_version` plus projection lineage.
5. Replay closure: replay runs in isolated build scope, parity validation required before activation, and active projection swap is atomic and auditable.
6. Deployment closure: deterministic countability transitions active, unresolved blocking incident prevents counting, lifecycle states visible in control plane, optimization loop counters measurable.
7. Operator closure: required routes and pages reachable via navigation, required widgets and lifecycle ribbon visible, gate and countability and terminal and escalation statuses render correctly, governance controls policy-gated, and audit rows visible immediately after action.
8. Documentation closure: drift register resolved and canonical docs align with implemented contracts, replay safety semantics, incident lifecycle, route and page discoverability, and positioning language.

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

- Legacy producers may emit non-stable `event_id` values within an attempt, causing dedupe misses or false duplicates until producer conformance is enforced.
- Terminal event catalog drift between config and emitters can create inconsistent terminalization behavior and incorrect gap evaluation.
- Replay activation without strict parity checks can expose mixed projection builds to read paths.
- Incident uniqueness migration can fail if existing unresolved incidents contain inconsistent trigger normalization.
- Suppression table and incident table can diverge if lifecycle transitions do not update suppression markers transactionally.
- Missing or incorrect `rate_card_version` pinning can break historical cost determinism and audit reproducibility.
- RBAC or policy misconfiguration can allow unauthorized replay activation or block legitimate on-call mitigation.
- Projection backlog metrics can become misleading if ingestion and projector queues are instrumented with inconsistent labels.
- UI discoverability can regress if navigation updates are shipped without route-policy alignment tests.
- Dual-write transition can surface temporary field disagreement if serializer precedence is not pinned to ledger-derived projections.


## Assumptions

- PostgreSQL is the source-of-record and supports required enum types, partial unique indexes, transactional DDL, and constraints used by this plan.
- Redis and Horizon remain available for ingestion, projection, scoring, escalation, and reconciliation jobs.
- Laravel 12 with Jetstream and Inertia remains the control-plane stack for operator pages and authorization surfaces.
- Existing policy guardrails (`CommandPolicy`, `PathPolicy`, `EnvPolicy`) remain semantically authoritative.
- All production workflows can be remediated to regex-compliant `workflow_key` values before strict enforcement.
- Event producers can emit stable `run_id`, `run_attempt_id`, `parent_run_id`, and attempt-scoped stable `event_id` values.
- Feature flags can gate ingest, scoring, cost, counting, operator surfaces, and replay controls independently.
- Schema registry metadata and cost rate-card registry are available at ingest and normalization boundaries for deterministic pinning.
- A central on-call channel and workflow-owner subscriptions exist for deduped escalation routing.
- Authenticated actor context is available for all governance and replay actions requiring audit persistence.
- Operator UI can add required routes, pages, and navigation updates without framework replacement.
- Server clocks are synchronized enough for deterministic UTC-based SLA checks, dedupe windows, lag metrics, and outage windows.

