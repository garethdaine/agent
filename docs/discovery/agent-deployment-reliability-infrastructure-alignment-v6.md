# Requirements Discovery Summary

Session: 17

**Phase 1 System Goal**
- Establish a governed execution environment for autonomous workflows where every run is deterministically observed, classified, scored for reliability, cost-governed, and automatically escalated when degraded.

**Strategic Alignment**
- Platform direction is locked to **Agent Deployment & Reliability Infrastructure**.
- Phase 1 vertical wedge is locked to **engineering agent workflows** (`repo analysis`, `code tasks`).
- No rewrite: preserve current Laravel local-first runtime and harden around telemetry, reliability, and cost governance.

**Baseline Confirmed (Preserve As-Is)**
- Core entities/services already present: `AgentJob`, `AgentJobRun`, `AgentRunEvent`, scheduler dedupe/watermark dispatch, Redis+Horizon execution, atomic state transitions, stale-run reconciliation, policy guardrails (`CommandPolicy`, `PathPolicy`, `EnvPolicy`), feature flags, audit logging.
- Advanced subsystems retained: delegation DAG + recovery, requirements discovery workflow, deterministic repo analysis pipeline, docs coverage controls, messenger control plane.

**Canonical Phase 1 Reliability Contract (Final)**
- Primary KPI scope: **scheduled runs only**; secondary KPI tracks all production runs.
- Run classification and weights (authoritative v1):
  - `Success=1.00` (autonomous verified objective met)
  - `Assisted=0.70` (human-approved fallback, verified within SLA)
  - `Degraded=0.50` (verified output quality reduction without human approval)
  - `Failed=0.00`
- `skipped` policy: control-flow neutral; excluded from weighted numerator/denominator; logged for audit.
- `skipped` trigger examples for classification discipline: upstream dependency not ready, workflow pause active, schedule suppression window.
- **Run Success Contract (explicit)**: a run is `Success` only if execution completed without `hard_fail`, no blocking guardrail/policy breach occurred, objective verification passed, and no human intervention was required.
- Assisted classification rule: human approval fallback path plus verification within SLA.
- Assisted SLA breach rule: if verification does not complete within `<=24h`, auto-reclassify to `Failed`.
- Degraded classification rule: automated run with objective quality reduction and no human approval.
- Failed classification rule: objective not met, timeout/unverified outcome, blocking guardrail/policy breach, or terminal hard failure.
- **Hard-fail mapping rule (explicit)**: `hard_fail` is set when `failure_class=hard_fail` or when `policy_blocked` / `guardrail_blocked` conditions terminate execution.
- **Deterministic degraded triggers**: `schema` violation, output contract mismatch, partial objective completion, fallback path execution, retry-recovery success, non-blocking guardrail violation; optional human downgrade supported.
- Retry policy: `2` retries, exponential backoff (`30s`, `2m`) + jitter.
- Reliability gate policy: compute both rolling `14-day` and rolling `50-run`; enforce the stricter result.
- Weighted reliability formula: `WeightedReliability = (sum(run_weight) / count(scored_runs)) * 100`; skipped runs excluded.
- Baseline threshold: weighted reliability `>=95%`.
- Companion quality gate: degraded-output rate `<=3%`.
- Hard-fail overrides: immediate hard-gate on `2` consecutive `hard_fail` OR `3` `hard_fail` within rolling `24h`.
- Escalation: immediate on hard-fail/guardrail breach; escalate on composite reliability `<95` in `24h`.
- Enforcement: let in-flight runs finish, pause/block new runs, manual approval required to resume.
- **Pause/Resume authority contract**: central on-call is primary responder; workflow owner/delegate can approve resume; platform admin has override authority; mandatory reason + audit trail.
- Manual override audit schema (required): `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`.

**Phase 1 Reliability Lifecycle (Deterministic)**
- `Run -> Classified -> Scored -> Aggregated -> Reliability Window Evaluated -> Threshold Breach? -> Escalate -> Pause -> Investigate -> Resume`.
- Reliability window evaluation timing: evaluation occurs after each run classification update.

**Canonical Failure Taxonomy (Final)**
- Model: two-tier + multi-label capture + derived primary class for scoring/alerting.
- `failure_class`: `hard_fail | soft_fail | degraded | control_flow`.
- `failure_reason_code` enum v1: `timeout`, `rate_limited`, `guardrail_blocked`, `approval_required`, `policy_blocked`, `dependency_error`, `infra_error`, `provider_error`, `validation_error`, `output_quality_fail`, `budget_breach`, `telemetry_unobservable`, `skipped`, `cancelled`.

