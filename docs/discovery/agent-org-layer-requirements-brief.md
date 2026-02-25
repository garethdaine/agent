# Requirements Discovery Brief — Agent Org Layer (AI Workforce)

## 1. Overview

**Feature Name:** Agent Org Layer (AI Workforce Orchestration)

**Purpose:** Add a first-class organizational operating layer on top of Agent so users can define named AI employees, reporting lines, councils, and recurring rituals while preserving deterministic execution, strict policy boundaries, and local-first deployment.

This brief consolidates:

1. Org-layer requirements from this document.
2. Competitive gap findings from OpenClaw review.
3. Locked discovery decisions already captured in:
  1. `adversarial-reviewer-discovery-requirements-brief.md`
  2. `agent-mcp-v5.md`
  3. `agent-memory-delegation-v2.md`
  4. `messenger-control-plane-v4.md`
  5. `natural-language-scheduling.md`
  6. `reconstructed-intelligent-delegation-for-agent-v3.md`

The Org Layer is a composition feature, not a replacement runtime.

---

## 2. Gap-Driven Positioning (OpenClaw Review)

From the comparative review, the highest-value gaps for Agent are:

1. No first-class multi-surface control plane that unifies org workflows across chat, API, and machine clients.
2. No explicit org abstraction over existing execution primitives (roles, councils, rituals, reporting graph).
3. Limited session-to-session orchestration semantics exposed to users as operational workflows.
4. No productized governance surface for budget, escalation, and institutional memory across recurring routines.

This brief addresses those gaps **within current planned functionality** rather than copying OpenClaw's full platform breadth.

---

## 3. Goals

1. Deliver a first-class org model (named agent profiles, reporting edges, councils, ritual templates).
2. Reuse Delegation DAG execution for org workflows instead of introducing a parallel engine.
3. Provide recurring ritual automation using existing cron infrastructure plus NL scheduling builder patterns.
4. Unify human and machine control via Messenger Control Plane and MCP contracts.
5. Ensure all org workflows are memory-aware using the four-layer memory architecture constraints.
6. Add adversarial quality gates to protect summary/plan artifacts used by org rituals.
7. Preserve deterministic, auditable, policy-compliant local-first operation.

---

## 4. Non-Goals (Phase 1)

1. Replacing existing DelegationGraph, DelegationTask, or ExecuteAgentRun internals.
2. Building a broad channel/plugin marketplace parity layer.
3. Replacing existing memory architecture or introducing a separate memory runtime.
4. Building hosted multi-tenant SaaS orchestration.
5. Introducing unrestricted autonomous command execution.
6. Delivering OpenClaw-style mobile node/device control in this phase.

---

## 5. Canonical Reuse Contracts (Locked)

### 5.1 Delegation Runtime Contract

Org execution must run through existing Delegation components and constraints:

1. Graph/task states, retries, recovery classification, and verification pipeline remain delegated to `delegation` services.
2. No contract may expand authority beyond existing `CommandPolicy`, `PathPolicy`, `EnvPolicy` boundaries.
3. Eager-ready/lazy-dispatch and max-parallel limits remain governed by `config/delegation.php`.
4. Human approval steps remain non-blocking and resumable.
5. Delegation structural/runtime limits remain inherited (including max tasks per graph and concurrent graph limits) unless changed in delegation config.

### 5.2 MCP Contract

Org machine control must obey `agent-mcp-v5` lock-ins:

1. Scope dimensions remain exactly `tenant`, `environment`, `role`.
2. Evaluation order remains scope check then permission claim check.
3. Transport semantics remain poll plus websocket with stable app error codes.
4. Tool contracts remain versioned and compatibility-gated through CI matrix rules.

### 5.3 Memory Contract

Org layer memory behavior must obey memory v2 lock-ins:

1. No API mode: Core and Working memory plus BM25 retrieval only.
2. API mode: extraction, embeddings, graph, and RRF enabled with graceful degradation.
3. Memory failures never block agent run completion.
4. Context injection remains wrapper-file based with adaptive token budget.

### 5.4 Messenger Contract

Org user-facing operations via chat must obey messenger v4 lock-ins:

1. Phase A channel scope remains Slack and Telegram.
2. Identity linking uses signed one-time token flow with Redis primary and DB fallback.
3. Destructive actions honor confirmation requirements.
4. Mutations are async with thread-aware progress updates.

### 5.5 NL Scheduling Contract

Ritual schedule authoring must obey F-011 lock-ins:

1. Cron remains canonical runtime format.
2. Rule-based parser first, async LLM fallback only for low confidence.
3. Confidence threshold, idempotent parse attempts, and timeout behavior remain unchanged.
4. Active-hours semantics and ISO day indexing remain canonical.

