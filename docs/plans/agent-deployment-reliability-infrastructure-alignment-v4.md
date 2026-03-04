# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve current Laravel local-first runtime, queue topology, scheduler dedupe and watermark behavior, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature-flag architecture.
2. In scope: telemetry v1 ledger plus deterministic normalization, reliability scoring v1, cost governance, escalation and pause and resume governance, deployment counting contract, operator dashboard surfaces, docs alignment, and engineering workflow templates.
3. Out of scope: new messenger channels, new model providers, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Lock operator-facing positioning copy to: 'We help companies deploy AI agents safely and keep them reliable in production.'
5. Standardize workflow identity everywhere with regex `^[a-z0-9._-]+[.]v[1-9][0-9]*$` and enforce in Postgres using `CHECK (workflow_key ~ '^[a-z0-9._-]+[.]v[1-9][0-9]*$')` on every table containing `workflow_key`.
6. Keep API changes additive to current versioned routing conventions; no path migration.
Impacted components: `config/agent.php`, `app/Support/Agent/*`, `routes/*`, `database/migrations/*`, `docs/`, `README.md`, `PROJECT-STATUS.md`.

## 2. Architecture Changes
1. Keep telemetry as canonical truth: append-only ledger plus async deterministic projections.
2. Pin normalization metadata per ledger row: `schema_hash`, `normalizer_version`, `registry_revision`.
3. Define identity contract: `workflow_key` is canonical cross-domain key; numeric ids are optional surrogates.
4. Keep scoring at `run_id` granularity; `run_attempt_id` is lineage only.
5. Standardize canonical governance enums at DB and PHP layers:
- `gate_state`: `pass | warn | fail | paused | insufficient_data`
- `countability_state`: `countable | not_countable | insufficient_data`
6. Require `projection_version` and `source_high_watermark_id` on every projection row (`run_attempts`, reliability windows/current, cost rollups, deployment registrations, escalations if projected).
7. Keep projection tables write-protected to projector services only.
8. Low-volume behavior remains deterministic: `gate_state=insufficient_data`, no auto-pause, non-countable deployment.
Impacted components: `app/Services/Telemetry/*`, `app/Services/Reliability/*`, `app/Services/Cost/*`, `app/Services/DeploymentHealth/*`, `app/Models/*`, `app/Jobs/*`.

