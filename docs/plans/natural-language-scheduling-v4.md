# Implementation Plan

Derived from discovery session 9.

# Natural Language Scheduling Implementation Plan

## Executive Summary
This plan implements a Natural Language scheduling mode in the Agent Job Builder, enabling users to describe schedules in plain English while preserving cron as the canonical runtime format. The hybrid parser uses deterministic rule-based parsing first with LLM fallback for low-confidence results, integrating with existing InterrogationRunnerAdapter infrastructure.

---

## Phase 1: Database Schema and Configuration

### 1.1 Add active_hours_config Column to agent_jobs
**File**: `database/migrations/2026_02_28_100000_add_active_hours_config_to_agent_jobs.php`

Create migration adding nullable JSON column `active_hours_config` to `agent_jobs` table. Schema: `{"start": "HH:MM", "end": "HH:MM", "days": [1,2,3,4,5]}` using ISO-8601 day indexing (1=Mon..7=Sun).

**Dependencies**: None
**Acceptance**:
- Migration runs without error
- Column accepts null and valid JSON
- Existing jobs have null value

### 1.2 Create nl_parse_attempts Table
**File**: `database/migrations/2026_02_28_100001_create_nl_parse_attempts_table.php`

Create telemetry table with columns: id (uuid PK), user_id (uuid FK), input_text (varchar 200), timezone (varchar 64), parser_path (enum: rule_based, llm_fallback), confidence (decimal 3,2), cron_result (varchar 100), active_hours_result (json nullable), status (enum: queued, running, completed, failed), user_confirmed (boolean), error_message (text nullable), created_at, completed_at (nullable).

Add indexes on user_id, status, created_at for cleanup queries and idempotency lookups. Add composite index on (user_id, input_text, timezone, created_at) for idempotency window queries.

**Dependencies**: None
**Acceptance**:
- Migration runs successfully
- All enum constraints enforced
- Indexes created for query performance

### 1.3 Add NL Parse Configuration
**File**: `config/agent.php`

Add `nl_parse` configuration block under existing agent config with keys matching env var naming convention `NL_PARSE_*`:
- `confidence_threshold` (env: `NL_PARSE_CONFIDENCE_THRESHOLD`, default: 0.75)
- `llm_timeout_seconds` (env: `NL_PARSE_TIMEOUT_SECONDS`, default: 30)
- `idempotency_window_seconds` (env: `NL_PARSE_IDEMPOTENCY_SECONDS`, default: 60)
- `rate_limit_per_minute` (env: `NL_PARSE_RATE_LIMIT_PER_MINUTE`, default: 10)
- `rate_limit_per_hour` (env: `NL_PARSE_RATE_LIMIT_PER_HOUR`, default: 60)
- `max_input_length` (env: `NL_PARSE_MAX_INPUT_LENGTH`, default: 200)
- `min_interval_minutes` (env: `NL_PARSE_MIN_INTERVAL_MINUTES`, default: 1)
- `retention_days` (env: `NL_PARSE_RETENTION_DAYS`, default: 90)

**Dependencies**: None
**Acceptance**:
- All config values accessible via `config('agent.nl_parse.*')`
- Environment variable overrides work with `NL_PARSE_*` prefix

---

## Phase 2: Core Parser Infrastructure

### 2.1 Create Parser Output DTO
**File**: `app/Support/NlSchedule/ParseResult.php`

Create value object implementing Parser Output Contract with properties: cron_expression (string), timezone (string), explanation (string), active_hours (nullable array with start/end/days), next_runs (array of {local, utc} pairs), confidence (float 0.0-1.0), parser_path (string: rule_based|llm_fallback), ambiguous (bool). Include factory methods and JSON serialization.

**Dependencies**: None
**Acceptance**:
- DTO validates all fields on construction
- Serializes to expected JSON contract format

### 2.2 Implement Rule-Based Schedule Parser
**File**: `app/Support/NlSchedule/RuleBasedScheduleParser.php`