### 5.6 Adversarial Reviewer Contract

Artifacts used by org workflows must pass reviewer gates:

1. Summary and plan stages use bounded review loops.
2. Clarification routing for summary uses existing open-question queue only.
3. Plan stage does not allow clarification in this phase (revise-or-fail only).
4. Reviewer output remains strict schema-validated JSON.
5. Feature flags and warn-only rollout mode are preserved.

---

## 6. Core User Flows

### 6.1 Define Org Structure

1. User creates named org agents mapped to delegatee profiles and capabilities.
2. User sets role, reporting target, authority narrowing, default output contract.
3. System validates contracts against delegation and policy constraints.

### 6.2 Run Rituals

1. User defines ritual template with phases, role mapping, and schedule.
2. Scheduler triggers ritual run and instantiates DelegationGraph.
3. System posts progress and completion through configured messenger and MCP surfaces.

### 6.3 Council Review

1. User defines council membership, perspectives, and synthesis strategy.
2. System fans out shared evidence package to member tasks.
3. Chair synthesis task produces structured conclusion and conflict log.
4. Adversarial reviewer gate can require revision before final acceptance.

### 6.4 Escalation and Approval

1. Risk, verification, or budget threshold triggers escalation state.
2. User receives approval request in supported surfaces.
3. Resolution resumes execution without replaying successful prior phases.

---

## 7. Functional Requirements

### 7.1 Org Agent Profiles

Each profile must include:

1. Unique `name` within user scope.
2. `role_slug` and `role_description`.
3. Delegation capability bindings.
4. Delegatee profile binding.
5. Narrowing-only authority overrides.
6. Optional parent/manager relation.
7. Default output schema contract.

### 7.2 Ritual Templates

Each ritual template must include:

1. Trigger definition (`cron` plus timezone, optional NL source metadata).
2. Phase graph (ordered or DAG).
3. Phase-to-role mapping.
4. Context inputs (memory, previous run outputs, optional external evidence).
5. Verification strategy requirements per phase.
6. Delivery targets (messenger, MCP-visible status, optional export).

### 7.3 Council Templates

Council templates must support:

1. Member list with perspective labels.
2. Shared evidence payload definition.
3. Member response schema.
4. Synthesis mode (`majority`, `weighted`, `chair_decides`).
5. Final report sections for agreements, conflicts, and recommended actions.

### 7.4 Cost and Governance

System must provide:

1. Per-agent token/runtime usage rollups.
2. Per-ritual run and template cost snapshots.
3. Threshold policies: warning, approval-required, hard-stop.
4. Escalation event generation and auditable decision outcome.

### 7.5 Quality Gates

1. Summary and plan artifacts used in ritual execution must pass adversarial review state checks.
2. Failed or exhausted reviewer loops must produce explicit terminal failure reasons.
3. Reviewer findings must be persisted with evidence references.

### 7.6 Control Surfaces

1. Messenger commands for listing, triggering, pausing, resuming rituals and resolving escalations.
2. MCP org endpoints consistent with v5 schema/version discipline.
3. Read/list operations support scoped filtering; mutating operations remain deny-on-mismatch.

---

## 8. Data Model Additions (Proposed)

Additive, user-scoped tables:

1. `org_agent_profiles`
2. `org_reporting_edges`
3. `org_ritual_templates`
4. `org_ritual_runs`
5. `org_council_templates`
6. `org_cost_ledgers`
7. `org_escalations`
8. `org_artifact_reviews`

Relations must reference existing delegation, job/run, and user entities without modifying their base schemas.

---

## 9. API Surface (Proposed)

Under `/agent/api/v1/org/`:

1. `GET /agents`
2. `POST /agents`
3. `PUT /agents/{id}`
4. `GET /rituals`
5. `POST /rituals`
6. `POST /rituals/{id}/run`
7. `POST /rituals/{id}/pause` (schedule-level pause; does not pause in-flight run state)
8. `POST /rituals/{id}/resume` (schedule-level resume; does not mutate terminal run semantics)
9. `GET /ritual-runs/{id}`
10. `GET /councils`
11. `POST /councils`
12. `GET /costs/summary`
13. `POST /escalations/{id}/resolve`
14. `GET /reviews/{id}`

All mutating endpoints require auth, scope checks, policy checks, and audit emission.

---

## 10. States and Events

### 10.1 Ritual Run State

`draft | scheduled | queued | running | waiting_approval | reviewing | succeeded | failed | cancelled | partial`

### 10.2 Required Events