## 3. Data Model and Migrations
1. Create Postgres enum types and PHP enums:
- `agent_gate_state_enum` with `pass,warn,fail,paused,insufficient_data`
- `agent_countability_state_enum` with `countable,not_countable,insufficient_data`
- `agent_sequence_violation_reason_enum` with `decrease,duplicate,gap_at_terminalization`
2. Create append-only `telemetry_event_ledger`.
- Columns: `id`, `event_id`, `schema_name`, `schema_version`, `schema_hash`, `normalizer_version`, `registry_revision`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`, `agent_id`, `provider`, `model`, `prompt_version`, `event_type`, `event_ts`, `sequence_no`, `sequence_violation`, `sequence_violation_reason`, `payload_json`, `estimated_flags_json`, `ingest_latency_ms`, `telemetry_delayed`, `telemetry_unobservable`, `unobservable_reason`, `ingested_at`.
- Constraints: `unique(event_id, run_attempt_id)`, `CHECK (workflow_key ~ '^[a-z0-9._-]+[.]v[1-9][0-9]*$')`, sequence violation reason consistency check.
- Indexes: `(run_id, sequence_no)`, `(run_attempt_id, sequence_no)`, `(workflow_key, event_ts)`, `(schema_name, schema_version, registry_revision)`.
3. Create `run_attempts` projection table.
- Columns: `run_attempt_id PK`, `run_id`, `parent_run_id`, `workflow_key`, `retry_ordinal`, `branch_path`, `started_at`, `ended_at`, `terminal_state`, `terminal_sequence_gap_detected`, `projection_version`, `source_high_watermark_id`.
- Apply workflow regex check.
4. Create `run_classifications`.
- Columns: `run_id PK`, `workflow_key`, `classification`, `failure_class`, `failure_reason_code`, `control_flow_reason_code`, `hard_fail`, `assisted_sla_deadline_at`, `assisted_sla_state`, `classified_at`, `classified_by`, `verification_passed`, `human_intervention`, `degraded_trigger_evidence_json`.
- Apply workflow regex check.
5. Create reliability tables.
- `workflow_reliability_windows`: `workflow_key`, `evaluated_at`, `window_kind`, `weighted_reliability`, `degraded_rate`, `hard_fail_rate`, `gate_state`, `gate_state_reason`, `projection_version`, `source_high_watermark_id`.
- `workflow_reliability_current`: `workflow_key PK`, `stricter_window_kind`, metrics, `gate_state`, `gate_state_reason`, `evaluated_at`, `projection_version`, `source_high_watermark_id`.
6. Create escalation and override tables.
- `workflow_escalations`: `id`, `workflow_key`, `run_id`, `trigger_type`, `window_bucket` (UTC `DATE`), `dedupe_key`, `trigger_payload`, `status`, `opened_at`, `resolved_at`, `projection_version`, `source_high_watermark_id`.
- Dedupe key rule: `${workflow_key}:${trigger_type}:${YYYY-MM-DD UTC}`.
- Partial unique index: one open escalation per dedupe key.
- `manual_override_audits`: `id`, `actor_id`, `acted_at`, `run_id`, `workflow_key`, `previous_state`, `new_state`, `reason`, `timestamp` mirror with equality constraint to `acted_at`.
7. Create cost tables.
- `model_rate_cards`, `workflow_budget_policies`, `workflow_cost_rollups` with `projection_version` and `source_high_watermark_id`.
8. Create `deployment_registrations`.
- Columns: `workflow_key PK`, `controls_enabled_at`, `gate_passed_at`, `compliance_hold_passed_at`, `countability_state`, `countability_reason`, `counted_at`, `optimization_loop_count`, `projection_version`, `source_high_watermark_id`.
9. Deterministic countability transitions:
- `insufficient_data` when both `<50` scored runs and `<14 days` since `controls_enabled_at`.
- `not_countable` when `gate_state != pass` or workflow paused or budget enforcement active or blocking escalation open.
- `countable` only when gate is pass, controls enabled, compliance hold passed, and no blocking escalation open.
10. Migration order: enum types and remediations -> ledger -> projections -> classifications -> reliability -> escalations and overrides -> cost -> deployment registrations -> indexes and checks.
Impacted components: `database/migrations/*`, `app/Models/*`, `app/Enums/*`.

## 4. API and Tool Contracts
1. Extend `routes/api.php` v1 group additively.
2. Validate `{workflowKey}` with canonical regex in request validators and route model binding.
3. Add or extend endpoints:
- `GET workflows/{workflowKey}/health`
- `GET workflows/{workflowKey}/reliability`
- `GET workflows/{workflowKey}/cost`
- `GET workflows/{workflowKey}/escalations`
- `POST workflows/{workflowKey}/resume`
- `POST workflows/{workflowKey}/pause`
- `GET deployments/counting`
- `POST telemetry/events` internal ingest.
4. Pause and resume payload contract requires: `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`.
5. API response additions:
- `gate_state`, `gate_state_reason`
- `countability_state`, `countability_reason`
- `window_bucket`
- `projection_version`, `source_high_watermark_id`
- `telemetry_delayed`, `telemetry_unobservable`, `unobservable_reason`.
6. Runner contract remains raw typed event emission only; normalization and scoring stay server-side.
Impacted components: `routes/api.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Requests/Agent/*`, `app/Support/Agent/*`.

## 5. Event Contracts and Ingestion Semantics
1. Enforce required envelope fields: `schema_name`, `schema_version`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`.
2. Ingest resolves schema registry metadata and pins `schema_hash`, `normalizer_version`, `registry_revision` immutably on ledger write.
3. Ingestion guarantees:
- at-least-once ingest
- idempotent dedupe by `(event_id, run_attempt_id)`
- append-only immutable ledger.
4. Explicit sequence violation rules per `(run_id, run_attempt_id)`:
- violation if `sequence_no` decreases
- violation if duplicate `sequence_no` appears with different `event_id`
- gap detection only at terminalization time, then mark `gap_at_terminalization`.
5. Deterministic projector ordering: `sequence_no -> event_ts -> ingested_at -> ledger_id`.
6. Observability semantics split:
- `telemetry_delayed=true` when event arrived late but reconstructable
- `telemetry_unobservable=true` when required telemetry cannot be reconstructed
- `telemetry_delayed_sla_breach=true` when `ingest_latency_ms > 300000`.
7. Auto-protect outage trigger:
- unobservable streak of 3 for same workflow, or
- delayed SLA breach streak of 3 for same workflow, or
- continuous ingest observability outage of 15 minutes.
8. Retention: raw ledger 7 days; normalized and aggregate projections 12 months.
Impacted components: `app/Services/Telemetry/IngestionService.php`, `app/Services/Telemetry/ProjectionOrdering.php`, `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Jobs/ProcessTelemetryEvent.php`, `app/Jobs/RebuildTelemetryProjections.php`.

## 6. Reliability Scoring and Lifecycle
1. Implement locked weights: Success 1.00, Assisted 0.70, Degraded 0.50, Failed 0.00.
2. Keep skipped runs control-flow neutral and excluded from numerator and denominator.
3. Enforce success contract before classifying Success.
4. Deterministic degraded triggers: schema violation, output contract mismatch, partial objective completion, fallback path execution, retry-recovery success, non-blocking guardrail violation.
5. Hard-fail mapping is explicit: `failure_class=hard_fail` or terminal `policy_blocked` or `guardrail_blocked`.
6. Assisted SLA: must verify within 24h; expiration auto-reclassifies to Failed.
7. Evaluate after each classification update using lifecycle: Run -> Classified -> Scored -> Aggregated -> Window Evaluated -> Breach Check -> Escalate -> Pause -> Investigate -> Resume.
8. Gate policy computes rolling 14-day and 50-run windows, applies stricter result, requires weighted reliability >=95 and degraded rate <=3.
9. Low-volume policy sets `gate_state=insufficient_data`, no auto-pause, non-countable state.
10. Burst override: hard-gate on 2 consecutive hard fails or 3 hard fails in rolling 24h.
11. Escalation dedupe key uses canonical UTC date window bucket.
12. Paused scheduler ticks are persisted as skipped with reason `workflow_paused` for auditability and excluded from scoring denominator.
Impacted components: `app/Services/Reliability/*`, `app/Enums/*`, `app/Console/Commands/EnforceAssistedSlaExpiry.php`, `app/Services/Scheduler/*`.

## 7. Cost Instrumentation and Governance
1. Build canonical cost calculator using `model_rate_cards` with deterministic token pricing.
2. Store billed provider cost separately for reconciliation; billed cost never drives enforcement.
3. Enforce monthly per-workflow budgets as authoritative key.
4. Keep daily and weekly budget windows alert-only.
5. Guardrails: warn at 80 percent, enforce at 100 percent, allow in-flight completion, block new runs until authorized resume.
6. Emit `budget_breach` reason code and escalation trigger payload with policy snapshot.
7. Add groundwork for anomaly detection with stable rollup fields and deviation metrics.
Impacted components: `app/Services/Cost/*`, `app/Jobs/ProjectWorkflowCosts.php`, `config/agent.php`.

## 8. Authorization and Scope Enforcement
1. Enforce RBAC capabilities:
- `central_on_call` incident response and pause authority
- `workflow_owner_or_delegate` resume approval authority
- `platform_admin` override authority.
2. Policy-gate pause/resume/escalation resolution in API and web controllers.
3. Require reason for every override and persist full audit metadata.
4. Expose audit trail in API and UI with actor, timestamp, transition, and related escalation.
5. Prevent concurrent conflicting escalation resolutions per open dedupe key.
Impacted components: `app/Policies/WorkflowGovernancePolicy.php`, `app/Providers/AuthServiceProvider.php`, `app/Http/Controllers/Api/V1/WorkflowGovernanceController.php`, `app/Http/Controllers/Agent/*`, `app/Models/ManualOverrideAudit.php`.

## 9. Failure and Retry Behavior
1. Runtime retries remain deterministic: 2 retries with 30s then 2m backoff plus jitter.
2. Ingestion retries use idempotent semantics and never double-score.
3. Assisted SLA expiry job reclassifies unresolved assisted runs to failed.
4. Auto-protect uses refined observability criteria from section 5 and pauses new runs only.
5. In-flight runs always complete during pause or gate enforcement.
6. Scheduler pause behavior remains deterministic and auditable with skipped reason code.
Impacted components: `app/Jobs/RunAgentWorkflowJob.php`, `app/Jobs/ProcessTelemetryEvent.php`, `app/Services/Reliability/GateEnforcer.php`, `app/Services/Alerting/*`, `app/Services/Scheduler/*`.

## 10. Observability and Operations
1. Add metrics: `ingest_latency_ms`, `projection_lag_seconds`, `sequence_violation_rate`, `telemetry_delayed_streak`, `telemetry_delayed_sla_breach_streak`, `telemetry_unobservable_streak`, `gate_flip_count`, `escalation_open_duration`, `projection_high_watermark_gap`.
2. Add structured logs for classification decisions, dedupe decisions, gate transitions, pause and resume actions, and budget enforcement.
3. Attach replay traceability fields (`projection_version`, `source_high_watermark_id`) to all operator-readable projection payloads.
4. Operator surfaces:
- `/agent/system-overview` shows ingest health, delayed vs unobservable states, lag, and high-watermark recency.
- `/agent/escalations` shows dedupe key, trigger evidence, and lag at trigger time.
5. Alert routing supports central on-call and workflow owner subscriptions with dedupe suppression by dedupe key.
Impacted components: `app/Services/Observability/*`, `app/Services/Alerting/*`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Pages/Agent/Escalations/Index.vue`.

## 11. User and Operator Surface Exposure
1. Add and confirm web routes/pages:
- `/agent/deployments`
- `/agent/deployments/{workflowKey}`
- `/agent/escalations`
- `/agent/budgets`
- `/agent/system-overview`.
2. Navigation discoverability:
- top-level `Agent Deployments` nav for authorized users
- workflow detail links to reliability, cost, attempts lineage, and escalation history
- breadcrumb continuity across all operator pages.
3. Required workflow widgets: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
4. Lifecycle ribbon: Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop.
5. Status treatment:
- gate badge for each `gate_state` enum value
- countability badge for each `countability_state` enum value
- explicit `not countable (incident open)` when blocking escalation exists
- separate delayed and unobservable chips.
6. Discoverability acceptance checks:
- every operator page reachable from main nav in <=2 clicks for authorized users
- run detail page contains direct workflow health link
- governance controls only visible for permitted roles
- every governance action creates immediately visible audit entry
- insufficient-data workflows never show auto-pause as reason.
Impacted components: `routes/web.php`, `app/Http/Controllers/Agent/*`, `resources/js/Pages/Agent/Deployments/*`, `resources/js/Pages/Agent/Escalations/Index.vue`, `resources/js/Pages/Agent/Budgets/Index.vue`, `resources/js/Pages/Agent/SystemOverview/Show.vue`, `resources/js/Layouts/*`.

## 12. Core/Beta/Experimental Stability Matrix
1. Publish matrix in docs and admin surface.
2. Core: scheduler dispatch plus paused-skip audit behavior, run state machine, policy guardrails, telemetry ledger ingest, schema pinning, classifier and scorer, budget enforcement, escalation and governance.
3. Beta: deployment counting automation, optimization-loop analytics, billed-cost variance views.
4. Experimental: advanced delegation recovery heuristics, non-essential messenger automations, non-critical templates.
5. Enforce matrix with feature flags and policy checks before API/UI exposure.
Impacted components: `docs/system-overview.md`, `PROJECT-STATUS.md`, `config/agent.php`, `resources/js/Pages/Agent/SystemOverview/Show.vue`.

## 13. Documentation Alignment and Canonical Narrative
1. Maintain docs drift register against implementation, including org/messenger/feature-flag state.
2. Update `PROJECT-STATUS.md` as canonical status source.
3. Align `README.md` with canonical system overview and locked positioning statement.
4. Document deterministic contracts: workflow key regex and Postgres check form, run identity model, sequence violation rules, window bucket format, enum vocabularies, countability transitions.
5. Document pause semantics and incident-open countability block.
6. Require docs updates in same change set for all runtime contract changes.
7. Add PR template checks for docs parity, route/page discoverability, and additive API compatibility.
Impacted components: `PROJECT-STATUS.md`, `README.md`, `docs/system-overview.md`, `.github/pull_request_template.md`.

## 14. Phase 1 Engineering Workflow Templates
1. Ensure registry includes and enables controls by default for:
- `eng.repo-analysis.v1`
- `eng.code-implementation.v1`
- `eng.pr-quality-gate.v1`
- `eng.dependency-update-triage.v1`
- `eng.release-readiness.v1`.
2. Validate template keys with canonical workflow regex.
3. Enforce per template: telemetry envelope, schema pinning, classification hooks, cost tracking, escalation hooks, budget binding.
4. Activation readiness checks: schema compliance, scoring enabled, cost policy attached, governance roles bound, countability initialized.
Impacted components: `config/agent.php`, `app/Services/WorkflowTemplates/*`, `database/seeders/*`.

## 15. Backward Compatibility Strategy
1. Keep schema registry with major-version rules and compatibility adapters.
2. During transition, dual-write legacy events to ledger; ledger is scoring source.
3. Backfill schema pin fields for legacy events using deterministic derived markers.
4. Add replay command to rebuild projections from ledger and verify deterministic parity by pinned metadata.
5. Keep API consumer contracts stable through additive fields and serializers.
6. Migrate run attempts via replay and block direct writes outside projectors.
7. Keep manual override API compatibility by accepting and returning `timestamp` while persisting canonical `acted_at`.
8. Add migration guard for existing nonconforming workflow keys with remediation script prior to enabling regex checks.
Impacted components: `app/Services/Telemetry/VersionedSchemaRegistry.php`, `app/Services/Telemetry/LegacyAdapter.php`, `app/Console/Commands/ReplayTelemetryLedger.php`, `app/Transformers/*`, `app/Services/Telemetry/RunAttemptProjector.php`, `database/migrations/*`.

## 16. Rollout and Rollback Controls
1. Feature flags:
- `agent.telemetry_v1_ingest`
- `agent.reliability_v1_scoring`
- `agent.cost_governance_v1`
- `agent.deployment_counting_v1`
- `agent.operator_dashboard_v1`.
2. Enable in dependency order: ingest -> normalization and projections -> scoring and gates -> cost enforcement -> deployment counting -> operator surfaces.
3. Rollback order: dashboard -> counting -> cost -> scoring, while keeping ledger ingest active unless storage incident requires stop.
4. Recovery control: rerun projectors and replay from ledger high watermark.
5. Pre-enable guard checks:
- enum types present in DB and app enum maps
- workflow key remediation complete
- escalation dedupe uniqueness clean.
Impacted components: `config/agent.php`, `app/Support/FeatureFlags/*`, `app/Console/Commands/*`.

## 17. Test Strategy
1. Unit tests:
- classification mapping for all failure classes and reason codes
- weighted reliability formula and skipped exclusion
- stricter-window selection logic
- hard-fail burst detection
- canonical cost calculations and estimation flags
- run_id-only denominator enforcement with multiple attempts
- workflow regex validation and enum serialization guards
- window bucket derivation format as UTC `YYYY-MM-DD`.
2. Feature tests:
- ingest idempotency for duplicate `(event_id, run_attempt_id)`
- out-of-order acceptance with sequence violation flags
- explicit sequence violation reasons: decrease, duplicate, gap at terminalization
- pause and resume authorization with mandatory reason persistence
- assisted SLA expiry auto-reclassification
- budget enforcement with in-flight completion and new-run blocking
- workflow key route binding and additive response compatibility
- escalation dedupe single open escalation per dedupe key.
3. Integration tests:
- end-to-end run -> ingest -> classification -> scoring -> gate -> escalation -> pause -> resume
- replay reproduces identical reliability and health state
- outage policy triggers auto-pause and alerting
- projection determinism with randomized ledger insert order
- late-arriving convergence without double-count scoring
- schema pin determinism across registry change
- open escalation blocks counting: gate pass but open blocking escalation keeps `countability_state != countable` and `counted_at` unset.
4. UI tests:
- route access and nav discoverability checks
- workflow widgets render required metrics
- governance action visibility by role
- audit trail visibility after overrides
- insufficient-data UI shows not countable copy and no auto-pause claim
- delayed vs unobservable chips render distinctly
- deployment detail shows `not countable (incident open)` when escalation is open.
5. Data integrity tests:
- immutable ledger updates rejected
- append-only run transition enforcement
- scoring and health derivable solely from replay
- projection high watermark monotonicity.
6. CI gates: Pint, static analysis, Pest, coverage threshold.
Impacted components: `tests/Unit/*`, `tests/Feature/*`, `tests/Integration/*`, `tests/Feature/AgentUi/*`, `.github/workflows/*`.

## 18. Ordered Task DAG
1. D1: Finalize canonical docs baseline and drift register.
2. D2: Implement workflow key remediation and Postgres regex checks across all workflow-key tables.
3. D3: Add DB enum types and PHP enums for gate and countability and sequence-violation reasons.
4. D4: Ship telemetry ledger migration with pinned schema metadata and delayed/unobservable fields.
5. D5: Implement ingest service dedupe, ordering, metadata pinning, and latency capture.
6. D6: Implement explicit sequence-violation detector including terminalization gap detection.
7. D7: Build normalizer registry and pinned normalizer execution.
8. D8: Build projection pipelines with `projection_version` and `source_high_watermark_id` on all projection rows.
9. D9: Implement taxonomy enums and classification engine.
10. D10: Implement run-level scorer, stricter window evaluator, low-volume gating.
11. D11: Implement escalation engine with canonical UTC window bucket and dedupe key enforcement.
12. D12: Implement observability outage detector with delayed SLA breach threshold and auto-protect rules.
13. D13: Implement pause/protect controls and scheduler paused-tick skipped persistence.
14. D14: Implement assisted SLA expiry reclassification job.
15. D15: Implement canonical cost service, budget policies, and enforcement.
16. D16: Implement deployment registration projector with deterministic countability transitions and incident-open counting block.
17. D17: Implement API endpoints and validators for health, reliability, cost, escalations, pause, resume, counting, and ingest.
18. D18: Implement operator web routes, pages, navigation, badges, lifecycle ribbon, and discoverability checks.
19. D19: Implement replay tooling, dual-write adapters, and compatibility serializers.
20. D20: Implement observability dashboards, structured logs, and alert routing.
21. D21: Complete unit, feature, integration, UI, and data-integrity tests plus CI gates.
22. D22: Publish final canonical docs, stability matrix, and acceptance scorecard protocol.

## 19. Acceptance Closure Mapping
1. Telemetry closure: envelope and identity fields implemented; schema pin metadata persisted; idempotent dedupe active; explicit sequence-violation semantics active; append-only ledger verified; deterministic normalization active; delayed and unobservable split active with SLA-aware outage auto-protect.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run-level scoring semantics, weighted formula, stricter-window gate, insufficient-data behavior, hard-fail burst override, and escalation dedupe implemented.
3. Governance closure: RBAC pause/resume authority enforced; manual override audits persisted with required fields; one-open-incident dedupe enforcement active.
4. Cost closure: canonical enforcement cost active; billed-cost reconciliation separated; monthly budget policy and thresholds active; rollups include projection version and high watermark.
5. Deployment closure: deterministic countability transitions active; incident-open workflows cannot be counted; lifecycle states visible; optimization loop measurable.
6. Operator closure: required routes/pages/nav discoverable; workflow metrics and lifecycle ribbon visible; enum status treatments rendered; governance actions policy-gated; audit entries visible.
7. Documentation closure: drift register resolved; `PROJECT-STATUS.md`, `README.md`, and system overview aligned to canonical contracts and positioning.

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

- Existing rows with nonconforming workflow keys can block regex-check migrations until remediation scripts run successfully.
- Introducing DB enums without synchronized PHP enum maps can cause serialization or casting mismatches in API responses.
- UTC window bucket computation can drift if any service derives buckets from local timezone or client clock.
- Sequence gap detection at terminalization can be missed when terminal events are delayed or absent, causing temporary false negatives.
- Projection-version backfill on existing projection rows can produce mixed-version reads during transition unless guarded by feature flags.
- Escalation dedupe uniqueness can fail if legacy open incidents do not have normalized dedupe keys.
- Delayed and unobservable semantics can diverge across services if SLA threshold constants are not centralized in config.
- Open-escalation counting blocks can surprise operators if UI messaging does not clearly explain countability reasons.
- Dual-write transition can show temporary disagreement between legacy and ledger-backed endpoints if read paths are mixed.
- Large billed-versus-canonical cost variance can reduce trust unless reconciliation views clearly explain source and purpose differences.
- RBAC role seeding gaps can either block valid responders or allow broad override access.
- Docs parity can regress if PR checks are not enforced for route/page discoverability and contract changes.


## Assumptions

- PostgreSQL is the source-of-record database and supports required checks, partial indexes, and enum types.
- Redis and Horizon remain available for ingestion, projection, and governance jobs.
- Laravel 12 with Jetstream and Inertia remains the control-plane framework.
- Existing guardrail policies remain authoritative and are reused without semantic change.
- All production workflows can be assigned stable regex-compliant workflow keys.
- Run events can be adapted to include run_id, run_attempt_id, and parent_run_id consistently.
- Feature-flag infrastructure can gate ingest, scoring, cost, counting, and operator surfaces independently.
- Schema registry metadata is version-controlled and available at ingest time for deterministic pinning.
- Central on-call alert channel exists and can receive deduped escalation notifications.
- Authenticated actor context is available on all governance actions for required audit fields.
- Operator web UI can add new pages and navigation entries without framework replacement.
- System clocks are synchronized to UTC well enough for deterministic bucket and SLA calculations.