Implement deterministic parser for v1 patterns as specified in brief:
- `every X minutes` / `every X hours`
- `daily at TIME`
- `weekdays at TIME` / `weekends at TIME`
- `every Monday-Sunday at TIME`
- `every day between TIME and TIME`
- `hourly`
- `twice a day` (ambiguous, suggests 9am/5pm, requires confirmation)

Return ParseResult with confidence score based on pattern match quality. Use ISO-8601 day indexing (1=Mon..7=Sun) throughout. Set ambiguous flag for `twice a day` and bare time without AM/PM.

**Dependencies**: 2.1
**Acceptance**:
- All v1 patterns from brief parse correctly with tests
- Confidence scores calculated appropriately
- Ambiguous flag set for twice-a-day and bare times
- active_hours populated for time-window patterns (e.g., "between TIME and TIME")

### 2.3 Create LLM Schedule Parser Strategy Interface
**File**: `app/Support/NlSchedule/Contracts/LlmScheduleParserStrategy.php`

Define provider-agnostic interface with method `parse(string $input, string $timezone): ParseResult`. Include timeout and error handling contracts. Used for direct API fallback when CLI integration unavailable.

**Dependencies**: 2.1
**Acceptance**:
- Interface defines clear contract
- Supports async execution pattern

### 2.4 Implement Claude LLM Parser Strategy
**File**: `app/Support/NlSchedule/Strategies/ClaudeLlmParserStrategy.php`

Implement LlmScheduleParserStrategy reusing existing `InterrogationRunnerAdapter`, `AdapterFactory`, `ClaudeAdapter`, and `SystemPromptResolver` infrastructure from `app/Support/Interrogation/`. Include structured prompt for schedule parsing with output format matching ParseResult.

**Dependencies**: 2.3, existing `app/Support/Interrogation/AdapterFactory.php`, `app/Support/Interrogation/Adapters/ClaudeAdapter.php`, `app/Support/Interrogation/SystemPromptResolver.php`
**Acceptance**:
- Parses NL input via Claude CLI
- Returns valid ParseResult
- Respects configured timeout (`NL_PARSE_TIMEOUT_SECONDS`)

### 2.5 Implement Codex LLM Parser Strategy
**File**: `app/Support/NlSchedule/Strategies/CodexLlmParserStrategy.php`

Implement LlmScheduleParserStrategy reusing existing `CodexAdapter` infrastructure from `app/Support/Interrogation/`. Follow same patterns as Claude strategy.

**Dependencies**: 2.3, existing `app/Support/Interrogation/Adapters/CodexAdapter.php`
**Acceptance**:
- Parses NL input via Codex CLI
- Returns valid ParseResult
- Respects configured timeout

### 2.6 Create LLM Parser Strategy Factory
**File**: `app/Support/NlSchedule/LlmParserStrategyFactory.php`

Factory selecting appropriate LlmScheduleParserStrategy based on configured default provider. Reuse `InterrogationSetting` patterns for provider selection. Support provider selection via config and fallback logic.

**Dependencies**: 2.4, 2.5, existing `app/Models/InterrogationSetting.php`
**Acceptance**:
- Returns correct strategy for configured provider
- Handles provider unavailability gracefully

---

## Phase 3: Parse Orchestration Service

### 3.1 Create NL Parse Attempt Model
**File**: `app/Models/NlParseAttempt.php`

Eloquent model for nl_parse_attempts table with casts for JSON fields, enum status, and relationship to User. Include scopes for idempotency lookups (by user_id + input_text + timezone within configurable window) and status filtering.

**Dependencies**: 1.2
**Acceptance**:
- Model CRUD operations work
- Casts apply correctly
- Scopes return expected results

### 3.2 Create NL Parse Attempt Repository
**File**: `app/Support/NlSchedule/NlParseAttemptRepository.php`

Repository handling parse attempt CRUD, idempotency lookups (by user_id + input + timezone within configurable window from `NL_PARSE_IDEMPOTENCY_SECONDS`), and status transitions. Implement atomic state transitions.

