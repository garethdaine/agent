# Discovery Requirements Brief — Adversarial Reviewer for Summary + Plan

## 1. Overview

**Feature Name:** Adversarial Reviewer (Requirements Discovery Quality Gate)  
**Purpose:** Add an automated reviewer pass that validates generated summary and plan artifacts against discovery context (brief, Q&A, metadata) before they are accepted.

The reviewer must detect missing scope, contradictions, weak coverage, and unresolved requirements drift. It then either:

1. approves the artifact,
2. requests targeted revision, or
3. requests additional clarification questions (summary stage only).

---

## 2. Goals

1. Prevent low-quality or contradictory summary/plan outputs from advancing.
2. Ensure summary and plan are consistent with:
   1. feature brief,
   2. interrogation Q&A transcript,
   3. discovery findings,
   4. stored session metadata.
3. Automate correction loops with bounded retries.
4. Reuse existing summary open-question queue mechanics for clarification, not a parallel questioning subsystem.

---

## 3. Non-Goals

1. Replacing existing interrogation logic end-to-end.
2. Human-in-the-loop mandatory approval for every reviewer finding.
3. New external services for orchestration.
4. Reworking build-task generation in this phase.

---

## 4. High-Level Flow

### 4.1 Summary Phase

1. Generate summary candidate (current behavior).
2. Run adversarial review against candidate + full context package.
3. If verdict = `pass`:
   1. persist summary,
   2. emit `summary_ready`.
4. If verdict = `revise`:
   1. regenerate summary with reviewer-required changes,
   2. re-run review (bounded loop).
5. If verdict = `needs_clarification`:
   1. enqueue reviewer clarification questions using existing summary open-question queue,
   2. transition to interrogation,
   3. consume answers,
   4. regenerate summary,
   5. re-run review.

### 4.2 Planning Phase

1. Generate plan candidate (current behavior).
2. Run adversarial review against plan + locked summary + full context package.
3. If verdict = `pass`:
   1. persist plan,
   2. emit `plan_ready`.
4. If verdict = `revise`:
   1. regenerate plan using reviewer-required changes,
   2. re-run review (bounded loop).
5. `needs_clarification` is not allowed during planning in this phase.

---

## 5. Reviewer Contract

Reviewer output must be strict JSON, with schema-validated fields:

1. `verdict`: enum `pass | revise | needs_clarification`
2. `issues`: array of objects:
   1. `type` (missing_requirement, contradiction, ambiguity, weak_acceptance_criteria, etc.)
   2. `severity` (low, medium, high, critical)
   3. `message`
   4. `evidence` (quote/snippet references from context)
3. `required_changes`: array of concrete rewrite directives.
4. `clarification_questions`: array of user-answerable questions (only valid for summary review).
5. `confidence`: numeric 0..1.
6. `review_notes`: optional concise rationale.

Invalid reviewer payloads must fail fast and follow existing error handling patterns.

---

## 6. Context Package Requirements

Each review invocation must include:

1. Session metadata snapshot.
2. Feature/session brief.
3. Discovery findings (latest relevant events).
4. Full reconstructed Q&A transcript.
5. Current candidate artifact:
   1. summary candidate for summary review,
   2. plan candidate + locked summary for plan review.

Context must be deterministic and reproducible from stored session state/events.

---

## 7. State, Events, and Metadata

Track reviewer lifecycle in `metadata_json`:

1. `summary.review_status`, `summary.review_attempts`, `summary.last_review`.
2. `plan.review_status`, `plan.review_attempts`, `plan.last_review`.

Emit system events for observability:

1. `summary_review_started`
2. `summary_review_passed`
3. `summary_review_failed`
4. `summary_review_clarification_needed`
5. `plan_review_started`
6. `plan_review_passed`
7. `plan_review_failed`

Include concise reason payloads and timestamps.

---

## 8. Config and Controls

Add feature flags and retry controls under interrogation config:

1. `adversarial_review_enabled` (default false for rollout).
2. `summary_review_max_retries`.
3. `plan_review_max_retries`.
4. `review_warn_only` (shadow mode: log findings, do not gate).
5. Optional reviewer model override.

When disabled, current flow must remain unchanged.

---

## 9. Failure Handling

1. No infinite loops: enforce hard retry caps.
2. On exhausted retries:
   1. transition to failed state with explicit reviewer failure code,
   2. preserve last reviewer payload in metadata for debugging.
3. Clarification loop failures must surface actionable error messages.

---

## 10. Security and Quality Constraints

1. Reviewer prompts must follow current non-interactive orchestration rules.
2. Clarification questions must pass existing question payload guard rules.
3. Reviewer-generated content must not bypass existing schema/normalizer/guard checks.
4. All lifecycle mutations must remain auditable via existing event/audit patterns.

---

## 11. Testing Requirements

Add automated tests for:

1. Summary review pass-through.
2. Summary revise loop convergence.
3. Summary clarification path:
   1. reviewer emits clarification questions,
   2. queue is populated,
   3. interrogation resumes,
   4. summary regenerates and passes review.
4. Plan review revise loop convergence.
5. Retry cap enforcement for both summary and plan.
6. Invalid reviewer payload handling.
7. Feature-flag off behavior (no gating).

Include unit tests for payload guard/normalizer + feature tests for end-to-end phase transitions.

---

## 12. Acceptance Criteria

1. A contradictory or incomplete summary is not emitted as `summary_ready` without either:
   1. successful revision, or
   2. clarification Q&A + successful revision.
2. A contradictory or incomplete plan is not emitted as `plan_ready` without successful revision.
3. Reviewer loops terminate deterministically under configured retry limits.
4. Existing summary open-question queue remains the single clarification mechanism.
5. With feature flag disabled, behavior matches current production flow.

---

## 13. Suggested Delivery Phases

1. Phase A: reviewer schema + guard + shadow-mode integration.
2. Phase B: summary gating + clarification integration.
3. Phase C: plan gating integration.
4. Phase D: hard-enable via config in controlled rollout.

