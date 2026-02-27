# Implementation Plan

Derived from discovery session 9.

# Natural Language Scheduling Implementation Plan

## Executive Summary
Implement a Natural Language scheduling mode for the Agent Job Builder that allows users to describe schedules in plain English. The system uses a hybrid parser strategy (deterministic rule-based first, LLM fallback for low-confidence results) while preserving cron as the canonical runtime format.

---

## Phase 1: Database Schema and Configuration

### 1.1 Migration: Add active_hours_config to agent_jobs
Create migration to add nullable JSON column for storing active hours configuration.

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_active_hours_config_to_agent_jobs.php`

```php
Schema::table('agent_jobs', function (Blueprint $table) {
    $table->json('active_hours_config')->nullable()->after('env_json');
});
```

**Schema Contract:**
```json
{
  "start": "HH:MM",
  "end": "HH:MM", 
  "days": [1,2,3,4,5]
}
```
Days use ISO-8601 indexing: 1=Monday through 7=Sunday.

### 1.2 Migration: Create nl_parse_attempts Table
Create telemetry table for parse attempt analytics with RBAC-gated access.

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_nl_parse_attempts_table.php`

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid | FK to users, indexed |
| input_text | varchar(200) | Full NL input |
| timezone | varchar(64) | IANA timezone |
| parser_path | enum(rule_based, llm_fallback) | |
| confidence | decimal(3,2) | 0.00-1.00 |
| cron_result | varchar(100) | Generated cron |
| active_hours_result | json | Nullable |
| status | enum(queued, running, completed, failed) | Indexed |
| user_confirmed | boolean | Default false |
| error_message | text | Nullable |
| idempotency_key | varchar(128) | Unique, indexed |
| created_at | timestamp | Indexed |
| completed_at | timestamp | Nullable |

Add composite index on (user_id, status, created_at).

### 1.3 Configuration Values
Add NL parsing configuration to `config/agent.php`.

**File:** `config/agent.php`

```php
'nl_parse' => [
    'confidence_threshold' => (float) env('AGENT_NL_PARSE_CONFIDENCE_THRESHOLD', 0.75),
    'llm_timeout_seconds' => (int) env('AGENT_NL_PARSE_LLM_TIMEOUT_SECONDS', 30),
    'idempotency_window_seconds' => (int) env('AGENT_NL_PARSE_IDEMPOTENCY_WINDOW_SECONDS', 60),
    'rate_limit_per_minute' => (int) env('AGENT_NL_PARSE_RATE_LIMIT_PER_MINUTE', 10),
    'rate_limit_per_hour' => (int) env('AGENT_NL_PARSE_RATE_LIMIT_PER_HOUR', 60),
    'max_input_length' => 200,
    'min_interval_minutes' => 1,
    'retention_days' => 90,
],
```

### 1.4 Model Updates

**File:** `app/Models/AgentJob.php`
- Add `active_hours_config` to casts array as `'array'`

**File:** `app/Models/NlParseAttempt.php` (new)
- UUID primary key trait
- Casts for status enum, active_hours_result array
- Scope for idempotency lookup
- BelongsTo user relationship

---

## Phase 2: Rule-Based Parser Implementation

### 2.1 RuleBasedScheduleParser Service
Deterministic pattern matching for v1 patterns with confidence scoring.

**File:** `app/Support/NlSchedule/RuleBasedScheduleParser.php`