**Dependencies**: 3.1
**Acceptance**:
- Idempotency lookup returns existing attempt within window
- Status transitions are atomic
- Duplicate submissions within 60-second window (default) return existing parse_attempt_id

### 3.3 Implement NL Schedule Parser Service
**File**: `app/Support/NlSchedule/NlScheduleParserService.php`

Main orchestration service implementing hybrid parsing flow:
1. Validate input length (200 char max from `NL_PARSE_MAX_INPUT_LENGTH`)
2. Check idempotency window for existing attempt
3. Execute rule-based parser
4. If confidence >= 0.75 (from `NL_PARSE_CONFIDENCE_THRESHOLD`): return completed with ParseResult immediately
5. If confidence < 0.75: check rate limits, queue LLM fallback job, return queued status with parse_attempt_id
6. On LLM failure/timeout: return failed status with best-effort rule_based_result

Logging policy: app logs contain first 80 chars + SHA-256 hash of input; full input_text stored only in nl_parse_attempts table.

**Dependencies**: 2.2, 2.6, 3.2
**Acceptance**:
- High-confidence rule-based returns completed immediately
- Low-confidence queues LLM job and returns queued status
- Idempotency prevents duplicate processing
- Rate limits enforced on LLM path only
- Logs contain first 80 chars + SHA-256 hash (not full input)

### 3.4 Create LLM Parse Job
**File**: `app/Jobs/ExecuteNlParseJob.php`

Queue job executing LLM fallback parsing. Updates parse attempt status (queued → running → completed/failed). Broadcasts `NlParseCompleted` WebSocket event on completion. Handles timeout and provider errors gracefully, populating rule_based_result fallback.

**Dependencies**: 3.3, 2.6
**Acceptance**:
- Job executes within configured timeout (30s default)
- Status transitions: queued → running → completed/failed
- WebSocket event fires on completion
- Fallback rule_based_result populated on failure

### 3.5 Create NL Parse Completed Event
**File**: `app/Events/NlParseCompleted.php`

Broadcast event implementing ShouldBroadcast on `private-user.{userId}` channel (matching existing `App.Models.User.{id}` pattern in channels.php). Event name: `NlParseCompleted`. Payload: `{ parse_attempt_id, status, result }` on success, `{ parse_attempt_id, status, error, rule_based_result }` on failure.

**Dependencies**: existing `routes/channels.php` pattern, existing `app/Events/InterrogationSessionUpdated.php` pattern
**Acceptance**:
- Event broadcasts to `private-user.{userId}` channel
- Payload matches contract specification
- Event name is exactly `NlParseCompleted`

### 3.6 Register WebSocket Channel Authorization
**File**: `routes/channels.php`

Verify existing `App.Models.User.{id}` channel authorization handles `private-user.{userId}` or add explicit authorization if needed.

**Dependencies**: 3.5
**Acceptance**:
- Channel authorization works for authenticated users
- Users can only subscribe to their own channel

---

## Phase 4: Internal API Endpoints

### 4.1 Create Parse Schedule Request
**File**: `app/Http/Requests/NlSchedule/ParseScheduleRequest.php`

Form request validating: input (required string, max 200), timezone (required string, valid IANA timezone).

**Dependencies**: None
**Acceptance**:
- Validates input length constraint (200 chars)
- Validates timezone format
- Returns appropriate error messages

### 4.2 Create NL Schedule Controller
**File**: `app/Http/Controllers/Internal/NlScheduleController.php`

Controller with methods:
- `parse(ParseScheduleRequest)`: POST endpoint invoking NlScheduleParserService, returning `{status, parse_attempt_id, result?}` for completed or `{status, parse_attempt_id}` for queued
- `show(string $parseAttemptId)`: GET endpoint returning parse attempt status: queued|running|completed|failed and result if completed

Both endpoints internal-only, authenticated via existing internal API middleware.

