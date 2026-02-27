# Implementation Plan

Derived from discovery session 4.

# Adversarial Reviewer Implementation Plan

## 1. Scope Boundary

This plan covers implementing an adversarial reviewer pass that validates summary and plan artifacts against discovery context before acceptance. The reviewer integrates into existing `ExecuteInterrogationSummaryJob` and `ExecuteInterrogationPlanJob` workflows, reuses the existing `summary_open_question_queue` for clarification questions, and follows the established `ClaudeAdapter` subprocess pattern.

**In scope:**
- AdversarialReviewerService class with CLI subprocess invocation
- ReviewerPayloadGuard and ReviewerPayloadNormalizer for schema validation
- Summary review flow with pass/revise/needs_clarification verdicts
- Plan review flow with pass/revise verdicts only
- Shadow mode (warn-only) for rollout validation
- System event emission for review lifecycle
- Metadata tracking for review status, attempts, and history
- Configuration keys under `config/agent.php` interrogation block

**Out of scope:**
- Replacing existing interrogation logic
- Human-in-the-loop mandatory approval
- New external orchestration services
- Build-task generation changes

---

## 2. Architecture Changes

### 2.1 New Service Class

Create `app/Support/Interrogation/AdversarialReviewerService.php` as a standalone injectable service. The service:
- Accepts session, artifact type (summary/plan), candidate payload, and context package
- Builds and executes Claude CLI subprocess using `--json-schema` for structured reviewer output
- Parses response via `parseReviewerResponse()` method
- Returns validated reviewer payload or throws on invalid response

### 2.2 New Guard and Normalizer

Create `app/Support/Interrogation/ReviewerPayloadGuard.php`:
- Validates verdict is enum `pass | revise | needs_clarification`
- Validates issues array structure (type, severity, message, evidence)
- Validates required_changes is array of strings
- Validates clarification_questions (max 3, only for summary)
- Validates confidence is numeric 0..1
- Fails fast on invalid payload

Create `app/Support/Interrogation/ReviewerPayloadNormalizer.php`:
- Normalizes severity to lowercase enum
- Trims and filters empty strings from arrays
- Ensures clarification_questions capped at 3
- Ensures confidence clamped to 0..1

### 2.3 ClaudeAdapter Extension

Add `buildReviewerCommand()` method to `app/Support/Interrogation/Adapters/ClaudeAdapter.php`:
- Uses `--json-schema` with reviewer contract schema
- Uses `--tools=Read,Glob,Grep` for context verification
- Does not use `--resume` (fresh session per review)

Add `parseReviewerResponse()` method following existing `parseSummaryResponse()` / `parsePlanResponse()` patterns.

Add `reviewerSchema()` private method returning JSON schema for reviewer contract.

---

## 3. Data Model / Metadata

### 3.1 Metadata JSON Structure

Extend `metadata_json` in `interrogation_sessions` table with nested reviewer state:

```json
{
  "summary": {
    "review_status": "pending | passed | failed | clarification_needed",
    "review_attempts": 0,
    "last_review": { /* reviewer payload */ },
    "review_history": [ /* all reviewer payloads */ ],
    "low_confidence_warning": false
  },
  "plan": {
    "review_status": "pending | passed | failed",
    "review_attempts": 0,
    "last_review": { /* reviewer payload */ },
    "review_history": [ /* all reviewer payloads */ ]
  }
}
```

No database migration required; `metadata_json` column exists.

### 3.2 Reviewer Payload Schema

```json
{
  "verdict": "pass | revise | needs_clarification",
  "issues": [
    {
      "type": "missing_requirement | contradiction | ambiguity | weak_acceptance_criteria | scope_drift | unresolved_dependency",
      "severity": "low | medium | high | critical",
      "message": "string",
      "evidence": "string"
    }
  ],
  "required_changes": ["string"],
  "clarification_questions": ["string"],
  "confidence": 0.0,
  "review_notes": "string"
}
```

---

## 4. API / Tool Contracts

### 4.1 AdversarialReviewerService Contract

```php
class AdversarialReviewerService
{
    public function reviewSummary(
        InterrogationSession $session,
        array $summaryCandidate,
        array $contextPackage
    ): array; // returns validated reviewer payload

    public function reviewPlan(
        InterrogationSession $session,
        array $planCandidate,
        array $lockedSummary,
        array $contextPackage
    ): array; // returns validated reviewer payload
}
```

### 4.2 Context Package Builder

Create `app/Support/Interrogation/ReviewerContextBuilder.php`:
- `buildForSummaryReview(InterrogationSession $session, array $summaryCandidate): array`
- `buildForPlanReview(InterrogationSession $session, array $planCandidate, array $lockedSummary): array`

