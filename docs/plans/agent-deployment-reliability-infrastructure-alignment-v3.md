# Implementation Plan

Derived from discovery session 17.

## 1. Scope Boundary
1. Preserve current Laravel local-first runtime, queue topology, scheduler dedupe/watermark behavior, policy guardrails, delegation DAG, requirements discovery, deterministic repo analysis, docs coverage controls, messenger control plane, and feature-flag architecture.
2. In scope: telemetry v1 ledger + deterministic normalization, reliability scoring v1, cost governance, escalation/pause/resume governance, deployment counting contract, operator dashboard surfaces, docs alignment, and engineering workflow templates.
3. Out of scope: new messenger channels, new model providers, delegation complexity expansion, enterprise compliance expansion, and runtime rewrite.
4. Lock operator-facing positioning copy to: 'We help companies deploy AI agents safely and keep them reliable in production.'
5. Standardize `workflow_key` naming everywhere with required pattern `/^[a-z0-9._-]+[.]v[1-9][0-9]*$/`.
6. Keep API/versioning changes additive to current routing conventions; no disruptive path migrations.
Impacted components: `config/agent.php`, `docs/`, `README.md`, `PROJECT-STATUS.md`, `app/` domain services.

## 2. Architecture Changes
1. Keep telemetry as canonical truth: append-only ledger plus async deterministic projections.
2. Pin normalization deterministically per ingested event using immutable registry pin metadata (`schema_hash` + `normalizer_version` + `registry_revision`) recorded on ledger rows.
3. Define workflow identity contract: `workflow_key` is canonical cross-domain identifier; numeric `workflow_id` remains optional surrogate only.
4. Keep reliability at `run_id` scoring granularity; `run_attempt_id` remains lineage/diagnostic only.
5. Keep cost governance domain for canonical enforcement cost, billed-cost reconciliation, and monthly budget enforcement.
6. Keep deployment health domain with per-workflow projections: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
7. Treat `run_attempts` and all rollup tables as projections only; no direct writes outside projector services.
8. Add explicit low-volume behavior: if data is insufficient, `gate_state=insufficient_data`, no auto-pause, and deployment stays non-countable.
Impacted components:
- `app/Services/Telemetry/*`
- `app/Services/Reliability/*`
- `app/Services/Cost/*`
- `app/Services/DeploymentHealth/*`
- `app/Services/SystemNarrative/*`
- `app/Models/*` projection/governance models
- `app/Jobs/*` ingestion/projector jobs