**Dependencies**: 3.3, 4.1
**Acceptance**:
- Parse endpoint returns `{status: "completed", parse_attempt_id, result}` for high-confidence immediately
- Parse endpoint returns `{status: "queued", parse_attempt_id}` for low-confidence
- Parse endpoint returns `{status: "failed", parse_attempt_id, rule_based_result, error}` on LLM failure
- Show endpoint returns current status
- Rate limit errors return 429

### 4.3 Register Internal API Routes
**File**: `routes/api.php`

Add routes under internal API prefix:
- POST `/internal/api/schedule/parse`
- GET `/internal/api/schedule/parse/{parseAttemptId}`

Apply internal API authentication middleware.

**Dependencies**: 4.2
**Acceptance**:
- Routes exactly match `/internal/api/schedule/parse` and `/internal/api/schedule/parse/{parse_attempt_id}`
- Routes accessible to authenticated users
- Routes not exposed publicly

---

## Phase 5: Active Hours Evaluation

### 5.1 Create Active Hours Evaluator
**File**: `app/Support/NlSchedule/ActiveHoursEvaluator.php`

Service evaluating whether a timestamp falls within active_hours_config window. Uses ISO-8601 day indexing (1=Mon..7=Sun). Returns boolean with optional skip metadata.

**Dependencies**: None
**Acceptance**:
- Correctly evaluates time within start/end range
- Correctly evaluates day-of-week constraint using ISO-8601 indexing
- Returns true when config is null (no restriction)

### 5.2 Create Next Runs Calculator
**File**: `app/Support/NlSchedule/NextRunsCalculator.php`

Service computing next N runs (default 5) accounting for both cron expression and active_hours_config filtering. Returns array of `{local, utc}` timestamp pairs showing true dispatch times. Uses existing Cron\CronExpression library.

**Dependencies**: 5.1
**Acceptance**:
- Returns correct next 5 runs for cron-only jobs
- Filters runs outside active hours window
- Handles timezone conversion correctly
- Next runs show true dispatch times (filtered by active hours)

### 5.3 Integrate Active Hours into DispatchDueService
**File**: `app/Support/Agent/DispatchDueService.php`

Modify `createScheduledRun` method to add active hours check after existing skip checks. Dispatch evaluation order exactly as specified in brief:
1. Cron due check (existing)
2. Active-hours check only if `active_hours_config IS NOT NULL`
3. Dispatch or skip

If outside active hours window: set status to skipped with structured metadata:
```json
{
  "skip_reason": "outside_active_hours",
  "job_id": "uuid",
  "scheduled_time": "ISO8601",
  "active_hours_config": { ... }
}
```

Ensure jobs with null active_hours_config behave exactly as before. Add new counter `skippedActiveHoursCount` to dispatch return stats.

**Dependencies**: 5.1, existing `app/Support/Agent/DispatchDueService.php`
**Acceptance**:
- Jobs with null active_hours_config unchanged (exact backward compatibility)
- Jobs with config skip outside window with `skip_reason: outside_active_hours`
- Skip reason logged with structured metadata including job_id, scheduled_time, active_hours_config
- Existing skip reasons (overlap, cooldown, rate_limited) unaffected
- Dispatch evaluation order: cron due → active_hours → dispatch/skip

---

## Phase 6: Update Agent Job Model and API

### 6.1 Update AgentJob Model
**File**: `app/Models/AgentJob.php`

Add `active_hours_config` to fillable array and casts (JSON). Add accessor/mutator if needed for validation.

**Dependencies**: 1.1
**Acceptance**:
- Model saves and retrieves active_hours_config correctly
- JSON cast applies

### 6.2 Update Store/Update Job Requests
**Files**: `app/Http/Requests/Agent/StoreAgentJobRequest.php`, `app/Http/Requests/Agent/UpdateAgentJobRequest.php`

Add optional `active_hours_config` field with JSON schema validation. Add `disable_active_hours` boolean field for update request that nullifies config when true.