**Canonical Telemetry Contract (Final)**
- Every event carries strict envelope: `schema_name`, `schema_version`.
- Ingestion semantics: at-least-once, idempotent dedupe (`event_id` + `run_attempt_id`), monotonic per-run sequencing, immutable append-only raw records, deterministic normalization.
- System of record: PostgreSQL append-only telemetry ledger + async projections.
- Identity model: `run_id` (logical workflow execution), `run_attempt_id` (retry/branch attempt), `parent_run_id` (delegation lineage).
- Scoring semantics: reliability scoring is computed at `run_id` level; `run_attempt_id` contributes telemetry lineage and diagnostics only, not independent score denominator entries.
- Granularity: mandatory run-level for all; mandatory step/branch telemetry for engineering workflows.
- Missing provider fields: accept run, deterministically estimate, mark `estimated`.
- Outage policy: fail-open with durable buffering/retry + `telemetry_delayed`; if unobservable breach persists (`3` consecutive unobservable/delayed runs for same workflow OR `15m` continuous outage), auto-pause new runs and page central on-call.
- Retention topology: raw stream `7d`; normalized run/aggregate telemetry `12m`.
- **Data integrity guarantees**: events immutable, runs append-only, ledger replayable, and scoring/health state derivable from ledger data.

**Canonical Cost Governance (Final)**
- Authoritative enforcement cost: deterministic internal canonical token-cost using model-versioned rate tables.
- Provider billed cost: stored separately for reconciliation/variance/invoice audit; never overrides enforcement cost.
- Budget key: **monthly per workflow** is authoritative.
- Weekly/daily windows are alert-only subwindows.
- Guardrails: warn at `80%`, enforce at `100%`; allow in-flight completion, block new runs, require explicit approved resume.

**Deployment Health Dashboard Contract (Per Workflow)**
- `ReliabilityScore`
- `DegradedRate`
- `HardFailRate`
- `BudgetUtilization`
- `EscalationEvents`

**Deployment Counting Contract (Final)**
- One counted production deployment = one distinct workflow with telemetry + reliability scoring + cost enforcement + escalation active.
- Must pass `>=95%` gate on stricter (`14-day` vs `50-run`) window and sustain compliance for an additional `14-day` compliance period before counting.

**Deployment Lifecycle (Phase 1)**
- `Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop`.
- Optimization loop definition: repeated deployment cycle where workflow reliability improvements are implemented and redeployed based on telemetry insights.

**Phase 1 Template Set (Seed Workflows)**
- `eng.repo-analysis.v1`
- `eng.code-implementation.v1`
- `eng.pr-quality-gate.v1`
- `eng.dependency-update-triage.v1`
- `eng.release-readiness.v1`

**Model Independence Contract**
- Reliability scoring and governance are model-agnostic across providers (`OpenAI`, `Anthropic`, local models, CLI/API runtimes) and keyed to canonical taxonomy/telemetry semantics, not provider-native status codes.

**Phase 1 Success Definition (Investor-Level)**
- `3-5` production workflows counted under the deployment contract.
- Sustained `>=95%` reliability gate compliance.
- Cost governance enforcement active on monthly workflow budgets.
- Automatic escalation and pause/resume governance functioning.
- Telemetry ledger and replayable projections fully operational.
- At least `2` counted workflows in recurring optimization loops (as defined in deployment lifecycle).
- Documentation accurately reflects actual system state and supports a publishable case study.

**Docs and Positioning Alignment**
- Deliver authoritative docs: `PROJECT-STATUS.md` + aligned `README` + canonical System Overview source.
- Correct docs drift against implementation (including org/messenger/feature-flag state).
- External USP lock: `We help companies deploy AI agents safely and keep them reliable in production.`

## Goals

- Consolidate the MVP into a single Phase 1 operating model centered on engineering workflow deployments.
- Standardize provider-agnostic telemetry with a versioned canonical envelope and deterministic normalization.
- Implement Reliability Score v1 with explicit success contract, deterministic degraded detection, locked weights, strict gates, burst overrides, and escalation/auto-protect behaviors.
- Implement run-level scoring semantics where `run_id` is the scoring unit and `run_attempt_id` is telemetry lineage only.
- Implement assisted verification SLA enforcement with automatic failed reclassification on SLA expiry.
- Implement cost instrumentation and governance with deterministic canonical costing and monthly per-workflow budget enforcement.
- Define a deterministic Phase 1 reliability lifecycle from run classification through escalation, pause, investigation, and resume.
- Publish a per-workflow deployment health dashboard contract (`ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`).
- Remove documentation drift and publish a single authoritative system narrative tied to real implementation status.
- Define and enforce core/beta/experimental boundaries so stabilization work targets production leverage only.
- Operationalize deployment counting and promotion rules so Phase 1 scorecards are deterministic and auditable.
- Enable reproducible pilot templates for engineering workflows with telemetry, reliability, and cost controls enabled by default.


## Constraints