**Supported Patterns:**
| Pattern | Example | Confidence | Notes |
|---------|---------|------------|-------|
| every X minutes | "every 15 minutes" | 0.95 | |
| every X hours | "every 2 hours" | 0.95 | |
| daily at TIME | "daily at 9am" | 0.95 | |
| weekdays at TIME | "weekdays at 9am" | 0.95 | |
| weekends at TIME | "weekends at 10am" | 0.95 | |
| every {day} at TIME | "every monday at 3pm" | 0.95 | |
| every day between TIME and TIME | "every day between 9am and 5pm" | 0.85 | Populates active_hours |
| hourly | "hourly" | 0.95 | |
| twice a day | "twice a day" | 0.65 | Ambiguous, suggests 9am/5pm |
| every X hours starting at TIME | "every 2 hours starting at 9am" | 0.85 | Populates active_hours |
| every X minutes during business hours | "every 30 minutes during business hours" | 0.85 | 9-17 active_hours |
| at TIME (bare) | "at 5" | 0.50 | Ambiguous, show AM/PM options |

**Output Contract:**
```php
[
    'cron_expression' => '0 9 * * 1-5',
    'timezone' => 'America/New_York',
    'explanation' => 'Every weekday at 9:00 AM',
    'active_hours' => ['start' => '09:00', 'end' => '17:00', 'days' => [1,2,3,4,5]] | null,
    'next_runs' => [['local' => '...', 'utc' => '...'], ...],
    'confidence' => 0.95,
    'parser_path' => 'rule_based',
    'ambiguous' => false,
    'ambiguous_options' => [] // For bare time references: AM/PM alternatives
]
```

### 2.2 Time Parsing Utilities
**File:** `app/Support/NlSchedule/TimeParser.php`
- Parse "9am", "9:30pm", "14:00", "9", "noon", "midnight"
- Return structured time with ambiguity flag for bare numbers
- Support 12h and 24h formats

### 2.3 Day Parsing Utilities
**File:** `app/Support/NlSchedule/DayParser.php`
- Map day names to ISO-8601 indices (1=Mon..7=Sun)
- Parse ranges: "monday-friday", "mon-fri"
- Parse lists: "monday, wednesday, friday"
- Parse keywords: "weekdays" → [1,2,3,4,5], "weekends" → [6,7]

---

## Phase 3: LLM Fallback Implementation

### 3.1 LlmScheduleParserStrategy Interface
Provider-agnostic interface for direct API LLM parsing.

**File:** `app/Support/NlSchedule/Contracts/LlmScheduleParserStrategy.php`

```php
interface LlmScheduleParserStrategy
{
    public function parse(string $input, string $timezone): ParseResult;
    public function supportsAsync(): bool;
}
```

### 3.2 Claude LLM Parser Implementation
Reuse existing InterrogationRunnerAdapter infrastructure for CLI integration path.

**File:** `app/Support/NlSchedule/Adapters/ClaudeLlmScheduleParser.php`
- Build command using ClaudeAdapter patterns
- Use JSON schema for structured output
- Timeout handling with configurable limit
- Parse response into standard ParseResult

### 3.3 Codex LLM Parser Implementation
**File:** `app/Support/NlSchedule/Adapters/CodexLlmScheduleParser.php`
- Build command using CodexAdapter patterns
- Same JSON schema and output contract

### 3.4 LLM Adapter Factory
**File:** `app/Support/NlSchedule/LlmAdapterFactory.php`
- Factory method returning appropriate LLM strategy
- Use InterrogationSetting runner_type preference
- Configurable default provider fallback

---

## Phase 4: Orchestration Service

### 4.1 NlScheduleParserService
Main orchestrator for hybrid parsing workflow.

**File:** `app/Support/NlSchedule/NlScheduleParserService.php`

**Workflow:**
1. Validate input length (≤200 chars)
2. Check idempotency window for duplicate requests
3. Execute rule-based parser
4. If confidence ≥ 0.75: return completed immediately
5. If confidence < 0.75: queue LLM fallback job, return queued status
6. Store parse attempt in nl_parse_attempts

**Rate Limiting (LLM path only):**
- Check Redis rate limiter: 10/min, 60/hour per user
- Return 429 with retry-after if exceeded

### 4.2 NlParseAttemptRepository
**File:** `app/Support/NlSchedule/NlParseAttemptRepository.php`
- Create/update parse attempts
- Idempotency lookup by hash(user_id + input + timezone)
- Status transition methods

