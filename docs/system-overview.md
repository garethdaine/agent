# System Overview (Canonical Phase 1 Contract)

We help companies deploy AI agents safely and keep them reliable in production.

## Scope

Phase 1 is intentionally bounded to a local-first Laravel runtime with provider-agnostic telemetry contract semantics. The vertical wedge is engineering workflows (`repo analysis`, `code tasks`) with deterministic reliability and cost governance.

## Canonical Runtime Contracts

- Workflow key regex: `^[a-z0-9._-]+[.]v[1-9][0-9]*$`.
- Reliability scoring formula: `WeightedReliability = (sum(run_weight) / count(scored_runs)) * 100`.
- Reliability gates evaluate both rolling `14-day` and rolling `50-run` windows and enforce the stricter result.
- Assisted SLA breach auto-reclassifies to `Failed` after `<=24h`.
- hard_fail is set when `failure_class=hard_fail` or when `policy_blocked` / `guardrail_blocked` terminates execution.
- `event_id` is producer-generated and unique within a `run_attempt_id`; global uniqueness is not required.
- Terminalization is catalog-driven: an attempt is terminal only when an ingested event has `terminal=true` and the event type is in the configured terminal catalog.
- projection tables are internal infrastructure data; no external ad hoc/reporting queries are permitted.
- projection relations are isolated to schema `agent_projection`; relations are moved out of `public` when present.
- projection access policy: reporting/analytics roles are denied direct table DML (`SELECT/INSERT/UPDATE/DELETE`), app role is read-only, projector role is scoped to projection writes (`INSERT/UPDATE`, plus `SELECT` required for update predicates), and admin/migration role retains full privileges.
- controllers must not query projection relations directly; projection reads flow through repository/service boundaries with active-build scoping.
- Only one rebuild may be active at a time.
- `active_build_age_seconds` = server UTC now - active projection build `activated_at`.

## Reliability and Taxonomy

- Primary KPI scope: scheduled runs only.
- Secondary KPI scope: all production runs.
- `skipped` is neutral control flow and excluded from weighted reliability numerator/denominator.
- Failure taxonomy is canonicalized by `failure_class` + `failure_reason_code` with deterministic mapping to scoring and escalation.

## Telemetry and Data Integrity

- Ingest semantics: at-least-once, idempotent dedupe by (`event_id`, `run_attempt_id`), monotonic per-run sequencing.
- System of record: append-only telemetry ledger in PostgreSQL with deterministic projections.
- Projection serving rule: runtime/API/UI reads are always scoped to the active projection build.
- Replay rule: rebuild into non-serving scope, run parity checks, atomically activate active build pointer.

## Cost Governance

- Enforcement uses internal canonical token-cost tables (versioned rate cards).
- Billed provider cost is retained for reconciliation only and never overrides enforcement values.
- Monthly workflow budget is authoritative; daily/weekly windows are alert-only.

## Operator Surfaces

- Deployment health widgets per workflow: `ReliabilityScore`, `DegradedRate`, `HardFailRate`, `BudgetUtilization`, `EscalationEvents`.
- Governance controls: pause/resume, escalation lifecycle, replay-build controls, and mandatory manual override audit fields.
- Build freshness signal: `active_build_age_seconds` exposed on system overview and replay views.

## Known Risk Boundaries

- Known risk boundary: event-id stability mistakes from producers can break dedupe semantics.
- Known risk boundary: terminal catalog drift can misclassify attempt terminalization until catalog updates and replay complete.
- Known risk boundary: projection query restrictions must be enforced so external consumers cannot bypass active-build scoped APIs.