Context package includes:
- Session metadata snapshot
- Feature/session brief
- Discovery findings (relevant system events)
- Full Q&A transcript via `ConversationReconstructor`
- Candidate artifact

---

## 5. Event Contracts

### 5.1 New System Event Types

Add constants to `app/Models/InterrogationEvent.php`:
- `TYPE_SUMMARY_REVIEW = 'summary_review'`
- `TYPE_PLAN_REVIEW = 'plan_review'`

### 5.2 Event Writer Methods

Add to `app/Support/Interrogation/InterrogationEventWriter.php`:
- `appendSummaryReview(array $payload): InterrogationEvent`
- `appendPlanReview(array $payload): InterrogationEvent`

Event payloads include:
- `status`: started | passed | failed | clarification_needed
- `verdict`: pass | revise | needs_clarification
- `attempt`: int
- `issue_count`: int
- `confidence`: float
- `at`: ISO-8601 timestamp

---

## 6. Job Integration

### 6.1 ExecuteInterrogationSummaryJob Changes

Modify `app/Jobs/ExecuteInterrogationSummaryJob.php`:

1. After generating summary candidate, check `adversarial_review_enabled` config
2. If enabled:
   - Call `AdversarialReviewerService::reviewSummary()`
   - Emit `summary_review_started` event
   - Handle verdict:
     - `pass`: check for critical issues (auto-escalate to revise), check confidence (log warning if below threshold), persist summary, emit `summary_review_passed`
     - `revise`: increment attempt counter, check against `summary_review_max_retries`, regenerate with required_changes in prompt, re-run review loop
     - `needs_clarification`: validate max 3 questions, insert at front of `summary_open_question_queue`, set `review_status: clarification_needed`, emit `summary_review_clarification_needed`, return (queue handler resumes flow)
3. On retry exhaustion: transition to failed state with `SUMMARY_REVIEW_EXHAUSTED` error code, store full review_history, emit `summary_review_failed`
4. If `review_warn_only: true`: log findings in metadata, do not gate artifact

### 6.2 ExecuteInterrogationPlanJob Changes

Modify `app/Jobs/ExecuteInterrogationPlanJob.php`:

1. After generating plan candidate and passing existing validation, check `adversarial_review_enabled` config
2. If enabled:
   - Call `AdversarialReviewerService::reviewPlan()`
   - Emit `plan_review_started` event
   - Handle verdict:
     - `pass`: check for critical issues, persist plan, emit `plan_review_passed`
     - `revise`: increment attempt counter, check against `plan_review_max_retries`, regenerate with required_changes, re-run review loop
     - `needs_clarification`: reject (not allowed in planning phase), treat as `revise`
3. On retry exhaustion: transition to failed, store history, emit `plan_review_failed`
4. Shadow mode behaves same as summary

### 6.3 Clarification Queue Re-entry

When `summary_open_question_queue.active` becomes false (all clarification questions answered), `InterrogationSessionController::submit()` already dispatches `ExecuteInterrogationSummaryJob`. The job will re-run summary generation with new Q&A context, then re-run review.

---

## 7. Configuration

### 7.1 Config Keys

Add to `config/agent.php` under `interrogation` key:

```php
'adversarial_review_enabled' => env('AGENT_ADVERSARIAL_REVIEW_ENABLED', false),
'summary_review_max_retries' => (int) env('AGENT_SUMMARY_REVIEW_MAX_RETRIES', 3),
'plan_review_max_retries' => (int) env('AGENT_PLAN_REVIEW_MAX_RETRIES', 2),
'review_warn_only' => env('AGENT_REVIEW_WARN_ONLY', false),
'review_severity_threshold' => env('AGENT_REVIEW_SEVERITY_THRESHOLD', 'high'),
'review_low_confidence_threshold' => 0.6,
'review_max_clarification_questions' => 3,
'reviewer_model_override' => env('AGENT_REVIEWER_MODEL_OVERRIDE', null),
```

### 7.2 Severity Threshold Logic

Severity order: low < medium < high < critical

Only issues at or above configured threshold (`high` by default) trigger blocking behavior. Lower severity issues are logged but do not force revision.

Critical issues always auto-escalate a `pass` verdict to `revise`.

---

## 8. Authorization / Scope Enforcement

No new authorization rules required. The reviewer operates within existing session ownership scope enforced by `InterrogationSessionPolicy`. Reviewer subprocess inherits session's `project_directory` and environment via existing `ClaudeAdapter::buildEnvironment()`.

---

## 9. Failure / Retry Behavior

### 9.1 Retry Loop Mechanics