### 4.3 Async LLM Parse Job
**File:** `app/Jobs/ExecuteNlParseJob.php`
- Queued job for LLM fallback path
- Timeout: configurable (default 30s)
- On success: update attempt status to completed, broadcast event
- On failure: update status to failed, include rule_based_result fallback
- On timeout: same as failure with timeout error

---

## Phase 5: API Endpoints

### 5.1 Parse Endpoint
**Route:** `POST /internal/api/schedule/parse`
**File:** `app/Http/Controllers/Internal/ScheduleParseController.php`

**Request:**
```json
{
  "input": "every weekday at 9am",
  "timezone": "America/New_York"
}
```

**Response (completed):**
```json
{
  "status": "completed",
  "parse_attempt_id": "uuid",
  "result": { /* Parser Output Contract */ }
}
```

**Response (queued):**
```json
{
  "status": "queued",
  "parse_attempt_id": "uuid"
}
```

**Response (failed with fallback):**
```json
{
  "status": "failed",
  "parse_attempt_id": "uuid",
  "rule_based_result": { /* best-effort Parser Output Contract */ },
  "error": "LLM timeout"
}
```

### 5.2 Poll Endpoint
**Route:** `GET /internal/api/schedule/parse/{parse_attempt_id}`
**Response:** Status with result when completed

### 5.3 Request Validation
**File:** `app/Http/Requests/Internal/ParseScheduleRequest.php`
- input: required|string|max:200
- timezone: required|timezone

### 5.4 Route Registration
**File:** `routes/api.php`
- Add internal API route group
- Apply auth:sanctum middleware
- No public API exposure

---

## Phase 6: WebSocket Integration

### 6.1 Broadcast Channel
**File:** `routes/channels.php`

Add channel authorization:
```php
Broadcast::channel('nl-parse.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

### 6.2 NlParseCompleted Event
**File:** `app/Events/NlParseCompleted.php`
- Implements ShouldBroadcast
- Channel: `private-nl-parse.user.{userId}`
- Payload: parse_attempt_id, status, result | error, rule_based_result?

---

## Phase 7: Active Hours Evaluation

### 7.1 ActiveHoursEvaluator Service
**File:** `app/Support/NlSchedule/ActiveHoursEvaluator.php`

```php
public function isWithinActiveHours(
    ?array $activeHoursConfig,
    CarbonImmutable $timestamp,
    string $timezone
): bool
```

- Return true if config is null (no restriction)
- Parse start/end times
- Check if timestamp day (ISO-8601) is in days array
- Check if timestamp time is within start-end window
- Handle overnight windows (end < start)

### 7.2 NextRunsCalculator Service
**File:** `app/Support/NlSchedule/NextRunsCalculator.php`

Compute next N runs accounting for both cron and active_hours filtering:
- Generate candidate runs from cron
- Filter by active_hours_config
- Return first 5 that pass filter
- Include local + UTC timestamps

### 7.3 DispatchDueService Integration
**File:** `app/Support/Agent/DispatchDueService.php`

Modify `createScheduledRun` method to add active hours check:

```php
// After existing skip checks (overlap, rate_limit, cooldown)
if ($status !== AgentJobRun::STATUS_SKIPPED) {
    $activeHoursConfig = $job->active_hours_config;
    if ($activeHoursConfig !== null) {
        $evaluator = app(ActiveHoursEvaluator::class);
        $zonedTime = $dueWindow->setTimezone($job->timezone);
        if (!$evaluator->isWithinActiveHours($activeHoursConfig, $zonedTime, $job->timezone)) {
            $status = AgentJobRun::STATUS_SKIPPED;
            $metadata['skip_reason'] = 'outside_active_hours';
            $metadata['active_hours_config'] = $activeHoursConfig;
            $finishedAt = $tickTimestamp;
        }
    }
}
```

Add to skip logging in `createScheduledRun`:
```php
'outside_active_hours' => 'skipped_outside_active_hours',
```

Update return array mapping in dispatch method.

---

## Phase 8: Frontend Implementation

### 8.1 JobForm NL Mode Tab
**File:** `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