## 3. Data Model and Migrations
1. Create immutable append-only `telemetry_event_ledger`.
- Required columns: `id`, `event_id`, `schema_name`, `schema_version`, `schema_hash`, `normalizer_version`, `registry_revision`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`, `agent_id`, `provider`, `model`, `prompt_version`, `event_type`, `event_ts`, `sequence_no`, `sequence_violation`, `payload_json`, `estimated_flags_json`, `telemetry_delayed`, `telemetry_unobservable`, `unobservable_reason`, `ingested_at`.
- Constraints/indexes: `unique(event_id, run_attempt_id)`, index `(run_id, sequence_no)`, index `(workflow_key, event_ts)`, index `(schema_name, schema_version, registry_revision)`, check `workflow_key` regex.
2. Create `run_attempts` as projection table only.
- Columns: `run_attempt_id PK`, `run_id`, `parent_run_id`, `workflow_key`, `retry_ordinal`, `branch_path`, `started_at`, `ended_at`, `terminal_state`, `projection_version`, `source_high_watermark_id`.
3. Create `run_classifications`.
- Columns: `run_id PK`, `workflow_key`, `classification`, `failure_class`, `failure_reason_code`, `control_flow_reason_code`, `hard_fail`, `assisted_sla_deadline_at`, `assisted_sla_state`, `classified_at`, `classified_by`, `verification_passed`, `human_intervention`, `degraded_trigger_evidence_json`.
4. Create reliability snapshots.
- `workflow_reliability_windows`: `workflow_key`, `evaluated_at`, `window_kind (14d|50run)`, `weighted_reliability`, `degraded_rate`, `hard_fail_rate`, `gate_state`, `gate_state_reason`, `source_high_watermark_id`.
- `workflow_reliability_current`: `workflow_key PK`, `stricter_window_kind`, `weighted_reliability`, `degraded_rate`, `hard_fail_rate`, `gate_state`, `gate_state_reason`, `evaluated_at`, `source_high_watermark_id`.
5. Create escalation and governance tables.
- `workflow_escalations`: `id`, `workflow_key`, `run_id`, `trigger_type`, `window_bucket`, `dedupe_key`, `trigger_payload`, `status`, `opened_at`, `resolved_at`.
- Enforce one open escalation per dedupe key via partial unique index on `dedupe_key` where `status='open'`.
- `manual_override_audits`: `id`, `actor_id`, `acted_at`, `run_id`, `workflow_key`, `previous_state`, `new_state`, `reason`, `timestamp` (compatibility mirror, constrained equal to `acted_at`).
6. Create cost tables.
- `model_rate_cards`: `provider`, `model`, `effective_version`, `input_token_rate`, `output_token_rate`, `currency`, `effective_from`, `effective_to`.
- `workflow_budget_policies`: `workflow_key PK`, `monthly_budget_amount`, `warn_threshold_pct`, `enforce_threshold_pct`, `active`.
- `workflow_cost_rollups`: `workflow_key`, `period_key`, `canonical_cost_total`, `billed_cost_total`, `variance_total`, `utilization_pct`, `source_high_watermark_id`.
7. Create deployment counting table.
- `deployment_registrations`: `workflow_key PK`, `controls_enabled_at`, `gate_passed_at`, `compliance_hold_passed_at`, `counted_at`, `optimization_loop_count`, `countability_state`, `source_high_watermark_id`.
8. Migration dependency order: ledger -> projection structures -> classification -> reliability -> escalation/override -> cost -> deployment counting -> regex/check/partial-index constraints.
Impacted components:
- `database/migrations/*`
- `app/Models/TelemetryEventLedger.php`
- `app/Models/RunAttempt.php`
- `app/Models/RunClassification.php`
- `app/Models/WorkflowReliabilityWindow.php`
- `app/Models/WorkflowReliabilityCurrent.php`
- `app/Models/WorkflowEscalation.php`
- `app/Models/ManualOverrideAudit.php`
- `app/Models/ModelRateCard.php`
- `app/Models/WorkflowBudgetPolicy.php`
- `app/Models/WorkflowCostRollup.php`
- `app/Models/DeploymentRegistration.php`

## 4. API and Tool Contracts
1. Extend existing versioned API group in `routes/api.php`; keep pathing additive.
2. Standardize path identity to `{workflowKey}` and validate against required regex.
3. Add/extend endpoints:
- `GET workflows/{workflowKey}/health`
- `GET workflows/{workflowKey}/reliability`
- `GET workflows/{workflowKey}/cost`
- `GET workflows/{workflowKey}/escalations`
- `POST workflows/{workflowKey}/resume`
- `POST workflows/{workflowKey}/pause`
- `GET deployments/counting`
- `POST telemetry/events` (internal ingestion)
4. Pause/resume payload contract: `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`; persist canonical `acted_at` + compatibility `timestamp`.
5. Response contract additions:
- `gate_state` with explicit values including `insufficient_data`
- `countability_state` with `countable|not_countable|insufficient_data`
- separate `telemetry_delayed` and `telemetry_unobservable` indicators with optional `unobservable_reason`.
6. Runner emission contract: emit raw typed events only; normalization/classification/costing stay server-side.
Impacted components:
- `routes/api.php`
- `app/Http/Controllers/Api/V1/*`
- `app/Http/Requests/Agent/*`
- `app/Http/Middleware/AgentApiVersionHeader.php`
- `app/Support/Agent/*` validators/mappers

## 5. Event Contracts and Ingestion Semantics
1. Enforce envelope fields: `schema_name`, `schema_version`, `run_id`, `run_attempt_id`, `parent_run_id`, `workflow_key`.
2. Resolve and pin registry metadata during ingest, then persist immutable `schema_hash`, `normalizer_version`, and `registry_revision` on every ledger row.
3. Ingestion guarantees:
- at-least-once ingest
- idempotent dedupe on `(event_id, run_attempt_id)`
- immutable ledger writes only
4. Sequencing policy:
- accept out-of-order and late arrivals
- set `sequence_violation=true` for non-monotonic/gap detection
- deterministic projector order: `sequence_no -> event_ts -> ingested_at -> ledger_id`
5. Deterministic normalization:
- projector selects normalizer using pinned ledger metadata, never mutable live config
- missing usage/cost values are deterministically estimated and flagged.
6. Split telemetry observability semantics:
- `telemetry_delayed=true`: event arrived late but observable
- `telemetry_unobservable=true`: required telemetry cannot be reconstructed
- store optional `unobservable_reason`.
7. Outage behavior:
- fail-open with durable buffering/retry
- auto-protect when thresholds breach
- track delayed and unobservable streams independently.
8. Retention:
- raw ledger partition retention 7d
- normalized/aggregate projections retention 12m.
Impacted components:
- `app/Services/Telemetry/IngestionService.php`
- `app/Services/Telemetry/Normalizer/*`
- `app/Services/Telemetry/VersionedSchemaRegistry.php`
- `app/Services/Telemetry/ProjectionOrdering.php`
- `app/Jobs/ProcessTelemetryEvent.php`
- `app/Jobs/RebuildTelemetryProjections.php`

## 6. Reliability Scoring and Lifecycle
1. Implement locked weights: `Success=1.00`, `Assisted=0.70`, `Degraded=0.50`, `Failed=0.00`.
2. Enforce `skipped` as control-flow-neutral; exclude from weighted numerator/denominator.
3. Scoring unit rule: score only at `run_id`; attempts never inflate denominator.
4. Enforce success contract checks before assigning `Success`.
5. Deterministic degraded triggers: schema violation, output contract mismatch, partial objective completion, fallback path execution, retry-recovery success, non-blocking guardrail violation.
6. Hard-fail mapping: `hard_fail=true` when `failure_class=hard_fail` or terminal `policy_blocked`/`guardrail_blocked`.
7. Assisted SLA: approval metadata + verification within `<=24h`; expiry auto-reclassifies to `Failed`.
8. Evaluate after each classification update: Run -> Classified -> Scored -> Aggregated -> Window Evaluated -> Breach Check -> Escalate -> Pause -> Investigate -> Resume.
9. Gate policy:
- compute rolling `14d` and `50run`
- enforce stricter result
- threshold `weighted_reliability >=95`
- companion gate `degraded_rate <=3`.
10. Low-volume gating:
- if workflow has insufficient sample/window data, set `gate_state=insufficient_data`
- do not auto-pause
- mark workflow as non-countable until sufficient data exists.
11. Burst override: hard-gate on `2` consecutive hard fails or `3` hard fails in rolling `24h`.
12. Escalation dedupe: derive `dedupe_key=workflow_key + trigger_type + window_bucket`; keep one open escalation per dedupe key.
13. Pause semantic in scheduler: scheduler still evaluates due ticks; paused workflows produce `skipped` run records with control-flow reason `workflow_paused` for auditability.
Impacted components:
- `app/Enums/RunClassification.php`
- `app/Enums/FailureClass.php`
- `app/Enums/FailureReasonCode.php`
- `app/Services/Reliability/ClassificationEngine.php`
- `app/Services/Reliability/ScoringEngine.php`
- `app/Services/Reliability/WindowEvaluator.php`
- `app/Services/Reliability/GateEnforcer.php`
- `app/Console/Commands/EnforceAssistedSlaExpiry.php`
- `app/Services/Scheduler/*`

## 7. Cost Instrumentation and Governance
1. Build canonical cost calculator from `model_rate_cards` with provider/model/version lookup and deterministic token math.
2. Store billed provider cost separately for reconciliation; never use billed cost for enforcement.
3. Enforce monthly per-workflow budgets as authoritative policy key.
4. Keep daily/weekly windows alert-only.
5. Enforce guardrails:
- warn at 80 percent utilization
- enforce at 100 percent utilization
- allow in-flight completion
- block new runs until authorized resume.
6. Emit canonical `budget_breach` reason code when enforcement triggers.
7. Add `source_high_watermark_id` to cost rollups so lag/replay status is visible in UI/API.
Impacted components:
- `app/Services/Cost/CanonicalCostCalculator.php`
- `app/Services/Cost/BudgetPolicyEvaluator.php`
- `app/Services/Cost/CostRollupProjector.php`
- `app/Jobs/ProjectWorkflowCosts.php`
- `config/agent.php`

## 8. Authorization and Scope Enforcement
1. Implement RBAC capabilities:
- `central_on_call`: incident response and pause authority
- `workflow_owner_or_delegate`: resume approval authority
- `platform_admin`: override authority.
2. Enforce policy checks on pause/resume/escalation resolution endpoints and UI actions.
3. Require `reason` on every manual override; persist full audit metadata.
4. Expose audit trail via API and operator UI with actor, acted_at/timestamp, state transition, and related escalation.
5. Limit escalation resolution to one actor flow per open dedupe key to avoid concurrent conflicting actions.
Impacted components:
- `app/Policies/WorkflowGovernancePolicy.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Http/Controllers/Api/V1/WorkflowGovernanceController.php`
- `app/Models/ManualOverrideAudit.php`

## 9. Failure and Retry Behavior
1. Runtime retry policy remains deterministic: 2 retries with `30s` then `2m` backoff plus jitter.
2. Ingestion retries use queue retry semantics with idempotent writes and no duplicate scoring side effects.
3. Assisted SLA expiry job reclassifies unresolved assisted runs to failed.
4. Telemetry outage auto-protect:
- trigger on 3 consecutive delayed or unobservable runs for same workflow, or 15m continuous observability outage
- auto-pause new runs
- page central on-call.
5. Preserve in-flight completion during any pause/gate action.
6. Scheduler pause behavior is deterministic: due run ticks are persisted as skipped with reason `workflow_paused`; these do not enter reliability denominator.
Impacted components:
- `app/Jobs/RunAgentWorkflowJob.php`
- `app/Jobs/ProcessTelemetryEvent.php`
- `app/Services/Reliability/GateEnforcer.php`
- `app/Services/Alerting/*`
- `app/Services/Scheduler/*`

## 10. Observability and Operations
1. Add operational metrics:
- `ingest_latency_ms`
- `projection_lag_seconds`
- `sequence_violation_rate`
- `telemetry_delayed_streak`
- `telemetry_unobservable_streak`
- `gate_flip_count`
- `escalation_open_duration`
- `projection_high_watermark_gap`.
2. Add structured logs for classifier decisions, dedupe-key escalation decisions, gate triggers, pause/resume actions, and budget enforcement.
3. Add replay traceability markers linking current projection rows to `source_high_watermark_id`.
4. Expose observability summaries in operator surfaces:
- `/agent/system-overview` shows ingestion health, delayed vs unobservable split, projector lag, and high-watermark recency
- `/agent/escalations` shows dedupe key, trigger evidence, and projection lag at trigger time.
5. Add alert routing controls for central on-call and workflow owners with dedupe suppression based on escalation dedupe keys.
Impacted components:
- `app/Services/Observability/*`
- `app/Jobs/*` instrumentation hooks
- `resources/js/Pages/Agent/SystemOverview/Show.vue`
- `resources/js/Pages/Agent/Escalations/Index.vue`

## 11. User and Operator Surface Exposure
1. Add/confirm operator pages/routes:
- `/agent/deployments`
- `/agent/deployments/{workflowKey}`
- `/agent/escalations`
- `/agent/budgets`
- `/agent/system-overview`.
2. Add navigation discoverability:
- top-level `Agent Deployments` nav for authorized users
- contextual links from run detail to workflow health, attempts lineage, and escalation history
- breadcrumbs across deployments, workflow detail, escalations, budgets, and system overview.
3. Render required dashboard widgets per workflow: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
4. Add lifecycle ribbon: `Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop`.
5. Add status treatments:
- `gate_state=insufficient_data` badge with copy `Not countable yet`
- separate telemetry status chips for `Delayed` and `Unobservable`
- escalation card displays active dedupe key and incident thread.
6. Discoverability acceptance checks:
- each page reachable from main nav in <=2 clicks for authorized users
- run detail includes direct workflow health link
- governance actions visible only when policy allows
- each operator action creates visible audit entry
- low-volume workflows never display auto-pause action caused by insufficient data.
Impacted components:
- `routes/web.php`
- `app/Http/Controllers/*` web controllers
- `resources/js/Pages/Agent/Deployments/Index.vue`
- `resources/js/Pages/Agent/Deployments/Show.vue`
- `resources/js/Pages/Agent/Escalations/Index.vue`
- `resources/js/Pages/Agent/Budgets/Index.vue`
- `resources/js/Pages/Agent/SystemOverview/Show.vue`
- `resources/js/Layouts/*`

## 12. Core/Beta/Experimental Stability Matrix
1. Publish explicit matrix in docs and admin page.
2. Core: scheduler dispatch + paused-skip audit behavior, run state machine, policy guardrails, telemetry ledger ingest, schema pinning, reliability classifier/scorer, budget enforcement, escalation/pause/resume governance.
3. Beta: deployment counting automation, optimization-loop analytics, billed-cost variance reconciliation views.
4. Experimental: advanced delegation recovery heuristics, non-essential messenger automations, non-critical workflow templates.
5. Enforce matrix via feature flags and policy checks before page/API exposure.
Impacted components:
- `docs/system-overview.md`
- `PROJECT-STATUS.md`
- `config/agent.php`
- `resources/js/Pages/Agent/SystemOverview/Show.vue`

## 13. Documentation Alignment and Canonical Narrative
1. Maintain docs drift register against implementation reality, including org/messenger/feature-flag state.
2. Update `PROJECT-STATUS.md` as implementation source of truth.
3. Align `README.md` to canonical system overview and locked positioning statement.
4. Document identity and determinism contracts: `workflow_key` regex, `run_id`, `run_attempt_id`, schema pinning, scoring semantics.
5. Document pause semantics explicitly: paused workflows produce auditable skipped runs with reason `workflow_paused`.
6. Add docs governance rule requiring docs updates in same change set for runtime contract changes.
7. Add PR template checks for docs parity, operator discoverability, and API compatibility.
Impacted components:
- `PROJECT-STATUS.md`
- `README.md`
- `docs/system-overview.md`
- `.github/pull_request_template.md`

## 14. Phase 1 Engineering Workflow Templates
1. Ensure template registry includes and enables controls by default:
- `eng.repo-analysis.v1`
- `eng.code-implementation.v1`
- `eng.pr-quality-gate.v1`
- `eng.dependency-update-triage.v1`
- `eng.release-readiness.v1`.
2. Enforce template key validation using canonical regex.
3. For each template enforce: telemetry v1 envelope, schema pinning, classification hooks, cost tracking, escalation hooks, budget binding.
4. Add readiness checks before activation: schema compliance, scoring enabled, cost policy attached, governance roles bound, countability state initialized.
Impacted components:
- `config/agent.php`
- `app/Services/WorkflowTemplates/*`
- `database/seeders/*`

## 15. Backward Compatibility Strategy
1. Keep schema registry with major-version change rules and compatibility adapters for prior major.
2. During transition, dual-write from existing `AgentRunEvent` to telemetry ledger; ledger remains scoring source.
3. Backfill schema pin fields for legacy events at ingest time with deterministic derived markers.
4. Add replay command to reconstruct projections from ledger and verify determinism using pinned normalizer metadata.
5. Keep existing API consumer contracts stable through additive fields and serializers.
6. Run-attempt migration policy: populate `run_attempts` from replay; reject direct writes outside projector services.
7. Keep manual override API compatibility by accepting/returning `timestamp` while persisting `acted_at` canonically.
Impacted components:
- `app/Services/Telemetry/VersionedSchemaRegistry.php`
- `app/Services/Telemetry/LegacyAdapter.php`
- `app/Console/Commands/ReplayTelemetryLedger.php`
- `app/Transformers/*`
- `app/Services/Telemetry/RunAttemptProjector.php`

## 16. Rollout and Rollback Controls
1. Add feature flags:
- `agent.telemetry_v1_ingest`
- `agent.reliability_v1_scoring`
- `agent.cost_governance_v1`
- `agent.deployment_counting_v1`
- `agent.operator_dashboard_v1`.
2. Enable by dependency order:
- telemetry ingest
- normalization/projections
- scoring/gates
- cost enforcement
- deployment counting
- operator surfaces.
3. Rollback controls:
- disable downstream flags first (`dashboard -> counting -> cost -> scoring`)
- keep ledger ingest active unless storage incident mandates stop
- rerun projectors/replay to re-derive state from ledger high watermark.
4. Include migration guards for regex constraints and partial unique escalation index to avoid invalid existing records.
Impacted components:
- `config/agent.php`
- `app/Support/FeatureFlags/*`
- `app/Console/Commands/*`

## 17. Test Strategy
1. Unit tests:
- classification mapping for all `failure_class` and `failure_reason_code` paths
- weighted reliability formula with skipped exclusion
- stricter-window selection logic
- hard-fail burst detection
- canonical cost calculations and estimation flags
- run_id-only denominator enforcement with multiple attempts
- workflow key regex validation.
2. Feature tests:
- ingest idempotency for duplicate `(event_id, run_attempt_id)`
- out-of-order acceptance with `sequence_violation` flag
- pause/resume authorization and mandatory reason persistence
- assisted SLA expiry auto-reclassification
- budget enforcement with in-flight completion + new-run blocking
- workflow key route binding and additive response compatibility
- escalation dedupe key enforces one open escalation per bucket.
3. Integration tests:
- end-to-end `run -> ingest -> classification -> scoring -> gate -> escalation -> pause -> resume`
- replay reproduces identical reliability/health state
- outage policy triggers auto-pause + alerting
- projection determinism with randomized ledger insert order
- late-arriving convergence with no double-count scoring
- schema pin determinism: registry mapping changes do not alter replay output for pinned historical rows.
4. UI tests:
- route access and nav discoverability checks
- dashboard metric rendering per workflow
- governance action visibility by role
- audit trail visibility after overrides
- insufficient-data UI shows `Not countable yet` and no auto-pause behavior
- delayed vs unobservable telemetry badges render distinctly.
5. Added sharp-edge tests:
- insufficient data gating: `<50 runs` and `<14d` => `gate_state=insufficient_data`, no auto-pause, not countable
- escalation dedupe: repeated same-window breach => single open escalation/incident thread
- paused scheduler tick: due run recorded as skipped with `workflow_paused`, excluded from reliability denominator
- schema pin replay: stored pin selects original normalizer even after registry change.
6. Data integrity tests:
- immutable ledger updates rejected
- append-only run transition enforcement
- scoring/health derivable solely from replay
- projection high watermark monotonicity checks.
7. CI quality gates:
- Pint
- static analysis (`PHPStan` or `Psalm` per repo standard)
- Pest suite
- coverage threshold gate.
Impacted components:
- `tests/Unit/Telemetry/*`
- `tests/Unit/Reliability/*`
- `tests/Unit/Cost/*`
- `tests/Feature/Agent/*`
- `tests/Integration/DeploymentReliability/*`
- `tests/Feature/AgentUi/*`
- `.github/workflows/*`

## 18. Ordered Task DAG
1. D1: Finalize canonical docs baseline and drift register.
Dependencies: none.
2. D2: Enforce workflow identity contract and `workflow_key` regex rule across config, validators, routes, DB checks.
Dependencies: D1.
3. D3: Ship telemetry ledger migrations/models including schema pin fields and delayed/unobservable split.
Dependencies: D2.
4. D4: Implement ingest service with dedupe, out-of-order acceptance, and registry-pin persistence.
Dependencies: D3.
5. D5: Build deterministic normalizers/provider adapters selected by pinned metadata.
Dependencies: D4.
6. D6: Build projection pipelines (`run_attempts`, reliability, cost, deployment) with `source_high_watermark_id` and write protections.
Dependencies: D3, D5.
7. D7: Implement failure taxonomy enums and classification engine including control-flow subreasons.
Dependencies: D5.
8. D8: Implement run-level scoring, stricter-window evaluator, insufficient-data gating, and burst overrides.
Dependencies: D7.
9. D9: Implement escalation engine with dedupe key and single-open incident enforcement.
Dependencies: D8.
10. D10: Implement pause/protect controls and scheduler paused-tick-to-skipped behavior.
Dependencies: D9.
11. D11: Implement assisted SLA expiry enforcement and reclassification job.
Dependencies: D7.
12. D12: Implement canonical cost calculator, rate cards, budget policies, and enforcement.
Dependencies: D5.
13. D13: Implement deployment counting state machine with non-countable/insufficient-data handling.
Dependencies: D8, D12, D10.
14. D14: Implement operator APIs for health, reliability, cost, escalations, and governance actions.
Dependencies: D9, D12, D13.
15. D15: Implement operator web routes/pages/navigation/discoverability and audit/incident views.
Dependencies: D14.
16. D16: Add five engineering templates with controls-enabled defaults and readiness checks.
Dependencies: D8, D12, D10.
17. D17: Implement replay tooling, dual-write adapters, and compatibility serializers.
Dependencies: D3, D5, D6.
18. D18: Implement observability metrics/logging/alerts including high-watermark lag surfacing.
Dependencies: D9, D12, D14.
19. D19: Complete full unit/feature/integration/UI/data-integrity tests and CI gates.
Dependencies: D4 through D18.
20. D20: Publish final docs set (`PROJECT-STATUS`, `README`, `system-overview`, stability matrix, scorecard protocol).
Dependencies: D15, D16, D19.

## 19. Acceptance Closure Mapping
1. Telemetry closure: envelope and identity fields implemented; schema pin metadata persisted; idempotent dedupe active; out-of-order accepted with sequence violations marked; append-only ledger verified; deterministic normalization active; delayed/unobservable split active with outage auto-protect.
2. Reliability closure: success contract, degraded triggers, assisted SLA expiry, run_id scoring semantics, weighted formula, stricter-window gate, insufficient-data non-pausing behavior, hard-fail burst override, and escalation dedupe implemented.
3. Governance closure: escalation and auto-protect active; RBAC pause/resume authority contract enforced; manual override audits persisted with required fields and acted_at/timestamp compatibility.
4. Cost closure: canonical enforcement cost active, billed-cost reconciliation separated, monthly budget policy and 80/100 thresholds active, cost rollups include high watermark.
5. Operator closure: dashboard metrics and lifecycle ribbon visible per workflow; pages/routes/navigation discoverable; governance actions permission-gated; audit entries visible; insufficient-data and telemetry observability statuses visible.
6. Deployment closure: counted deployment contract enforced, non-countable states explicit, optimization loop measurable, five engineering templates runnable with controls enabled.
7. Documentation closure: drift register resolved; `PROJECT-STATUS` and `README` aligned to canonical system overview, identity/versioning conventions, and locked positioning statement.

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

- Legacy services may still write or query by `workflow_id`, causing mixed identity joins until all read/write paths are migrated to `workflow_key`.
- Existing historical events may lack enough metadata for strict schema pin backfill, requiring deterministic fallback rules and explicit derived-pin markers.
- Regex enforcement on `workflow_key` can reject existing nonconforming records unless migration includes normalization/remediation scripts.
- Partial unique escalation index may fail migration if duplicate open incidents already exist for equivalent dedupe buckets.
- If projector jobs lag, high-watermark fields can expose stale health/cost/deployment state that operators may misinterpret as live truth.
- Separating `telemetry_delayed` and `telemetry_unobservable` can be inconsistently implemented across services, producing conflicting outage behavior.
- Paused scheduler-to-skipped conversion can inflate skipped volume if pause toggles flap, masking true throughput without clear dashboards.
- Low-volume `insufficient_data` policy may delay deployment counting longer than operators expect unless UI language is explicit and consistent.
- Dual-write transition can still produce diverging API views if some endpoints read legacy projections while others read new projections.
- RBAC role mapping to existing org/user models may create approval bottlenecks or over-broad override access without explicit seeding/migration.
- Canonical cost and billed-cost variance may be large for some providers, reducing trust if reconciliation views are not operator-friendly.
- Documentation parity can regress if PR enforcement does not block schema/contract changes without corresponding docs updates.


## Assumptions

- Laravel 12 + Jetstream + Inertia remains the runtime and control-plane foundation.
- PostgreSQL is available for append-only ledger and projection storage with required index/constraint support.
- Redis + Horizon remains available for ingestion, projection, classification, and enforcement jobs.
- Current guardrails (`CommandPolicy`, `PathPolicy`, `EnvPolicy`) remain authoritative and unchanged in this phase.
- Workflow templates are uniquely addressable by stable `workflow_key` values and can be migrated to regex-compliant keys.
- Run lifecycle events can provide or be deterministically adapted to provide `run_id`, `run_attempt_id`, and `parent_run_id`.
- Feature-flag framework supports independent toggles for telemetry, scoring, cost governance, deployment counting, and operator UI surfaces.
- Schema registry source is version-controlled and can expose stable schema hashes/revisions for ingest pinning.
- Central on-call paging channel exists and accepts platform-generated escalation notifications.
- Authenticated actor context is always available for manual override writes.
- Operator UI stack can add pages, navigation entries, and policy-gated actions without framework replacement.
- Repository workflows and review process can enforce docs updates alongside runtime contract changes.