```
attempt = 0
while attempt < max_retries:
    generate candidate
    run review
    if verdict == pass and no critical issues:
        persist and exit
    if verdict == needs_clarification (summary only):
        enqueue questions, exit (resumes after answers)
    attempt++
    inject required_changes into next generation prompt

if attempt >= max_retries:
    fail with REVIEW_EXHAUSTED code
    store full review_history
```

### 9.2 Failure Codes

- `SUMMARY_REVIEW_EXHAUSTED`: summary review retry limit reached
- `SUMMARY_REVIEW_INVALID_PAYLOAD`: reviewer returned unparseable JSON
- `PLAN_REVIEW_EXHAUSTED`: plan review retry limit reached
- `PLAN_REVIEW_INVALID_PAYLOAD`: reviewer returned unparseable JSON

### 9.3 Clarification Failure

If clarification questions fail validation (e.g., exceed max count, fail `QuestionPayloadGuard`), treat as invalid payload and increment retry counter. Do not insert invalid questions into queue.

---

## 10. Observability

### 10.1 System Events

All review lifecycle transitions emit `InterrogationEvent` records:
- `summary_review_started`
- `summary_review_passed`
- `summary_review_failed`
- `summary_review_clarification_needed`
- `plan_review_started`
- `plan_review_passed`
- `plan_review_failed`

Events include verdict, attempt, issue_count, confidence, timestamp.

### 10.2 Metadata Persistence

Full reviewer payloads stored in `metadata_json.summary.review_history` and `metadata_json.plan.review_history` arrays for debugging convergence issues.

### 10.3 Broadcast

Existing `InterrogationSessionUpdated` event broadcasts review events to frontend via WebSocket.

---

## 11. Test Strategy

### 11.1 Unit Tests

Create `tests/Unit/ReviewerPayloadGuardTest.php`:
- Valid payload passes
- Missing verdict fails
- Invalid verdict enum fails
- Invalid severity enum fails
- clarification_questions exceeds max fails (summary context)
- clarification_questions present in plan context fails
- confidence out of range normalized

Create `tests/Unit/ReviewerPayloadNormalizerTest.php`:
- Severity normalized to lowercase
- Empty strings filtered from arrays
- clarification_questions capped at 3
- confidence clamped to 0..1

Create `tests/Unit/AdversarialReviewerServiceTest.php`:
- Mock subprocess returns valid JSON
- Mock subprocess returns invalid JSON throws
- Context package assembled correctly

### 11.2 Feature Tests

Create `tests/Feature/AdversarialReviewerSummaryTest.php`:
- Pass verdict persists summary
- Revise verdict regenerates and re-reviews
- Revise loop converges within retry limit
- needs_clarification populates queue
- Clarification answers consumed, summary regenerated, review re-run
- Retry cap enforced, session fails gracefully
- Critical issue auto-escalates pass to revise
- Low confidence logs warning but does not block
- Shadow mode logs but does not gate

Create `tests/Feature/AdversarialReviewerPlanTest.php`:
- Pass verdict persists plan
- Revise verdict regenerates and re-reviews
- Revise loop converges
- Retry cap enforced
- needs_clarification treated as revise
- Shadow mode logs but does not gate

Create `tests/Feature/AdversarialReviewerDisabledTest.php`:
- Feature flag off skips all review logic
- Behavior identical to current production flow

### 11.3 Integration Tests

Extend `tests/Feature/InterrogationApiWorkflowTest.php`:
- End-to-end flow with reviewer enabled
- Clarification path through API endpoints

---

## 12. Backward Compatibility

### 12.1 Feature Flag Default

`adversarial_review_enabled` defaults to `false`. Existing flows remain unchanged until explicitly enabled.

### 12.2 Metadata Schema

New `summary.review_*` and `plan.review_*` keys are additive. Existing sessions without these keys function normally; code checks for key existence before access.

### 12.3 Job Signatures

No changes to job constructor signatures or dispatch patterns. Reviewer logic is internal to job execution.

---

## 13. Rollout and Rollback Controls

### 13.1 Rollout Phases

**Phase A:** Deploy with `adversarial_review_enabled: false` and `review_warn_only: true`. Reviewer executes in shadow mode, findings logged but not gating.

**Phase B:** Enable gating for summary review only by setting `adversarial_review_enabled: true` for subset of users or sessions.

**Phase C:** Enable gating for plan review.

**Phase D:** Remove `review_warn_only` override, full production gating.

### 13.2 Rollback

Set `adversarial_review_enabled: false` via environment variable. No code deployment required for immediate rollback.

---

## 14. File Impact Summary

### 14.1 New Files