Add third schedule mode alongside basic/advanced:
```javascript
const schedule = reactive({
    mode: 'basic', // 'basic' | 'advanced' | 'natural_language'
    // ...existing fields
    nlInput: '',
    nlTimezone: null, // null = use account default
    nlParseAttemptId: null,
    nlParseStatus: null, // null | 'parsing' | 'completed' | 'failed'
    nlParseResult: null,
});
```

Mode tabs UI:
- Basic (existing)
- Advanced (existing)
- Natural Language (new)

### 8.2 NL Input Section Component
**File:** `resources/js/Components/Agent/NlScheduleInput.vue`

- Text input (200 char max, character counter)
- Timezone dropdown (defaults to account timezone from props)
- "Parse Schedule" button
- Loading state during parse
- Error display for validation/rate limit errors

### 8.3 Parse Confirmation Modal Component
**File:** `resources/js/Components/Agent/NlParseConfirmationModal.vue`

Blocking modal displayed when:
- confidence < 0.75
- ambiguous flag is true

Content:
- Parsed cron expression (read-only code display)
- Human-readable explanation
- Timezone
- Next 5 runs table (local + UTC, filtered by active_hours)
- For ambiguous times: radio buttons for AM/PM selection
- Buttons: "Confirm", "Cancel", "Edit in Advanced Mode"

"Edit in Advanced Mode" action:
- Pre-fill cron_expression in advanced mode
- Switch schedule.mode to 'advanced'
- Close modal

### 8.4 LLM Degradation Warning Component
**File:** `resources/js/Components/Agent/NlParseDegradationWarning.vue`

Warning banner shown when LLM failed/timeout with rule_based_result fallback:
- Warning icon + message explaining degradation
- Display best-effort result
- "Confirm Anyway" button (requires explicit confirmation)
- "Cancel" button

### 8.5 WebSocket Subscription
**File:** `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

Subscribe to `private-nl-parse.user.{userId}` channel:
- On NlParseCompleted event: update parse status/result
- Unsubscribe on component unmount

### 8.6 Active Hours Display in Advanced Mode
**File:** `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

When editing existing job with active_hours_config:
- Show "Active Hours Restriction" info box
- Display configured hours and days
- "Disable active hours restriction" checkbox
- On save with checkbox checked: send active_hours_config: null

### 8.7 Jobs List UI Updates
Ensure NL-created jobs display correctly in job list with appropriate indicators.

---

## Phase 9: AgentJob API Updates

### 9.1 Store/Update Request Handling
**File:** `app/Http/Requests/Agent/StoreAgentJobRequest.php`
**File:** `app/Http/Requests/Agent/UpdateAgentJobRequest.php`

Add validation for active_hours_config:
```php
'active_hours_config' => ['nullable', 'array'],
'active_hours_config.start' => ['required_with:active_hours_config', 'date_format:H:i'],
'active_hours_config.end' => ['required_with:active_hours_config', 'date_format:H:i'],
'active_hours_config.days' => ['required_with:active_hours_config', 'array', 'min:1'],
'active_hours_config.days.*' => ['integer', 'min:1', 'max:7'],
```

### 9.2 Controller Updates
**File:** `app/Http/Controllers/Api/V1/AgentJobController.php`

- Include active_hours_config in store/update operations
- Add to transformJob response
- Support null to clear active_hours_config on update

---

## Phase 10: Telemetry and Cleanup

### 10.1 Parse Attempt Logging
**File:** `app/Support/NlSchedule/NlParseAttemptRepository.php`

