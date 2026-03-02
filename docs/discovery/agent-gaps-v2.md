# Requirements Discovery Summary

Session: 13

## Implementation Plan: Resolve All Production Stub/Placeholder Code

This plan addresses 11 implementation gaps identified in a Laravel/Vue.js agent management system. All gaps represent placeholder or stub code that must be replaced with production-ready implementations.

### 1. Messenger Handler Consolidation

**Current State:** Two parallel handler implementations exist:
- `App\Services\Messenger\ActionHandlers\*` — stub handlers with fake data (active path)
- `App\Messenger\ChatAction\Handlers\*` — real DB-backed handlers with unit tests (unused)

**Target State:** Consolidate to `App\Messenger\ChatAction\Handlers` namespace using `ChatActionContext`/`ChatActionResult` pattern.

**Changes Required:**
- Update `ChatActionExecutor` to use `ChatActionHandlerInterface` from `App\Messenger\ChatAction\Handlers`
- Replace handler class map to reference `App\Messenger\ChatAction\Handlers\*` classes
- Adapt executor to construct `ChatActionContext` instead of passing raw parameters array
- Delete stub handlers from `App\Services\Messenger\ActionHandlers\*`
- Retain `ActionHandlerInterface` only if used elsewhere, otherwise delete

**Handlers to wire:**
- `JobsListHandler`, `JobsCreateHandler`, `JobsUpdateHandler`, `JobsDeleteHandler`
- `RunsListActiveHandler`, `RunsStopHandler`, `RunsRetryHandler`, `RunsRunNowHandler`, `RunsSteerHandler`

### 2. Messenger Policy Enforcement

**File:** `ChatActionPolicyValidator.php` (lines 106, 144, 189)

**Current State:** Placeholder checks that permit all actions.

**Target State:** Ownership-based validation only.

**Implementation:**
- For job actions: verify `$user->id === $job->user_id`
- For run actions: verify `$user->id === $run->agentJob->user_id`
- Return `PolicyResult::denied('You do not own this resource')` on mismatch
- Remove placeholder comments and TODO markers

### 3. Compliance Metrics Endpoint

**File:** `ComplianceController.php` (line 26)

**Current State:** Returns zeros/nulls.

**Target State:** Return basic usage metrics per tenant.

**Metrics to implement:**
- `total_jobs` — count of `AgentJob` per tenant
- `total_runs` — count of `AgentJobRun` per tenant
- `success_rate` — percentage of runs with `STATUS_SUCCEEDED`
- `failure_rate` — percentage of runs with `STATUS_FAILED`
- `active_runs` — count of currently running jobs

**Query approach:** Aggregate from `AgentJob` and `AgentJobRun` tables filtered by tenant.

### 4. Compliance Tenant Override Resolution

**File:** `ComplianceFlagResolver.php` (line 126)

**Current State:** TODO with fallback behavior.

**Target State:** Remove tenant override capability entirely.

**Implementation:**
- Remove `getTenantOverride()` method or have it return `null`
- Ensure all compliance flags use system defaults only
- Document that tenant-specific compliance overrides are not supported

### 5. Delegation Reconciler Auto-Retry

**File:** `DelegationReconciler.php` (lines 90, 125)

**Current State:** No-op blocks for blocked tasks and stuck graphs.

**Target State:** Auto-retry with exponential backoff.

**Retry Policy:**
- Maximum 3 retries
- Backoff intervals: 1 minute, 5 minutes, 15 minutes
- After 3 failures: mark as permanently failed

**Implementation:**
- Track `retry_count` and `last_retry_at` in task metadata or dedicated columns
- On blocked task detection: increment retry count, schedule retry job with appropriate delay
- On stuck graph detection: same retry logic applied to graph root
- After max retries: update status to `FAILED`, set `failure_reason`

### 6. NL Schedule Parser Clarification Flow

**File:** `NlScheduleParserService.php` (lines 107, 122)

**Current State:** Low-confidence path queues but doesn't dispatch.

**Target State:** Ask user for clarification before proceeding.

**Implementation:**
- When confidence below threshold, return a `ClarificationRequired` result type
- Include parsed interpretation and suggested alternatives
- Do not create/update schedule until user confirms
- Wire clarification response back through messenger to resume schedule creation

### 7. Trust Score Calculator

**File:** `TrustScoreCalculator.php` (line 116)

**Current State:** Hardcoded placeholder metric values.

**Target State:** Composite score from real data.

**Scoring Dimensions:**
- **Success Rate (40%)**: `(successful_runs / total_runs)` for user's jobs
- **Account Age (30%)**: Normalized score based on `user.created_at`
- **Behavioral Signals (30%)**: Inverse of error rate, policy violations, anomaly flags

**Implementation:**
- Query `AgentJobRun` for success/failure counts
- Calculate account age in days, normalize (e.g., cap at 365 days = 1.0)
- Track behavioral events in separate table or metadata; compute signal score
- Combine with weights: `0.4 * success + 0.3 * age + 0.3 * behavior`

### 8. AI Critic Output Retrieval

**File:** `AiCriticCompletedJob.php` (line 127 — `getRunOutput` method)

**Current State:** Falls back to `metadata_json['output']` only.

**Target State:** Multi-source fallback chain.