**Dependencies**: 6.1
**Acceptance**:
- Valid active_hours_config accepted
- Invalid schema rejected with clear error
- disable_active_hours=true nullifies existing config

### 6.3 Update AgentJobController
**File**: `app/Http/Controllers/Api/V1/AgentJobController.php`

Modify store/update methods to:
1. Accept active_hours_config from validated request
2. Handle disable_active_hours flag in update
3. Include active_hours_config in transformJob response
4. Include next_run calculation accounting for active hours (using NextRunsCalculator)

**Dependencies**: 5.2, 6.2
**Acceptance**:
- Jobs created with active_hours_config via API
- Jobs updated with active_hours_config via API
- disable_active_hours clears config
- API response includes active_hours_config and filtered next_run_utc

---

## Phase 7: Frontend UI Implementation

### 7.1 Create NL Schedule Input Component
**File**: `resources/js/Components/Agent/NlScheduleInput.vue`

Vue component with:
- Text input (200 char max, character counter)
- Timezone dropdown (defaults to account timezone, supports override via NL input or dropdown)
- Parse button triggering API call to `/internal/api/schedule/parse`
- Loading state during async parse
- Error display for validation failures

Emits parsed result or error to parent.

**Dependencies**: None
**Acceptance**:
- Input enforces 200 char limit with visible counter
- Timezone dropdown shows common zones with account default
- Parse button invokes internal API
- Loading spinner during async operation

### 7.2 Create Parse Confirmation Modal
**File**: `resources/js/Components/Agent/ParseConfirmationModal.vue`

Blocking modal component (not inline or toast) displaying:
- Parsed cron expression (read-only, no inline editing)
- Human-readable explanation
- Timezone
- Next 5 runs table (local + UTC, filtered by active hours showing true dispatch times)
- For ambiguous times: AM/PM option selector
- Active hours summary if present
- Buttons: "Confirm", "Cancel", "Edit in Advanced Mode"

Modal must block background interaction. No inline cron editing capability.

**Dependencies**: 7.1
**Acceptance**:
- Modal blocks background interaction (blocking modal)
- Cron field is read-only (no inline editing)
- Next runs show filtered dispatch times (true dispatch times accounting for active hours)
- Ambiguous times show both AM/PM options
- "Edit in Advanced Mode" pre-fills cron and switches to Advanced tab

### 7.3 Create LLM Degradation Warning Banner
**File**: `resources/js/Components/Agent/LlmDegradationWarning.vue`

Warning banner component displayed when LLM timeout/rate-limit/outage occurs. Shows best-effort rule-based result with warning message. Requires explicit user confirmation to proceed.

**Dependencies**: None
**Acceptance**:
- Warning clearly indicates degraded mode
- Shows rule_based_result fallback
- Requires explicit confirm/cancel action (user must confirm to proceed)

### 7.4 Update JobForm with NL Mode Tab
**File**: `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

Add "Natural Language" mode/tab alongside existing Basic/Advanced in schedule section (currently has `schedule.mode` with 'basic' and 'advanced'):
1. Add third radio option "Natural Language" to schedule.mode
2. Show NlScheduleInput component when mode is 'natural_language'
3. Integrate WebSocket listener for `NlParseCompleted` events on `private-user.{userId}` channel
4. Show ParseConfirmationModal when confidence < 0.75 or ambiguous flag set (blocking modal)
5. Auto-populate cron_expression and active_hours_config from confirmed parse
6. Show LlmDegradationWarning when LLM fails with rule_based_result
7. "Edit in Advanced Mode" button switches tab and pre-fills generated cron (only path for editing)
8. In Advanced mode when active_hours_config exists: show "Disable active hours restriction" checkbox

Preserve existing Basic/Advanced flows unchanged.

**Dependencies**: 7.1, 7.2, 7.3, existing `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`
**Acceptance**:
- Three schedule mode tabs: Basic, Advanced, Natural Language
- NL mode shows input and parse button
- High-confidence parse auto-populates without modal
- Low-confidence/ambiguous shows blocking modal (not inline)
- Modal "Edit in Advanced Mode" switches to Advanced and pre-fills (only edit path)
- WebSocket receives `NlParseCompleted` events
- Active hours checkbox in Advanced mode
- Existing Basic/Advanced unchanged

### 7.5 Update Create Job Page
**File**: `resources/js/Pages/Agent/Jobs/Create.vue`

Ensure Create page passes active_hours_config in payload when present. No other changes needed as JobForm handles logic.

**Dependencies**: 7.4
**Acceptance**:
- Creating job via NL mode includes active_hours_config in API payload
- Job creation success redirects to index

### 7.6 Update Edit Job Page
**File**: `resources/js/Pages/Agent/Jobs/Edit.vue`

- Jobs always open in Advanced mode (not NL mode)
- Display active_hours_config summary if present
- Show "Disable active hours restriction" checkbox
- Handle disable_active_hours flag in update payload

**Dependencies**: 7.4
**Acceptance**:
- Edit page opens in Advanced mode regardless of creation method
- Active hours config displayed if present
- Checkbox nullifies config on save
- Update payload includes disable_active_hours when checked

### 7.7 Add NL Mode Navigation Discoverability
**File**: `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