On parse attempt creation/completion:
- Store full input_text in database (200 char max)
- Application logs: first 80 chars + SHA-256 hash

**Log format:**
```php
Log::info('NL parse attempt', [
    'parse_attempt_id' => $attempt->id,
    'user_id' => $attempt->user_id,
    'input_preview' => Str::limit($input, 80),
    'input_hash' => hash('sha256', $input),
    'parser_path' => $attempt->parser_path,
    'confidence' => $attempt->confidence,
    'status' => $attempt->status,
]);
```

### 10.2 Cleanup Command
**File:** `app/Console/Commands/NlParseCleanupCommand.php`

```php
protected $signature = 'nl-parse:cleanup
    {--dry-run : Preview rows without deleting}
    {--chunk=500 : Chunk size per batch}';
```

- Delete nl_parse_attempts older than 90 days
- Chunk processing for large datasets
- Log summary

### 10.3 Scheduled Task Registration
**File:** `app/Console/Kernel.php` or `routes/console.php`

```php
Schedule::command('nl-parse:cleanup')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping();
```

### 10.4 RBAC for Telemetry Access
**File:** `app/Policies/NlParseAttemptPolicy.php`

- viewAny: admin or analytics role
- view: admin or analytics role
- No create/update/delete via policy (system-only)

---

## Phase 11: Validation and Safety

### 11.1 Cron Expression Validation
All NL-generated cron expressions must pass through existing validation:

**File:** `app/Support/NlSchedule/CronValidator.php`
- Validate 5-part numeric cron format
- Reject intervals below min_interval_minutes (1 minute)
- Use existing NumericCronExpression validation rules
- Return validation errors with nearest valid suggestion

### 11.2 Input Sanitization
**File:** `app/Support/NlSchedule/InputSanitizer.php`
- Trim whitespace
- Normalize Unicode
- Reject control characters
- Enforce 200 character limit

---

## Phase 12: Testing

### 12.1 Unit Tests
- RuleBasedScheduleParser pattern coverage (all v1 patterns)
- TimeParser edge cases (12h, 24h, ambiguous)
- DayParser ISO-8601 indexing verification
- ActiveHoursEvaluator window calculations
- NextRunsCalculator filtering logic
- CronValidator rejection cases

### 12.2 Feature Tests
- Parse API endpoint happy path (high confidence)
- Parse API endpoint low confidence → queued flow
- Parse API idempotency within window
- Parse API rate limiting enforcement
- WebSocket event broadcasting
- DispatchDueService active hours skip
- Cleanup command retention enforcement

### 12.3 Integration Tests
- End-to-end job creation via NL mode
- Confirmation modal flow
- Advanced mode pre-fill from NL result
- Active hours persistence and dispatch behavior

### 12.4 Acceptance Verification
| Criteria | Verification Method |
|----------|---------------------|
| NL mode creates valid job | E2E test creating job with "every weekday at 9am" |
| High confidence auto-accepts | Assert no modal shown when confidence ≥ 0.75 |
| Low confidence shows modal | Assert modal displayed when confidence < 0.75 |
| Modal shows filtered next runs | Assert runs respect active_hours_config |
| Edit in Advanced pre-fills cron | Assert cron_expression populated in advanced tab |
| Active hours skip logged | Assert skip_reason: outside_active_hours in run metadata |
| Existing jobs unchanged | Assert null active_hours_config jobs dispatch normally |
| Telemetry persisted | Assert nl_parse_attempts records created |
| Cleanup enforced | Assert records > 90 days deleted |

---

## Dependency Graph

