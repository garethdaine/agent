# Requirements Discovery Summary

Session: 4

## Adversarial Reviewer for Requirements Discovery Quality Gate

### Overview

Add an automated adversarial reviewer pass that validates generated summary and plan artifacts against discovery context before acceptance. The reviewer detects missing scope, contradictions, weak coverage, and unresolved requirements drift, then either approves, requests revision, or (for summary only) requests clarification questions.

### Architecture

**Core Service:** `AdversarialReviewerService` — standalone service class injected into `SummaryPhaseHandler` and `PlanPhaseHandler`. Invoked via Claude CLI subprocess following the existing `ClaudeAdapter` pattern with `--json-schema` for structured outputs.

**Context Package:** Each review invocation receives:
- Session metadata snapshot
- Feature/session brief
- Discovery findings (latest relevant events)
- Full reconstructed Q&A transcript
- Current candidate artifact (summary for summary review; plan + locked summary for plan review)

### Reviewer Contract Schema

```json
{
  "verdict": "pass | revise | needs_clarification",
  "issues": [
    {
      "type": "missing_requirement | contradiction | ambiguity | weak_acceptance_criteria | ...",
      "severity": "low | medium | high | critical",
      "message": "string",
      "evidence": "string (quote/snippet from context)"
    }
  ],
  "required_changes": ["string"],
  "clarification_questions": ["string"],
  "confidence": 0.0-1.0,
  "review_notes": "string (optional)"
}
```

### Verdict Handling

**Pass Verdict:**
- If any issue has `severity: critical`, auto-escalate to `revise` verdict
- If `confidence < 0.6`, log as soft warning in `metadata_json` but do not block
- Otherwise, persist artifact and emit ready event

**Revise Verdict:**
- Pass full reviewer payload (issues, required_changes, evidence) to regeneration context
- Regenerate artifact with explicit correction instructions
- Re-run review (bounded by retry limits)

**Needs Clarification Verdict (Summary Only):**
- Maximum 3 clarification questions per request
- Insert questions at front of existing summary open-question queue (high priority)
- Transition to interrogation phase
- After answers consumed, regenerate summary and re-run review

### Configuration

Add to `config/agent.php` under `interrogation` key:

```php
'adversarial_review_enabled' => env('AGENT_ADVERSARIAL_REVIEW_ENABLED', false),
'summary_review_max_retries' => env('AGENT_SUMMARY_REVIEW_MAX_RETRIES', 3),
'plan_review_max_retries' => env('AGENT_PLAN_REVIEW_MAX_RETRIES', 2),
'review_warn_only' => env('AGENT_REVIEW_WARN_ONLY', false),
'review_severity_threshold' => env('AGENT_REVIEW_SEVERITY_THRESHOLD', 'high'),
'review_low_confidence_threshold' => 0.6,
'review_max_clarification_questions' => 3,
'reviewer_model_override' => env('AGENT_REVIEWER_MODEL_OVERRIDE', null),
```

### Severity Threshold Behavior

- `review_severity_threshold` defaults to `high`
- Only issues at or above threshold block artifact (high, critical)
- Low/medium issues logged but do not force revision
- Critical issues always auto-escalate `pass` to `revise`

### Shadow Mode (`review_warn_only: true`)

- Reviewer executes but does not gate artifacts
- All findings stored in `metadata_json` for analysis
- Artifacts proceed as if review passed
- Used for safe rollout validation

### State and Metadata Tracking

Store in `metadata_json`:

```json
{
  "summary": {
    "review_status": "pending | passed | failed | clarification_needed",
    "review_attempts": 0,
    "last_review": { /* reviewer payload */ },
    "review_history": [ /* all reviewer payloads */ ],
    "low_confidence_warning": true | false
  },
  "plan": {
    "review_status": "pending | passed | failed",
    "review_attempts": 0,
    "last_review": { /* reviewer payload */ },
    "review_history": [ /* all reviewer payloads */ ]
  }
}
```

### System Events

Emit for observability:
- `summary_review_started`
- `summary_review_passed`
- `summary_review_failed`
- `summary_review_clarification_needed`
- `plan_review_started`
- `plan_review_passed`
- `plan_review_failed`

Event payloads include verdict, attempt number, issue count, and timestamp.

### Failure Handling

- Hard retry caps enforced (3 for summary, 2 for plan)
- On exhausted retries: transition to failed state with reviewer failure code
- Store full retry history (all reviewer payloads from each attempt) in `metadata_json`
- Clarification loop failures surface actionable error messages

### Payload Guards

- Invalid reviewer JSON fails fast with existing error handling
- Clarification questions validated against existing question payload guard rules
- Reviewer content does not bypass schema/normalizer/guard checks

### Delivery Phases

1. **Phase A:** Reviewer schema, guards, shadow-mode integration
2. **Phase B:** Summary gating + clarification integration
3. **Phase C:** Plan gating integration
4. **Phase D:** Hard-enable via config in controlled rollout

## Goals

- Prevent low-quality or contradictory summary/plan outputs from advancing through the discovery pipeline
- Ensure summary and plan artifacts are consistent with feature brief, Q&A transcript, discovery findings, and session metadata
- Automate correction loops with bounded retries (3 for summary, 2 for plan)
- Reuse existing summary open-question queue for clarification questions (no parallel questioning subsystem)
- Provide shadow mode for safe rollout and validation of reviewer accuracy
- Store full reviewer history for debugging convergence issues and failed sessions


## Constraints

- Reviewer invoked via Claude CLI subprocess consistent with existing ClaudeAdapter pattern
- No infinite loops: hard retry caps of 3 (summary) and 2 (plan) enforced
- needs_clarification verdict only allowed during summary phase, not planning phase
- Maximum 3 clarification questions per reviewer request
- Clarification questions inserted at front of queue (high priority)
- Critical-severity issues auto-escalate pass verdict to revise
- Low-confidence passes (below 0.6) logged as soft warning but do not block
- Shadow mode stores findings in metadata_json only, does not gate artifacts
- Feature flag disabled by default; existing flow unchanged when disabled
- Reviewer prompts must follow non-interactive orchestration rules
- All lifecycle mutations auditable via existing event/audit patterns


## Acceptance Criteria

- AdversarialReviewerService class exists as standalone injectable service
- Reviewer returns strict JSON matching defined schema with verdict, issues, required_changes, clarification_questions, confidence, review_notes
- Summary review blocks artifacts with high/critical severity issues (configurable threshold)
- Plan review blocks artifacts with high/critical severity issues (configurable threshold)
- Pass verdict with critical-severity issue auto-escalates to revise verdict
- Pass verdict with confidence below 0.6 logs soft warning in metadata_json without blocking
- Revise verdict triggers regeneration with full reviewer payload in context
- Summary needs_clarification verdict inserts max 3 questions at front of open-question queue
- Retry limits enforced: summary fails after 3 attempts, plan fails after 2 attempts
- Failed reviews store full retry history (all payloads) in metadata_json
- Shadow mode (review_warn_only: true) stores findings but does not gate artifacts
- Feature flag off: behavior identical to current production flow
- System events emitted for all review lifecycle transitions with verdict, attempt, timestamp
- Invalid reviewer payload fails fast without persisting partial state
- Config keys added: adversarial_review_enabled, summary_review_max_retries, plan_review_max_retries, review_warn_only, review_severity_threshold
- Unit tests cover payload guard/normalizer for reviewer schema
- Feature tests cover summary pass, revise loop, clarification path, plan revise loop, retry cap enforcement, invalid payload handling, flag-off behavior