Ensure Natural Language tab is visually prominent and includes helper text explaining the feature. Add tooltip or inline hint for first-time users.

**Dependencies**: 7.4
**Acceptance**:
- NL tab clearly visible in schedule mode selector
- Helper text explains "Describe your schedule in plain English"
- Tab styling consistent with Basic/Advanced
- Users can discover NL mode from job creation page without external documentation

---

## Phase 8: Telemetry and Cleanup

### 8.1 Create Parse Cleanup Command
**File**: `app/Console/Commands/NlParseCleanupCommand.php`

Artisan command `nl-parse:cleanup` deleting nl_parse_attempts older than configured retention (90 days from `NL_PARSE_RETENTION_DAYS`). Log count of deleted records.

**Dependencies**: 3.1
**Acceptance**:
- Command deletes records older than retention period
- Logs deletion count
- Handles large datasets efficiently (batch delete)

### 8.2 Schedule Cleanup Command
**File**: `app/Console/Kernel.php`

Schedule nl-parse:cleanup to run daily at off-peak hour.

**Dependencies**: 8.1
**Acceptance**:
- Command runs daily
- Scheduled at low-traffic time

### 8.3 Add RBAC Gate for Telemetry Access
**File**: `app/Policies/NlParseAttemptPolicy.php` or appropriate gate definition

Define authorization gate restricting nl_parse_attempts table access to admin and analytics roles only.

**Dependencies**: 3.1
**Acceptance**:
- Non-admin users cannot view other users' parse attempts
- Admin/analytics roles can access for analysis
- Gate enforced on any telemetry query endpoints

---

## Phase 9: Validation and Safety

### 9.1 Integrate Cron Validation in Parser
**Files**: `app/Support/NlSchedule/RuleBasedScheduleParser.php`, `app/Support/NlSchedule/Strategies/*.php`

All NL-generated cron expressions must pass through existing NumericCronExpression validation before being returned. Invalid or harmful schedules should be rejected with nearest valid suggestion.

**Dependencies**: 2.2, existing `app/Rules/NumericCronExpression.php`
**Acceptance**:
- All parser output passes NumericCronExpression validation
- Invalid expressions rejected with clear error
- Nearest valid suggestion provided on rejection
- Parser does not return invalid cron

### 9.2 Add Minimum Interval Validation
**File**: `app/Support/NlSchedule/RuleBasedScheduleParser.php`

Enforce minimum schedule interval (1 minute from `NL_PARSE_MIN_INTERVAL_MINUTES`) matching existing cron minimum. Reject schedules more frequent than minimum.

**Dependencies**: 2.2
**Acceptance**:
- Schedules more frequent than 1 minute rejected
- Error message indicates minimum interval