- Preserve local-first execution philosophy and existing runtime architecture; no rewrite.
- Remain provider-agnostic across CLI and API agent providers.
- Maintain deterministic, auditable processing (append-only telemetry, idempotent ingestion, traceable scoring).
- Keep modular feature-flag architecture; avoid bespoke client-specific branching.
- Do not prioritize expansion of messenger surfaces, new provider additions, or delegation complexity growth in this phase.
- Telemetry schema changes must use explicit versioning with major-version rules for breaking changes and compatibility windows.
- Reliability and budget enforcement must support RBAC-governed human override with mandatory audit metadata.
- Manual override records must include `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, and `reason`.
- Scoring and alerting must use canonical taxonomy/reason codes rather than provider-specific error semantics.
- Reliability scoring and governance must remain model-agnostic across OpenAI/Anthropic/local models.
- Run attempts must not inflate reliability denominator; scoring remains at logical run level.
- Data integrity guarantees are mandatory: immutable events, append-only runs, replayable ledger, derivable state from ledger.


## Acceptance Criteria

- `PROJECT-STATUS.md` and `README` are updated to match current implementation and include canonical System Overview references; documented drift items are closed.
- Telemetry v1 schema is implemented with required envelope fields (`schema_name`, `schema_version`) and canonical run identity fields (`run_id`, `run_attempt_id`, `parent_run_id`).
- Telemetry ingestion enforces at-least-once + idempotent dedupe by (`event_id`,`run_attempt_id`) with monotonic sequencing checks and append-only persistence in PostgreSQL ledger.
- Telemetry normalization pipeline is centralized and provider-agnostic; runners emit raw typed events only.
- Missing usage/cost fields are deterministically estimated and flagged (`estimated`), with delayed ingestion flagged (`telemetry_delayed`).
- Reliability Score v1 uses locked weights: Success=1.00, Assisted=0.70, Degraded=0.50, Failed=0.00; `skipped` excluded from weighted denominator/numerator.
- Canonical run success contract is enforced: success requires non-hard-failed completion, no blocking guardrail breach, objective verification pass, and no human intervention.
- Deterministic degraded classification is implemented with explicit triggers: schema violation, output contract mismatch, partial objective completion, fallback path execution, retry recovery success, and non-blocking guardrail violation.
- Weighted reliability formula is implemented exactly as `(sum(run_weight) / count(scored_runs)) * 100`, with skipped runs excluded.
- Reliability scoring is computed at `run_id` level; `run_attempt_id` telemetry is captured for retries/branches but does not create independent scored runs.
- Assisted success requires explicit human-approval metadata and verification within `<=24h`; unresolved/expired approvals are automatically reclassified and scored failed.
- Skipped run examples are codified and validated as control-flow outcomes (upstream dependency not ready, workflow pause, schedule suppression) and remain excluded from reliability scoring.
- Reliability gating computes both rolling 14-day and rolling 50-run windows and enforces the stricter result with threshold `>=95%` weighted reliability.
- Reliability window evaluation executes after each run classification update.
- Degraded-output companion gate `<=3%` is active for alerting; hard-fail unresolved incident SLA (`24h`) is enforced.
- Hard-fail burst override is active: auto hard-gate on 2 consecutive hard_fail or 3 hard_fail in rolling 24h.
- Hard-fail mapping is explicit and enforced: `failure_class=hard_fail` or terminal `policy_blocked`/`guardrail_blocked` outcomes map to hard-fail handling.
- Auto-protect behavior is active: in-flight runs complete, new runs pause/block, resume requires authorized RBAC action with reason logging.
- Pause/resume authority contract is enforced: central on-call primary response, workflow owner/delegate resume approval, platform admin override, all auditable.
- Manual override audit schema is implemented and persisted with required fields: `actor_id`, `timestamp`, `run_id`, `previous_state`, `new_state`, `reason`.
- Cost service uses deterministic internal canonical costing as authoritative for enforcement and ROI; provider billed cost is stored separately for reconciliation/variance.
- Budget policy is enforced monthly per workflow with 80% warning and 100% hard enforcement; daily/weekly are alert subwindows only.
- Telemetry outage protection is active: after 3 consecutive unobservable/delayed runs for same workflow or 15m continuous outage, new runs auto-pause and central on-call is paged.
- Failure taxonomy v1 is implemented with `failure_class` plus normalized `failure_reason_code` enum including: timeout, rate_limited, guardrail_blocked, approval_required, policy_blocked, dependency_error, infra_error, provider_error, validation_error, output_quality_fail, budget_breach, telemetry_unobservable, skipped, cancelled.
- Data integrity guarantees are test-verified: immutable events, append-only runs, replayable ledger, and derivable scoring/health state from ledger replay.
- Deployment health dashboard exposes per-workflow `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, and `EscalationEvents`.
- Deployment counting contract is enforced: a counted deployment is one distinct workflow with telemetry+scoring+cost+escalation enabled and sustained gate compliance before counting.
- Deployment lifecycle states are implemented and observable in control-plane status (`Workflow Created -> Beta Deployment -> Reliability Observed -> Production Gate Passed -> Deployment Counted -> Optimization Loop`).
- Optimization loop semantics are documented and measurable as repeated telemetry-informed reliability improvement and redeployment cycles.
- Phase 1 engineering templates exist and are runnable with controls enabled: `eng.repo-analysis.v1`, `eng.code-implementation.v1`, `eng.pr-quality-gate.v1`, `eng.dependency-update-triage.v1`, `eng.release-readiness.v1`.
- Primary reliability KPI uses scheduled-run population; secondary KPI for all production runs is exposed in scorecards/alerts.
- Phase 1 success scorecard confirms 3-5 counted production workflows, at least 2 in recurring optimization, and case-study-ready evidence with telemetry/reliability/cost governance active.