- `app/Support/Interrogation/AdversarialReviewerService.php`
- `app/Support/Interrogation/ReviewerPayloadGuard.php`
- `app/Support/Interrogation/ReviewerPayloadNormalizer.php`
- `app/Support/Interrogation/ReviewerContextBuilder.php`
- `tests/Unit/ReviewerPayloadGuardTest.php`
- `tests/Unit/ReviewerPayloadNormalizerTest.php`
- `tests/Unit/AdversarialReviewerServiceTest.php`
- `tests/Feature/AdversarialReviewerSummaryTest.php`
- `tests/Feature/AdversarialReviewerPlanTest.php`
- `tests/Feature/AdversarialReviewerDisabledTest.php`

### 14.2 Modified Files

- `config/agent.php` — add reviewer config keys
- `app/Support/Interrogation/Adapters/ClaudeAdapter.php` — add reviewer command/schema/parse methods
- `app/Support/Interrogation/Contracts/InterrogationRunnerAdapter.php` — add reviewer method signatures
- `app/Support/Interrogation/Adapters/CodexAdapter.php` — add reviewer method stubs (codex support optional)
- `app/Models/InterrogationEvent.php` — add TYPE_SUMMARY_REVIEW, TYPE_PLAN_REVIEW constants
- `app/Support/Interrogation/InterrogationEventWriter.php` — add appendSummaryReview, appendPlanReview methods
- `app/Jobs/ExecuteInterrogationSummaryJob.php` — integrate reviewer flow
- `app/Jobs/ExecuteInterrogationPlanJob.php` — integrate reviewer flow

---

## 15. Implementation Sequence

### Phase A: Foundation (no gating)

1. Create `ReviewerPayloadGuard` with validation rules
2. Create `ReviewerPayloadNormalizer` with normalization rules
3. Add reviewer config keys to `config/agent.php`
4. Add `buildReviewerCommand()`, `reviewerSchema()`, `parseReviewerResponse()` to `ClaudeAdapter`
5. Create `ReviewerContextBuilder` for context package assembly
6. Create `AdversarialReviewerService` with subprocess invocation
7. Add event type constants to `InterrogationEvent`
8. Add `appendSummaryReview`, `appendPlanReview` to `InterrogationEventWriter`
9. Add unit tests for guard, normalizer, service
10. Integrate shadow-mode-only path into `ExecuteInterrogationSummaryJob` (logs findings, does not gate)
11. Integrate shadow-mode-only path into `ExecuteInterrogationPlanJob`

### Phase B: Summary Gating

1. Implement pass/revise verdict handling in `ExecuteInterrogationSummaryJob`
2. Implement needs_clarification verdict with queue insertion
3. Implement retry loop with exhaustion handling
4. Implement critical-issue auto-escalation
5. Implement low-confidence warning
6. Add feature tests for summary review flows

### Phase C: Plan Gating

1. Implement pass/revise verdict handling in `ExecuteInterrogationPlanJob`
2. Implement retry loop with exhaustion handling
3. Reject needs_clarification as invalid for plan phase
4. Add feature tests for plan review flows

### Phase D: Rollout

1. Enable `adversarial_review_enabled` via environment
2. Monitor event logs and metadata for convergence issues
3. Disable `review_warn_only` for full gating

## Sections

- Scope Boundary
- Architecture Changes
- Data Model / Metadata
- API / Tool Contracts
- Event Contracts
- Job Integration
- Configuration
- Authorization / Scope Enforcement
- Failure / Retry Behavior
- Observability
- Test Strategy
- Backward Compatibility
- Rollout and Rollback Controls
- File Impact Summary
- Implementation Sequence


## Risks

- Reviewer subprocess may time out on complex sessions with large context packages; mitigate with configurable timeout and graceful fallback to warn-only mode
- Clarification question quality depends on reviewer prompt engineering; poor questions may confuse users or fail QuestionPayloadGuard validation repeatedly
- Retry loops may not converge if reviewer consistently produces revise verdicts with contradictory required_changes; retry cap prevents infinite loops but may cause false failures
- Shadow mode may not surface all edge cases if production traffic patterns differ from test scenarios
- CodexAdapter reviewer support is optional and may not reach parity with ClaudeAdapter initially
- Review history storage in metadata_json may grow large for sessions with many retries; consider pruning strategy for long-term sessions


## Assumptions

- ClaudeAdapter subprocess pattern with --json-schema is reliable for structured reviewer output
- Existing summary_open_question_queue mechanics support high-priority question insertion at front of queue
- ConversationReconstructor provides complete Q&A transcript suitable for reviewer context
- Existing QuestionPayloadGuard rules are sufficient to validate reviewer-generated clarification questions
- Session metadata_json column has sufficient capacity for reviewer history storage
- Feature flag environment variable changes take effect without code deployment
- Reviewer model (default or override) has sufficient capability to detect contradictions, missing scope, and weak acceptance criteria