### 9.3 Add Rate Limit Enforcement
**File**: `app/Support/NlSchedule/NlScheduleParserService.php`

Implement rate limiting on LLM fallback path only using Laravel rate limiter:
- 10 per minute per user (from `NL_PARSE_RATE_LIMIT_PER_MINUTE`)
- 60 per hour per user (from `NL_PARSE_RATE_LIMIT_PER_HOUR`)

Rule-based path remains unlimited. Return 429 error when exceeded with retry-after header.

**Dependencies**: 3.3
**Acceptance**:
- Rate limits enforced per user on LLM path only
- Rule-based path unlimited
- 429 response includes retry-after
- Limits configurable via env vars

---

## Phase 10: Testing

### 10.1 Unit Tests for Rule-Based Parser
**File**: `tests/Unit/RuleBasedScheduleParserTest.php`

Comprehensive tests for all v1 patterns from brief including edge cases, confidence scoring, ambiguity detection, and active_hours population.

**Dependencies**: 2.2
**Acceptance**:
- All v1 patterns have positive and negative test cases
- Edge cases covered (midnight, year boundaries)
- Confidence scores verified
- Day indexing verified as ISO-8601 (1=Mon..7=Sun)

### 10.2 Unit Tests for Active Hours Evaluator
**File**: `tests/Unit/ActiveHoursEvaluatorTest.php`

Tests for time window evaluation, day-of-week constraints using ISO-8601 indexing, null config handling, and timezone edge cases.

**Dependencies**: 5.1
**Acceptance**:
- All evaluation scenarios covered
- Null config returns true (no restriction)
- Boundary conditions tested
- ISO-8601 day indexing verified

### 10.3 Unit Tests for Next Runs Calculator
**File**: `tests/Unit/NextRunsCalculatorTest.php`

Tests for cron-only calculation, active hours filtering, and correct count return showing true dispatch times.

**Dependencies**: 5.2
**Acceptance**:
- Correct next runs for various cron patterns
- Active hours filtering verified (shows true dispatch times)
- Timezone handling correct

### 10.4 Feature Tests for Parse API
**File**: `tests/Feature/NlScheduleParseTest.php`

Integration tests covering:
- High-confidence immediate completion with status "completed"
- Low-confidence async flow returning status "queued" with parse_attempt_id
- LLM failure returning status "failed" with rule_based_result
- Idempotency within 60-second window returning existing parse_attempt_id
- Rate limiting on LLM path
- Authentication requirement
- Invalid input rejection

**Dependencies**: 4.2
**Acceptance**:
- All API scenarios tested
- Error responses verified
- Status transitions verified (queued → running → completed/failed)

### 10.5 Feature Tests for Dispatch with Active Hours
**File**: `tests/Feature/AgentDispatchDueCommandTest.php` (extend existing)

Add tests for:
- Dispatch skipped outside active hours with skip_reason: outside_active_hours
- Dispatch proceeds within active hours
- Null config behaves as before (exact backward compatibility)
- Skip reason logged correctly with structured metadata

**Dependencies**: 5.3
**Acceptance**:
- Active hours skip verified with outside_active_hours reason
- Backward compatibility verified (null config unchanged)
- Logging verified with structured metadata

### 10.6 Feature Tests for Job CRUD with Active Hours
**File**: `tests/Feature/AgentJobActiveHoursTest.php`

Tests for creating/updating jobs with active_hours_config and disabling via checkbox.

**Dependencies**: 6.3
**Acceptance**:
- Jobs created with config via API
- Jobs updated with config via API
- disable_active_hours flag nullifies config

### 10.7 E2E Tests for NL Scheduling Flow
**File**: `tests/Browser/NlSchedulingTest.php` or Playwright equivalent

End-to-end tests covering:
- User enters NL input, high-confidence auto-populates
- User enters NL input, low-confidence shows blocking modal (not inline)
- User confirms modal, job created
- User cancels modal, returns to form
- User clicks "Edit in Advanced Mode", tab switches with cron pre-filled
- WebSocket receives NlParseCompleted event
- LLM degradation warning shown and confirmed
- NL mode tab discoverable from job creation page