```
Phase 1 (Schema/Config)
    ↓
Phase 2 (Rule-Based Parser) ─────────────────┐
    ↓                                        │
Phase 3 (LLM Fallback) ──────────────────────┤
    ↓                                        │
Phase 4 (Orchestration) ←────────────────────┘
    ↓
Phase 5 (API Endpoints)
    ↓
Phase 6 (WebSocket) ─────────────────────────┐
    ↓                                        │
Phase 7 (Active Hours Eval) ─────────────────┤
    ↓                                        │
Phase 8 (Frontend) ←─────────────────────────┘
    ↓
Phase 9 (AgentJob API Updates)
    ↓
Phase 10 (Telemetry/Cleanup)
    ↓
Phase 11 (Validation)
    ↓
Phase 12 (Testing)
```

---

## Navigation and Discoverability

### In-App Access Points
1. **Job Create/Edit Page**: Schedule section shows Basic/Advanced/Natural Language tabs
2. **Natural Language Tab**: Clearly labeled with descriptive helper text
3. **Modal Workflow**: Blocking confirmation prevents accidental submission
4. **Edit in Advanced**: One-click escape hatch to cron editing

### User Education
- Placeholder text in NL input: "e.g., every weekday at 9am"
- Helper text explaining supported patterns
- Inline examples below input field
- Explanation text in confirmation modal

### Admin Access
- nl_parse_attempts table access via admin/analytics RBAC roles only
- No public API exposure for parse telemetry

---

## Backward Compatibility Checklist

| Area | Verification |
|------|--------------|
| Existing jobs with null active_hours_config | Dispatch unchanged - no active hours check when null |
| Basic mode scheduling | Unmodified code path |
| Advanced mode scheduling | Unmodified code path |
| Job API contract | Only additive changes (active_hours_config field) |
| Scheduler behavior | Only additive check after existing skip evaluations |
| UI existing flows | No changes to Basic/Advanced mode UX |

## Sections

- Phase 1: Database Schema and Configuration
- Phase 2: Rule-Based Parser Implementation
- Phase 3: LLM Fallback Implementation
- Phase 4: Orchestration Service
- Phase 5: API Endpoints
- Phase 6: WebSocket Integration
- Phase 7: Active Hours Evaluation
- Phase 8: Frontend Implementation
- Phase 9: AgentJob API Updates
- Phase 10: Telemetry and Cleanup
- Phase 11: Validation and Safety
- Phase 12: Testing


## Risks

- LLM fallback latency may exceed 30s timeout under load, requiring graceful degradation to rule-based results
- Rule-based parser v1 pattern coverage may not handle edge cases, leading to unexpected LLM fallback frequency
- ISO-8601 day indexing (1=Mon) differs from cron standard (0=Sun), requiring careful translation at cron generation
- Active hours overnight windows (e.g., 10pm-6am) require special handling to avoid incorrect skip evaluations
- WebSocket connection failures require polling fallback to prevent UI from blocking indefinitely
- Rate limiting on LLM path may frustrate users if rule-based confidence is consistently low for their inputs
- Telemetry table growth requires monitoring; 90-day retention may still accumulate significant data for high-volume users
- Ambiguous time handling (e.g., 'at 5') may confuse users if AM/PM selection UI is not sufficiently clear
- Existing jobs edited in Advanced mode will not migrate NL metadata; NL input is not persisted on jobs


## Assumptions

- Existing InterrogationRunnerAdapter, AdapterFactory, and ClaudeAdapter infrastructure is functional and can be extended for NL parsing
- Redis is available for rate limiting and idempotency caching
- Laravel Reverb/Echo WebSocket infrastructure is operational for real-time event broadcasting
- Account timezone is available via authenticated user context for defaulting NL timezone
- NumericCronExpression validation in existing request validation can be reused for NL-generated cron
- Users understand basic scheduling terminology (weekdays, AM/PM, timezone concepts)
- The nl_parse_attempts table access restriction via RBAC roles is sufficient for compliance requirements
- LLM provider (Claude/Codex) availability is consistent enough that fallback path handles transient failures gracefully
- Active hours configuration is simple enough that v1 schema (start, end, days) covers majority of use cases
- Frontend Vue/Inertia stack supports modal blocking patterns without significant architectural changes