**Fallback Order:**
1. Canonical stdout storage (file path derived from `AgentJobRun` id/paths)
2. `metadata_json['output']`
3. Run artifacts/attachments

**Implementation:**
- Resolve stdout file path (e.g., `storage/runs/{run_id}/stdout.log`)
- If file exists and non-empty, use it
- Else check `$run->metadata_json['output']`
- Else iterate `$run->artifacts` and concatenate relevant text content
- Return combined output or empty string if all sources empty

### 9. Inbound Attachment Processing

**File:** `ProcessInboundMessage.php` (line 265)

**Current State:** Preserves provider file IDs only.

**Target State:** Download and store attachments immediately.

**Implementation:**
- For each attachment in inbound message:
  - Fetch file content from provider (Slack, etc.) using stored file ID
  - Validate file type and size against allowed limits
  - Store in configured disk (local or S3/cloud)
  - Create `MessageAttachment` record with local path, MIME type, size
- Handle download failures: log error, mark attachment as `failed_to_download`
- Process synchronously within job (attachments are part of message context)

### 10. Slack Socket Worker Graceful Drain

**File:** `SlackSocketWorker.php` (line 161)

**Current State:** Sets flags only, no await.

**Target State:** Graceful drain with timeout.

**Implementation:**
- On shutdown signal:
  - Set `$draining = true` flag
  - Stop accepting new WebSocket messages
  - Track in-flight operation count (`$pendingOperations`)
  - Wait loop: check every 100ms if `$pendingOperations === 0`
  - Timeout after configurable period (default 30 seconds)
  - If timeout exceeded, log warning and exit anyway
- Ensure each operation increments/decrements `$pendingOperations` counter

### 11. Remove All Remaining Stubs

After implementing the above, audit and remove:
- All `// TODO` and `// PLACEHOLDER` comments in affected files
- Any remaining fake data returns
- Unused interfaces and classes from `App\Services\Messenger\ActionHandlers\*`

## Goals

- Consolidate messenger handlers into App\Messenger\ChatAction\Handlers namespace using ChatActionContext/ChatActionResult pattern
- Update ChatActionExecutor to use the consolidated handler interface and construct ChatActionContext
- Implement ownership-based policy enforcement in ChatActionPolicyValidator (user can only act on own jobs/runs)
- Implement compliance metrics endpoint returning job counts, run counts, and success rates per tenant
- Remove tenant override capability from ComplianceFlagResolver (all tenants follow same rules)
- Implement auto-retry with backoff in DelegationReconciler (3 retries at 1min/5min/15min intervals)
- Implement clarification flow in NlScheduleParserService for low-confidence schedule parsing
- Implement composite trust scoring using success rate (40%), account age (30%), and behavioral signals (30%)
- Implement multi-source output retrieval in AiCriticCompletedJob (stdout → metadata → artifacts fallback)
- Implement immediate attachment download and storage in ProcessInboundMessage
- Implement graceful drain with timeout in SlackSocketWorker
- Delete all stub handlers from App\Services\Messenger\ActionHandlers namespace
- Remove all placeholder/TODO code from production paths


## Constraints

- Zero placeholder or stub code in production system
- No regressions - existing unit tests in App\Messenger\ChatAction\Handlers must continue to pass
- Ownership-based authorization only (no team-based, role-based, or attribute-based access control)
- No tenant compliance overrides - all tenants follow identical compliance rules
- Fast failure detection for delegation (max 3 retries over ~21 minutes total)
- Synchronous attachment download during inbound message processing
- Graceful socket drain must have configurable timeout (default 30 seconds)


## Acceptance Criteria

- ChatActionExecutor resolves handlers from App\Messenger\ChatAction\Handlers namespace
- ChatActionExecutor constructs ChatActionContext with user, parameters, action, targetJob, targetRun, and confirmed flag
- All 9 action handlers (JobsList, JobsCreate, JobsUpdate, JobsDelete, RunsListActive, RunsStop, RunsRetry, RunsRunNow, RunsSteer) execute real database operations
- ChatActionPolicyValidator denies action when user_id does not match resource owner
- ChatActionPolicyValidator permits action when user_id matches resource owner
- ComplianceController.metrics() returns total_jobs, total_runs, success_rate, failure_rate, active_runs per tenant
- ComplianceFlagResolver.getTenantOverride() returns null or is removed
- DelegationReconciler retries blocked tasks up to 3 times with 1/5/15 minute delays
- DelegationReconciler marks tasks as permanently failed after 3 retry attempts
- NlScheduleParserService returns ClarificationRequired response when parse confidence is low
- TrustScoreCalculator computes score from AgentJobRun success rate, User.created_at age, and behavioral event counts
- AiCriticCompletedJob.getRunOutput() reads from stdout file storage as primary source
- AiCriticCompletedJob.getRunOutput() falls back to metadata_json then artifacts when stdout empty
- ProcessInboundMessage downloads attachment files from provider and stores locally
- ProcessInboundMessage creates MessageAttachment records with local path, MIME type, and size
- SlackSocketWorker tracks pending operation count during drain
- SlackSocketWorker waits for pending operations to complete before shutdown (up to timeout)
- All files in App\Services\Messenger\ActionHandlers\ are deleted
- No TODO, PLACEHOLDER, or stub comments remain in the 11 affected files