**Dependencies**: 7.4
**Acceptance**:
- Full user flow tested in browser
- Modal interactions verified (blocking modal, no inline edit)
- WebSocket integration verified
- Feature discoverability verified

---

## Phase 11: Documentation and Cleanup

### 11.1 Update API Documentation
Document internal parse endpoints (`POST /internal/api/schedule/parse` and `GET /internal/api/schedule/parse/{parse_attempt_id}`), request/response contracts, WebSocket event format (`NlParseCompleted` on `private-user.{userId}`), and error responses.

**Dependencies**: 4.2
**Acceptance**:
- Endpoints documented with examples
- Error responses documented
- WebSocket event documented

### 11.2 Add Inline Help Text
Update JobForm inline help text to explain NL scheduling feature and limitations, including 200 char limit and supported patterns.

**Dependencies**: 7.4
**Acceptance**:
- Help text visible in NL mode
- Explains supported patterns
- Notes 200 char limit

## Sections

- Phase 1: Database Schema and Configuration
- Phase 2: Core Parser Infrastructure
- Phase 3: Parse Orchestration Service
- Phase 4: Internal API Endpoints
- Phase 5: Active Hours Evaluation
- Phase 6: Update Agent Job Model and API
- Phase 7: Frontend UI Implementation
- Phase 8: Telemetry and Cleanup
- Phase 9: Validation and Safety
- Phase 10: Testing
- Phase 11: Documentation and Cleanup


## Risks

- LLM provider rate limits or outages could degrade user experience during high-usage periods; mitigated by rule-based fallback with explicit degradation warning requiring user confirmation
- Rule-based parser v1 pattern coverage may not handle all user inputs; mitigated by LLM fallback and iterative pattern expansion based on telemetry
- WebSocket connection reliability varies by client network; mitigated by polling endpoint (/internal/api/schedule/parse/{parse_attempt_id}) as fallback mechanism
- Active hours integration into DispatchDueService could introduce bugs in existing dispatch flow; mitigated by null-config backward compatibility check and extensive testing
- Idempotency window (60s) may be too short for slow-typing users or too long for rapid iteration; configurable per environment via NL_PARSE_IDEMPOTENCY_SECONDS
- Frontend modal blocking behavior could frustrate users expecting inline editing; acceptance criteria explicitly prohibit inline editing per requirements, only Edit in Advanced mode path available
- Day indexing inconsistency between cron library (0=Sun) and ISO-8601 (1=Mon) could cause off-by-one errors; mitigated by explicit conversion layer in parser and comprehensive day indexing tests
- Telemetry table growth could impact database performance over time; mitigated by daily cleanup command with 90-day retention (NL_PARSE_RETENTION_DAYS)
- Rate limiting on LLM path could block legitimate high-frequency testing; limits configurable via NL_PARSE_RATE_LIMIT_PER_MINUTE and NL_PARSE_RATE_LIMIT_PER_HOUR, rule-based path remains unlimited


## Assumptions

- Existing InterrogationRunnerAdapter, AdapterFactory, ClaudeAdapter, CodexAdapter, SystemPromptResolver, and InterrogationSetting infrastructure is stable and suitable for reuse
- WebSocket broadcasting via private-user.{userId} channel works with existing App.Models.User.{id} authorization in channels.php
- Existing NumericCronExpression validation rule covers all valid 5-part cron expressions
- Laravel rate limiter infrastructure is available and configured
- Frontend build tooling supports Vue 3 component additions without configuration changes
- Account timezone is accessible in frontend context for defaulting timezone dropdown
- Existing internal API authentication middleware applies to new routes without modification
- Database supports JSON column type and querying (PostgreSQL confirmed from existing migrations)
- Cron library (dragonmantank/cron-expression) handles timezone-aware due calculations correctly
- Users have sufficient permissions to create/edit jobs when accessing NL scheduling feature