1. `org_ritual_scheduled`
2. `org_ritual_started`
3. `org_ritual_phase_completed`
4. `org_ritual_escalation_requested`
5. `org_ritual_escalation_resolved`
6. `summary_review_started`
7. `summary_review_passed`
8. `summary_review_failed`
9. `summary_review_clarification_needed`
10. `plan_review_started`
11. `plan_review_passed`
12. `plan_review_failed`
13. `org_budget_threshold_exceeded`
14. `org_ritual_completed`

Event ordering must follow existing run/event monotonic sequencing and cursor-safe recovery rules.

---

## 11. Security and Policy Constraints

1. Org-layer execution inherits existing command/path/env restrictions with no widening.
2. Authority overrides may only narrow effective permissions.
3. Messenger account-link, signature verification, and replay protections remain mandatory.
4. MCP scope mismatch defaults to deny except locked read/list/stream filter cases.
5. Artifact review and escalation mutations must be auditable and idempotent.
6. No raw command payloads accepted from messenger or MCP org endpoints.

---

## 12. Observability and Analytics

Required telemetry:

1. Ritual lifecycle and phase timings.
2. Escalation frequency and resolution latency.
3. Reviewer loop metrics (attempt count, fail reasons, clarification frequency).
4. Cost/budget threshold crossings.
5. Per-agent contribution and failure hotspots.
6. Queue depth and dead-letter visibility for org-related jobs.

Logging must use structured correlation IDs across messenger ingress, org orchestration, delegation execution, and artifact review outcomes.

---

## 13. Testing Requirements

1. Unit tests for org profile validation, authority narrowing, ritual template validation, council synthesis rules, and cost threshold evaluation.
2. Feature tests for:
  1. ritual scheduling and DelegationGraph instantiation,
  2. messenger-triggered run-now and escalation resolution,
  3. MCP org endpoint scope enforcement,
  4. adversarial reviewer pass/revise/clarification flows,
  5. hard-stop budget behavior.
3. Regression tests proving no behavior change when org feature flags are disabled.
4. End-to-end tests for:
  1. one council ritual,
  2. one non-council ritual,
  3. one escalation-resume flow,
  4. one degraded-memory path that still completes execution.

---

## 14. Acceptance Criteria

1. Users can define org agents mapped to existing delegatee profiles and capabilities.
2. Users can define recurring rituals that execute through existing scheduler and delegation runtime.
3. Council workflows produce structured multi-perspective outputs plus synthesis and conflict logs.
4. Summary/plan artifacts feeding org workflows are gated by adversarial reviewer outcomes.
5. Messenger and MCP can both trigger and observe ritual runs with policy-valid structured payloads.
6. Escalation approvals resume pending workflows without rerunning completed successful phases.
7. Cost telemetry is available by agent and ritual run with warn/approve/stop thresholds.
8. Scope, transport, and error behavior for org MCP endpoints remain consistent with `agent-mcp-v5` contracts.
9. Memory integration degrades gracefully by capability mode and never blocks run completion.
10. Disabling org-layer feature flags leaves existing behavior unchanged.
11. All org-layer mutations are auditable with actor, context, correlation id, and resource identifiers.
12. Automated test coverage includes unit, feature, and end-to-end scenarios for all core org flows.

---

## 15. Suggested Delivery Phases

1. **Phase A — Org Foundation**
  1. Org profiles, reporting edges, feature flags, CRUD APIs, base events.
2. **Phase B — Ritual Runtime Integration**
  1. Ritual templates, scheduler trigger, DelegationGraph mapping, lifecycle/status APIs.
3. **Phase C — Council and Quality Gates**
  1. Council templates, synthesis flow, adversarial reviewer integration.
4. **Phase D — Governance and Surfaces**
  1. Cost ledgers, thresholds, escalation UX, messenger/MCP parity for controls.
5. **Phase E — Hardening and Compatibility**
  1. CI compatibility gates, rollout controls, resilience testing, observability completion.

---

## 16. Open Questions

1. Should org agent profiles remain user-scoped in v1 or support workspace-shared profiles immediately? Current baseline remains user-scoped in v1; this question is about first expansion phase after baseline parity.
2. Should council synthesis default to deterministic rules-first or model-mediated with deterministic fallback?
3. Should hard-stop budget controls terminate in-flight branches or only block undispatched branches?
4. What default messenger notification granularity avoids alert fatigue while preserving operational awareness?
5. Should ritual templates support import/export JSON in v1 for portability across installations?
6. Should org-level session-to-session orchestration be exposed as explicit user tools in v1 or remain internal to delegation flows?

