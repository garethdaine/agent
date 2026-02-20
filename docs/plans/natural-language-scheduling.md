# Implementation Plan

Derived from discovery session 5.

# F-011: Natural Language Job Builder — Implementation Plan

## 1. Database Migrations

### 1.1 Add `active_hours_config` column to `agent_jobs` table
Create migration: `add_active_hours_config_to_agent_jobs`
- Add nullable JSON column `active_hours_config` to `agent_jobs` table
- No default value, no backfill, no data rewrite
- Schema: `{"start": "09:00", "end": "17:00", "days": [1, 2, 3, 4, 5]}` (ISO day indexing 1=Mon..7=Sun)
- Down migration: drop the column
- **File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_active_hours_config_to_agent_jobs.php`

### 1.2 Create `nl_parse_attempts` table
Create migration: `create_nl_parse_attempts_table`
- Columns:
  - `id` — UUID primary key
  - `user_id` — foreign key to `users` table, cascadeOnDelete
  - `input_text` — string(200), not nullable
  - `timezone` — string(64), not nullable
  - `parser_path` — string(20), enum-like: `rule_based`, `llm_fallback`
  - `confidence` — float, nullable
  - `cron_result` — string(100), nullable
  - `active_hours_result` — JSON, nullable (stores parsed active_hours object)
  - `user_confirmed` — boolean, nullable
  - `status` — string(20): `queued`, `running`, `completed`, `failed`
  - `error` — text, nullable
  - `result_payload` — JSON, nullable (full parse response)
  - `rule_based_result_payload` — JSON, nullable (best-effort fallback)
  - `parse_attempt_id` — UUID, unique index (for async tracking + idempotency)
  - `input_fingerprint` — string(64), index (SHA-256 of `input_text + timezone + user_id` for idempotency lookups)
  - `created_at`, `updated_at` — timestamps
- Index on `[user_id, created_at]` for analytics queries
- Index on `[input_fingerprint, created_at]` for idempotency lookups
- Index on `[created_at]` for purge job
- **File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_nl_parse_attempts_table.php`

### 1.3 Update `AgentJob` model
- Add `active_hours_config` to the `casts()` method as `'array'`
- No other model changes required; `$guarded = []` already allows mass assignment
- **File**: `app/Models/AgentJob.php`

### 1.4 Create `NlParseAttempt` model
- UUID primary key (`$incrementing = false`, `$keyType = 'string'`)
- Casts: `confidence` → `float`, `user_confirmed` → `boolean`, `result_payload` → `array`, `rule_based_result_payload` → `array`, `active_hours_result` → `array`
- BelongsTo `User`
- Scope `recent(int $seconds = 60)` for idempotency window queries
- Scope `stale(int $days = 90)` for purge queries
- **File**: `app/Models/NlParseAttempt.php`

---

## 2. Configuration

### 2.1 Add NL parse config to `config/agent.php`
Add a new `nl_parse` key to the agent config array:
```php
'nl_parse' => [
    'confidence_threshold' => (float) env('NL_PARSE_CONFIDENCE_THRESHOLD', 0.75),
    'max_input_length' => (int) env('NL_PARSE_MAX_INPUT_LENGTH', 200),
    'llm' => [
        'enabled' => (bool) env('NL_PARSE_LLM_ENABLED', true),
        'timeout_seconds' => (int) env('NL_PARSE_LLM_TIMEOUT_SECONDS', 30),
        'rate_limit' => [
            'per_minute' => (int) env('NL_PARSE_LLM_RATE_LIMIT_PER_MINUTE', 10),
            'per_hour' => (int) env('NL_PARSE_LLM_RATE_LIMIT_PER_HOUR', 60),
        ],
    ],
    'idempotency_window_seconds' => (int) env('NL_PARSE_IDEMPOTENCY_WINDOW_SECONDS', 60),
    'retention_days' => (int) env('NL_PARSE_RETENTION_DAYS', 90),
    'polling' => [
        'interval_seconds' => 2,
        'max_polls' => 20,
    ],
],
```
- **File**: `config/agent.php`

---

## 3. Domain Services — Rule-Based Parser

### 3.1 Create `RuleBasedScheduleParser`
Standalone service class responsible for deterministic NL-to-cron translation.

**Location**: `app/Support/NaturalLanguage/RuleBasedScheduleParser.php`

**Method**: `parse(string $input, string $timezone): ParseResult`

**Pattern matching (ordered by specificity)**:
1. `every X minutes` → `*/X * * * *` (validate X ∈ [1,59])
2. `every X hours` → `0 */X * * *` (validate X ∈ [1,23])
3. `hourly` → `0 * * * *`
4. `daily at TIME` → `M H * * *`
5. `weekdays at TIME` → `M H * * 1-5`
6. `weekends at TIME` → `M H * * 0,6`
7. `every DAYNAME at TIME` → `M H * * DOW` (map day names to cron DOW numbers)
8. `every day between TIME and TIME` → `0 H1-H2 * * *` (active-hours: generate cron range and/or `active_hours` config)
9. `twice a day` → default suggestion `0 9,17 * * *`, confidence = 0.60 (below threshold, ambiguous)

**TIME parsing**: Support `9am`, `9:30am`, `09:00`, `9 AM`, `9:00 AM`, `17:00` formats. Extract hours + minutes.

**Timezone extraction**: Detect timezone abbreviations in input (e.g., "at 9am EST"). Map common abbreviations to IANA: `EST→America/New_York`, `CST→America/Chicago`, `MST→America/Denver`, `PST→America/Los_Angeles`, `GMT→UTC`, `UTC→UTC`. If detected, override the provided timezone parameter.

**Confidence scoring**:
- Exact pattern match with unambiguous time → 1.0
- Exact pattern match with defaulted values (e.g., no minute specified) → 0.85
- `twice a day` without times → 0.60
- No pattern match → 0.0 (triggers LLM fallback)

**Output**: `ParseResult` value object containing all fields from the full parse response payload spec.

**Frequency guardrails**: After generating cron, validate it produces a minimum interval ≥ 1 minute. If input implies sub-minute frequency (e.g., "every 30 seconds"), reject with error and suggest "every 1 minute" as nearest valid alternative.

- **File**: `app/Support/NaturalLanguage/RuleBasedScheduleParser.php`

### 3.2 Create `ParseResult` value object
Immutable data class holding the full parse response:
- `cron_expression: string`
- `timezone: string`
- `explanation: string`
- `active_hours: ?ActiveHoursConfig`
- `next_runs: array` (array of `['local' => ISO-8601, 'utc' => ISO-8601]`)
- `confidence: float`
- `parser_path: string` (`rule_based` | `llm_fallback`)
- `ambiguous: bool` (derived: confidence < threshold)
- `error: ?string`
- `suggested_alternative: ?string` (for rejected inputs)
- Methods: `toArray(): array`, `isAcceptable(): bool`
- **File**: `app/Support/NaturalLanguage/ParseResult.php`

### 3.3 Create `ActiveHoursConfig` value object
- `start: string` (HH:MM)
- `end: string` (HH:MM)
- `days: array` (ISO day numbers 1-7)
- Methods: `toArray(): array`, `static fromArray(array): self`, `isWithinWindow(CarbonImmutable $time): bool`
- The `isWithinWindow` method checks day-of-week via `Carbon::dayOfWeekIso` (already 1=Mon..7=Sun) and time via start (inclusive) / end (exclusive) comparison
- **File**: `app/Support/NaturalLanguage/ActiveHoursConfig.php`

### 3.4 Create `RunPreviewGenerator`
Service that generates next-N run timestamps.
- Reuse `Cron\CronExpression::factory()` and timezone handling from `AgentJobController::transformJob()`
- Method: `generate(string $cronExpression, string $timezone, ?ActiveHoursConfig $activeHours, int $count = 5): array`
- Returns array of `['local' => ISO-8601, 'utc' => ISO-8601]`
- When `ActiveHoursConfig` is provided, filter out runs that fall outside the active window, continuing iteration until `$count` valid runs are found (with a safety cap of 1000 iterations)
- **File**: `app/Support/NaturalLanguage/RunPreviewGenerator.php`

---

## 4. Domain Services — NaturalLanguageScheduleParser Orchestrator

### 4.1 Create `NaturalLanguageScheduleParser`
Top-level orchestrator service.

**Location**: `app/Support/NaturalLanguage/NaturalLanguageScheduleParser.php`

**Dependencies** (injected):
- `RuleBasedScheduleParser`
- `RunPreviewGenerator`
- `NlParseTelemetryService`
- `NumericCronExpression` (for validation)

**Method**: `parse(string $inputText, string $timezone, int $userId): ParseAttemptResult`

**Orchestration flow**:
1. Validate input length (≤ 200 chars)
2. Call `RuleBasedScheduleParser::parse()`
3. If confidence ≥ threshold (0.75):
   - Validate cron via `NumericCronExpression`
   - Generate run previews via `RunPreviewGenerator`
   - Record telemetry
   - Return synchronous `completed` result
4. If confidence < threshold and > 0.0:
   - Store rule-based best-effort in `NlParseAttempt` record
   - If LLM enabled: dispatch `ProcessNlParseJob` (async), return `queued` status with `parse_attempt_id`
   - If LLM disabled: return rule-based result with low confidence as-is
5. If confidence = 0.0 (no pattern match):
   - If LLM enabled: dispatch `ProcessNlParseJob`, return `queued`
   - If LLM disabled: return error result

**Idempotency**: Before creating a new `NlParseAttempt`, check for existing record with matching `input_fingerprint` within the idempotency window (60s). If found, return existing `parse_attempt_id`.

- **File**: `app/Support/NaturalLanguage/NaturalLanguageScheduleParser.php`

### 4.2 Create `ParseAttemptResult` value object
Wraps the API response for initial POST:
- `status: string` (`completed` | `queued`)
- `parse_attempt_id: ?string` (UUID, present when queued)
- `result: ?ParseResult` (present when completed synchronously)
- `rule_based_result: ?ParseResult` (present when queued, may be null)
- Method: `toArray(): array`
- **File**: `app/Support/NaturalLanguage/ParseAttemptResult.php`

---

## 5. LLM Fallback Path

### 5.1 Create `NlParseLlmService`
Service that orchestrates the LLM call for NL parsing.

**Location**: `app/Support/NaturalLanguage/NlParseLlmService.php`

**Approach**: Use the existing `InterrogationRunnerAdapter` / `AdapterFactory` pattern. However, since `InterrogationRunnerAdapter` is tightly coupled to `InterrogationSession`, create a lightweight **adapter wrapper** that:
- Resolves the LLM provider via `InterrogationSetting::getForUser()` for `interrogation.runner_type` (or a new `nl_parse.runner_type` setting with fallback)
- Uses `AdapterFactory::make()` to get the correct adapter
- Builds a CLI command using the adapter's executable with a specialized system prompt and structured JSON output schema
- Runs the command synchronously (within the queued job context) with the configured timeout (30s)
- Parses the JSON response into a `ParseResult`

**System prompt**: Dedicated NL-to-cron system prompt that instructs the LLM to:
- Parse the natural language schedule description
- Return a JSON object matching the parse response schema
- Include cron_expression (5-part numeric only), timezone, explanation, active_hours, confidence
- Never use month/day names in cron — numeric only

**Timeout**: Enforced via `Process::timeout()` on the CLI subprocess, configured from `NL_PARSE_LLM_TIMEOUT_SECONDS`

- **File**: `app/Support/NaturalLanguage/NlParseLlmService.php`

### 5.2 Create `ProcessNlParseJob`
Queued Laravel job for async LLM fallback processing.

**Location**: `app/Jobs/ProcessNlParseJob.php`

**Flow**:
1. Load `NlParseAttempt` by `parse_attempt_id`
2. Update status to `running`
3. Call `NlParseLlmService::parse()`
4. On success:
   - Validate returned cron via `NumericCronExpression`
   - Generate run previews
   - Update `NlParseAttempt` to `completed` with `result_payload`
   - Broadcast `NlParseCompleted` event
5. On failure/timeout:
   - Update `NlParseAttempt` to `failed` with error message
   - Broadcast `NlParseCompleted` event with failed status
   - Preserve `rule_based_result_payload` for client fallback
6. Record telemetry

**Queue**: `default` queue (not `agent` — avoid competing with agent run jobs)

**Retries**: 0 retries (fail fast, client handles degradation)

- **File**: `app/Jobs/ProcessNlParseJob.php`

### 5.3 Create `NlParseCompleted` broadcast event
Following the existing `InterrogationSessionUpdated` pattern.

**Location**: `app/Events/NlParseCompleted.php`

- Implements `ShouldBroadcast`
- Channel: `PrivateChannel('App.Models.User.'.$this->userId)` (reuses existing user channel auth from `channels.php`)
- Broadcast as: `nl-parse.completed`
- Payload: `parse_attempt_id`, `status`, `result` (if completed), `error` (if failed), `rule_based_result` (if available)

- **File**: `app/Events/NlParseCompleted.php`

---

## 6. Telemetry Service

### 6.1 Create `NlParseTelemetryService`
**Location**: `app/Support/NaturalLanguage/NlParseTelemetryService.php`

**Responsibilities**:
- Write to `nl_parse_attempts` DB table (full `input_text`, up to 200 chars)
- Write to application logs via `Log::info()` with truncated input (first 80 chars) + SHA-256 hash of full input
- Never write full `input_text` to application logs
- Method: `record(int $userId, string $inputText, string $parserPath, float $confidence, ?string $cronResult, ?bool $userConfirmed): void`
- Method: `recordConfirmation(string $parseAttemptId, bool $confirmed): void`

### 6.2 Create `PurgeStaleNlParseAttemptsCommand`
Artisan command for 90-day retention cleanup.

**Location**: `app/Console/Commands/PurgeStaleNlParseAttemptsCommand.php`

- Signature: `nl-parse:purge {--days=90 : Retention period in days}`
- Deletes rows from `nl_parse_attempts` where `created_at < now() - retention_days`
- Chunked deletion (1000 rows at a time) to avoid lock contention
- Register in Laravel scheduler (`Console/Kernel.php` or `routes/console.php`) to run daily

---

## 7. Rate Limiting

### 7.1 Create `NlParseLlmRateLimiter` middleware
**Location**: `app/Http/Middleware/NlParseLlmRateLimiter.php`

**Behavior**:
- Applied to the `POST /internal/api/schedule/parse` endpoint
- Only activates when the request would trigger the LLM fallback path
- Uses Laravel's `RateLimiter` facade with two limiters:
  - Per-minute: `nl-parse-llm-minute:{user_id}` → max from `config('agent.nl_parse.llm.rate_limit.per_minute')`
  - Per-hour: `nl-parse-llm-hour:{user_id}` → max from `config('agent.nl_parse.llm.rate_limit.per_hour')`
- On limit exceeded: HTTP 429 with `Retry-After` header
- Rule-based parses bypass this middleware entirely (checked in the controller logic, not middleware — middleware only wraps the LLM dispatch path)

**Implementation note**: Since rate limiting is conditional on whether LLM fallback is needed (determined after rule-based parse), enforce this in the controller/service layer rather than pure middleware. Register a named rate limiter in `AppServiceProvider` or `RouteServiceProvider` that the controller can invoke conditionally.

---

## 8. API Controller + Routes

### 8.1 Create `NlScheduleParseController`
**Location**: `app/Http/Controllers/Api/Internal/NlScheduleParseController.php`

**Endpoints**:

#### `POST /internal/api/schedule/parse`
- Request validation:
  - `input_text`: required, string, max:200
  - `timezone`: required, string, max:64, timezone
- Calls `NaturalLanguageScheduleParser::parse()`
- Returns synchronous response (completed) or async response (queued) per API contract
- When LLM fallback is needed, checks rate limits before dispatching
- Auth: `auth:sanctum` middleware

#### `GET /internal/api/schedule/parse/{parse_attempt_id}`
- Loads `NlParseAttempt` by `parse_attempt_id` where `user_id` matches authenticated user
- Returns current status + result/error per polling contract
- Auth: `auth:sanctum` middleware

### 8.2 Create `ParseScheduleRequest` form request
**Location**: `app/Http/Requests/NaturalLanguage/ParseScheduleRequest.php`
- Rules: `input_text` required|string|max:200, `timezone` required|string|max:64|timezone

### 8.3 Register routes
Add to `routes/api.php` inside the authenticated group:
```php
Route::post('/internal/schedule/parse', [NlScheduleParseController::class, 'parse'])->middleware('throttle:agent-mutations');
Route::get('/internal/schedule/parse/{parseAttemptId}', [NlScheduleParseController::class, 'show']);
```

---

## 9. Scheduler Extension — Active Hours Evaluation

### 9.1 Modify `DispatchDueService::collectDueWindows()`
After the existing cron-due check passes (line ~193-197 in current code), add active-hours evaluation:

```php
// After: if (! $cron->isDue($zoned)) { continue; }
// After: if (! $this->matchesCronMinuteAndHour(...)) { continue; }

// NEW: Active-hours window check
if ($job->active_hours_config !== null) {
    $activeHours = ActiveHoursConfig::fromArray($job->active_hours_config);
    if (! $activeHours->isWithinWindow($zoned)) {
        $this->logActiveHoursSkip($job, $zoned, $activeHours);
        continue;
    }
}
```

### 9.2 Add `logActiveHoursSkip()` method to `DispatchDueService`
Log structured skip event:
```php
private function logActiveHoursSkip(AgentJob $job, CarbonImmutable $currentTime, ActiveHoursConfig $activeHours): void
{
    Log::info('Job dispatch skipped: outside active hours', [
        'job_id' => $job->id,
        'skip_reason' => 'outside_active_hours',
        'current_time' => $currentTime->toIso8601String(),
        'window_start' => $activeHours->start,
        'window_end' => $activeHours->end,
        'window_days' => $activeHours->days,
    ]);
}
```
- No user-visible error records created
- No changes to job state
- Purely informational structured log

### 9.3 Evaluation order verification
Confirm the dispatch evaluation order is:
1. Cron due check (existing, unchanged)
2. Active-hours window check (new, only if `active_hours_config IS NOT NULL`)
3. Existing dispatch logic: deduplication, cooldown, overlap prevention (unchanged)

### 9.4 Null guard
Jobs with `active_hours_config = null` skip all active-hours logic entirely. The null check is the first guard before any deserialization or evaluation occurs.

---

## 10. Job Create/Update API Extension

### 10.1 Modify `StoreAgentJobRequest` and `UpdateAgentJobRequest`
Add `active_hours_config` to validation rules:
```php
'active_hours_config' => ['nullable', 'array'],
'active_hours_config.start' => ['required_with:active_hours_config', 'string', 'regex:/^\d{2}:\d{2}$/'],
'active_hours_config.end' => ['required_with:active_hours_config', 'string', 'regex:/^\d{2}:\d{2}$/'],
'active_hours_config.days' => ['required_with:active_hours_config', 'array'],
'active_hours_config.days.*' => ['integer', 'between:1,7'],
```

### 10.2 Modify `AgentJobController::store()` and `update()`
Add `active_hours_config` to the create/fill arrays:
```php
'active_hours_config' => $validated['active_hours_config'] ?? null,
```

### 10.3 Modify `AgentJobController::transformJob()`
Include `active_hours_config` in the response payload:
```php
'active_hours_config' => $job->active_hours_config,
```

Also modify `RunPreviewGenerator` integration: when `active_hours_config` is present, pass it to the preview generator so next-run previews respect the active window.

---

## 11. Frontend — JobForm.vue Extension

### 11.1 Add NL schedule mode
Extend the `schedule` reactive object:
```javascript
const schedule = reactive({
    mode: 'basic',    // existing: 'basic' | 'advanced' → now also 'natural_language'
    // ... existing fields ...
    nlInput: '',       // NL text input (max 200 chars)
    nlParsing: false,  // loading state
    nlResult: null,    // parsed result from API
    nlError: null,     // error message
    nlParseAttemptId: null, // for async polling
    nlConfirmRequired: false, // show confirmation dialog
});
```

### 11.2 Add NL mode radio button
In the schedule mode selector, add a third option:
```html
<label class="inline-flex items-center gap-2">
    <input v-model="schedule.mode" type="radio" value="natural_language" class="border-gray-300" />
    Natural Language
</label>
```

### 11.3 Add NL input UI section
When `schedule.mode === 'natural_language'`:
- Text input field with `maxlength="200"` and character counter
- "Parse Schedule" button that calls `POST /internal/api/schedule/parse`
- Loading spinner during parsing
- Result display area showing:
  - Human-readable explanation
  - Generated cron expression (readonly)
  - Confidence indicator (green ≥75%, yellow 50-74%, red <50%)
  - Parser path indicator (rule_based / llm_fallback)
  - Next 5 run previews (table with local + UTC columns)
  - Active hours display (if present)
- Timezone dropdown (pre-filled from account default or form.timezone)

### 11.4 Confirmation dialog component
When `nlResult.ambiguous === true`:
- Modal overlay showing:
  - "This interpretation may not be exact. Please confirm:"
  - Parsed cron expression
  - Human-readable explanation
  - Next 5 run previews
  - Active hours (if present)
  - "Confirm" button → accepts result, sets `form.cron_expression` and `form.active_hours_config`
  - "Cancel" button → dismisses, user can re-enter
  - "Switch to Basic/Advanced" link → changes schedule mode

### 11.5 Async polling logic
When `POST` returns `status: "queued"`:
- Start polling `GET /internal/api/schedule/parse/{parse_attempt_id}` every 2 seconds
- Max 20 polls (40s total)
- Show "Analyzing with AI..." spinner
- On `completed`: display result, check confidence threshold
- On `failed`: show warning banner with rule-based best-effort (if available), offer mode switch
- Listen for `nl-parse.completed` websocket event as alternative to polling (use whichever resolves first)

### 11.6 LLM degradation UX
When LLM is unavailable or rate-limited:
- Show warning banner: "AI analysis unavailable. Showing best-effort interpretation."
- Display rule-based result even if below threshold
- Show "Confirm anyway" and "Switch to Basic/Advanced" buttons

### 11.7 Apply NL result to form
On confirmation (auto or manual):
- Set `form.cron_expression` from `nlResult.cron_expression`
- Set `form.timezone` from `nlResult.timezone`
- Set `form.active_hours_config` from `nlResult.active_hours` (mapped to DB schema)
- Record confirmation telemetry via API call

### 11.8 Form submission
Modify `submit()` function to include `active_hours_config` in payload when present:
```javascript
if (form.active_hours_config) {
    payload.active_hours_config = form.active_hours_config;
}
```

### 11.9 Hydration
Modify `hydrate()` function:
- If job has `active_hours_config`, populate NL-related display state
- When switching back from NL mode to basic/advanced, clear NL state but preserve `cron_expression`

---

## 12. Testing

### 12.1 Unit Tests

#### `tests/Unit/RuleBasedScheduleParserTest.php`
- `every 30 minutes` → `*/30 * * * *`, confidence 1.0
- `every 2 hours` → `0 */2 * * *`, confidence 1.0
- `hourly` → `0 * * * *`, confidence 1.0
- `daily at 9am` → `0 9 * * *`, confidence 1.0
- `daily at 9:30am` → `30 9 * * *`, confidence 1.0
- `daily at 17:00` → `0 17 * * *`, confidence 1.0
- `weekdays at 9am` → `0 9 * * 1-5`, confidence 1.0
- `weekends at 10am` → `0 10 * * 0,6`, confidence 1.0
- `every Monday at 9am` → `0 9 * * 1`, confidence 1.0
- `every Friday at 5pm` → `0 17 * * 5`, confidence 1.0
- `every day between 9am and 5pm` → `0 9-17 * * *`, confidence 1.0, active_hours present
- `twice a day` → `0 9,17 * * *`, confidence 0.60, ambiguous=true
- `gibberish text` → confidence 0.0 (no match)
- `every 30 seconds` → rejected, suggest "every 1 minute"
- Input exceeding 200 chars → validation error
- `at 9am EST` → timezone override to `America/New_York`

#### `tests/Unit/ActiveHoursConfigTest.php`
- ISO day indexing: 1=Mon..7=Sun validated
- `isWithinWindow()` returns true for time within start-end on valid day
- `isWithinWindow()` returns false for time outside window
- `isWithinWindow()` returns false for valid time on excluded day
- Start inclusive, end exclusive boundary behavior
- `fromArray()` / `toArray()` roundtrip

#### `tests/Unit/RunPreviewGeneratorTest.php`
- Generates 5 runs for simple daily cron
- Generates runs respecting timezone
- Generates runs filtered by active-hours window
- Returns both local and UTC timestamps

#### `tests/Unit/NaturalLanguageScheduleParserTest.php`
- Synchronous path for high-confidence rule-based result
- Queued path for low-confidence result when LLM enabled
- Error path when LLM disabled and no pattern match
- Idempotency: duplicate call within 60s returns same parse_attempt_id
- Cron validation: rejects if rule-based parser produces invalid cron

### 12.2 Feature/Integration Tests

#### `tests/Feature/NlScheduleParseTest.php`
- POST with valid NL input returns completed response with cron
- POST with ambiguous input returns queued response with parse_attempt_id
- POST with input > 200 chars returns 422
- POST with invalid timezone returns 422
- GET polling endpoint returns correct status transitions
- Idempotent POST within 60s returns existing parse_attempt_id
- LLM rate limit exceeded returns 429 with Retry-After
- Rule-based parse bypasses rate limiting

#### `tests/Feature/AgentJobActiveHoursTest.php`
- Create job with `active_hours_config` → persisted correctly
- Update job with `active_hours_config` → updated correctly
- Create job without `active_hours_config` → null, backward compatible
- Validation: days array values must be 1-7
- Validation: start/end must be HH:MM format

#### `tests/Feature/DispatchDueServiceActiveHoursTest.php`
- Job with `active_hours_config = null` dispatches as before (backward compatibility)
- Job with active-hours window dispatches when within window
- Job with active-hours window skipped when outside window (wrong time)
- Job with active-hours window skipped when outside window (wrong day)
- Skip event logged with structured metadata
- Evaluation order: cron check → active-hours → dispatch/skip

#### `tests/Feature/NlParseTelemetryTest.php`
- Parse attempt recorded in `nl_parse_attempts` table with full input
- Application log contains truncated input (80 chars) + SHA-256 hash
- Full input NOT present in application logs
- Confirmation recorded via `recordConfirmation()`

#### `tests/Feature/PurgeStaleNlParseAttemptsTest.php`
- Rows older than 90 days are deleted
- Rows newer than 90 days are preserved
- Custom retention period via `--days` option

#### `tests/Feature/ProcessNlParseJobTest.php`
- Successful LLM parse updates NlParseAttempt to completed
- LLM timeout (30s) transitions to failed, preserves rule-based result
- LLM error transitions to failed with error message
- NlParseCompleted event broadcast on completion and failure
- Invalid cron from LLM is caught by NumericCronExpression validation

### 12.3 Existing Regression Tests
- Verify all existing tests in `AgentJobValidationTest` pass without modification
- Verify `DispatchDueService` existing tests pass (no behavioral change for null active_hours_config)
- Verify Basic and Advanced mode in JobForm.vue continue to function (manual/E2E verification)

---

## 13. Implementation Sequence (Dependency Order)

### Phase A — Foundation (no dependencies)
1. **1.1** — Migration: `active_hours_config` column on `agent_jobs`
2. **1.2** — Migration: `nl_parse_attempts` table
3. **1.3** — Update `AgentJob` model casts
4. **1.4** — Create `NlParseAttempt` model
5. **2.1** — Add NL parse config to `config/agent.php`
6. **3.2** — Create `ParseResult` value object
7. **3.3** — Create `ActiveHoursConfig` value object

### Phase B — Core Services (depends on Phase A)
8. **3.4** — Create `RunPreviewGenerator`
9. **3.1** — Create `RuleBasedScheduleParser`
10. **6.1** — Create `NlParseTelemetryService`
11. **4.2** — Create `ParseAttemptResult` value object

### Phase C — Orchestrator + API (depends on Phase B)
12. **4.1** — Create `NaturalLanguageScheduleParser` orchestrator
13. **8.2** — Create `ParseScheduleRequest` form request
14. **8.1** — Create `NlScheduleParseController`
15. **8.3** — Register routes

### Phase D — LLM Fallback (depends on Phase C)
16. **5.1** — Create `NlParseLlmService`
17. **5.2** — Create `ProcessNlParseJob`
18. **5.3** — Create `NlParseCompleted` event
19. **7.1** — Implement rate limiting

### Phase E — Scheduler Extension (depends on Phase A)
20. **9.1-9.4** — Modify `DispatchDueService` for active-hours evaluation

### Phase F — Job API Extension (depends on Phase A)
21. **10.1** — Update `StoreAgentJobRequest` / `UpdateAgentJobRequest`
22. **10.2-10.3** — Update `AgentJobController` store/update/transform

### Phase G — Telemetry Cleanup (depends on Phase B)
23. **6.2** — Create `PurgeStaleNlParseAttemptsCommand`

### Phase H — Frontend (depends on Phase C, D, F)
24. **11.1-11.9** — JobForm.vue NL mode, confirmation dialog, polling, hydration

### Phase I — Testing (depends on all phases)
25. **12.1** — Unit tests
26. **12.2** — Feature/integration tests
27. **12.3** — Regression verification

---

## 14. File Inventory

### New Files
| # | Path | Purpose |
|---|---|---|
| 1 | `database/migrations/YYYY_add_active_hours_config_to_agent_jobs.php` | Add nullable JSON column |
| 2 | `database/migrations/YYYY_create_nl_parse_attempts_table.php` | Telemetry/async tracking table |
| 3 | `app/Models/NlParseAttempt.php` | Eloquent model for parse attempts |
| 4 | `app/Support/NaturalLanguage/ParseResult.php` | Value object for parse output |
| 5 | `app/Support/NaturalLanguage/ActiveHoursConfig.php` | Value object for active hours |
| 6 | `app/Support/NaturalLanguage/ParseAttemptResult.php` | Value object for API response |
| 7 | `app/Support/NaturalLanguage/RuleBasedScheduleParser.php` | Deterministic NL parser |
| 8 | `app/Support/NaturalLanguage/RunPreviewGenerator.php` | Next-N run preview service |
| 9 | `app/Support/NaturalLanguage/NaturalLanguageScheduleParser.php` | Orchestrator service |
| 10 | `app/Support/NaturalLanguage/NlParseLlmService.php` | LLM fallback service |
| 11 | `app/Support/NaturalLanguage/NlParseTelemetryService.php` | Telemetry dual-write service |
| 12 | `app/Jobs/ProcessNlParseJob.php` | Async LLM queue job |
| 13 | `app/Events/NlParseCompleted.php` | Broadcast event for async results |
| 14 | `app/Http/Controllers/Api/Internal/NlScheduleParseController.php` | Parse API controller |
| 15 | `app/Http/Requests/NaturalLanguage/ParseScheduleRequest.php` | Form request validation |
| 16 | `app/Console/Commands/PurgeStaleNlParseAttemptsCommand.php` | 90-day retention purge |
| 17 | `tests/Unit/RuleBasedScheduleParserTest.php` | Rule-based parser unit tests |
| 18 | `tests/Unit/ActiveHoursConfigTest.php` | Active hours value object tests |
| 19 | `tests/Unit/RunPreviewGeneratorTest.php` | Run preview unit tests |
| 20 | `tests/Unit/NaturalLanguageScheduleParserTest.php` | Orchestrator unit tests |
| 21 | `tests/Feature/NlScheduleParseTest.php` | Parse API integration tests |
| 22 | `tests/Feature/AgentJobActiveHoursTest.php` | Job CRUD + active hours tests |
| 23 | `tests/Feature/DispatchDueServiceActiveHoursTest.php` | Scheduler active hours tests |
| 24 | `tests/Feature/NlParseTelemetryTest.php` | Telemetry write tests |
| 25 | `tests/Feature/PurgeStaleNlParseAttemptsTest.php` | Purge command tests |
| 26 | `tests/Feature/ProcessNlParseJobTest.php` | Async LLM job tests |

### Modified Files
| # | Path | Changes |
|---|---|---|
| 1 | `app/Models/AgentJob.php` | Add `active_hours_config` to `casts()` |
| 2 | `config/agent.php` | Add `nl_parse` config block |
| 3 | `routes/api.php` | Add internal parse endpoints |
| 4 | `app/Support/Agent/DispatchDueService.php` | Add active-hours evaluation in `collectDueWindows()` |
| 5 | `app/Http/Requests/Agent/StoreAgentJobRequest.php` | Add `active_hours_config` validation rules |
| 6 | `app/Http/Requests/Agent/UpdateAgentJobRequest.php` | Add `active_hours_config` validation rules |
| 7 | `app/Http/Controllers/Api/V1/AgentJobController.php` | Add `active_hours_config` to store/update/transform |
| 8 | `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue` | Add NL mode, confirmation dialog, polling |
| 9 | `routes/channels.php` | Already has user channel auth (no change needed if using `App.Models.User.{id}`) |

---

## 15. Key Technical Decisions Embedded in Plan

1. **Adapter reuse strategy**: `NlParseLlmService` uses `AdapterFactory::make()` to resolve the LLM provider, reusing the executable resolution and environment building from existing adapters. It does NOT extend `InterrogationRunnerAdapter` (which is session-coupled) but uses the adapter's executable path and builds a standalone command.

2. **Rate limiting implementation**: Conditional rate limiting is enforced in the controller/service layer (not pure middleware) because the decision to invoke LLM depends on the rule-based parse result. The controller first runs rule-based parsing, then checks rate limits only if LLM fallback is needed.

3. **Cron day-of-week mapping**: The existing `JobForm.vue` uses 0=Sunday..6=Saturday for cron (standard cron convention). The `active_hours_config.days` uses ISO 1=Mon..7=Sun. The `RuleBasedScheduleParser` generates cron expressions using standard cron DOW (0-6) while simultaneously populating `active_hours.days` using ISO indexing (1-7). These are independent fields with different conventions, both correct for their context.

4. **Websocket channel**: Uses the existing `App.Models.User.{id}` private channel (already authorized in `channels.php`) rather than creating a new channel. This avoids new channel authorization code.

5. **Input fingerprint for idempotency**: SHA-256 hash of `"{user_id}|{timezone}|{input_text}"` stored as `input_fingerprint` on `NlParseAttempt`. Queried with a time window to implement 60-second idempotency without race conditions (uses `firstOrCreate` pattern with the fingerprint as lookup key).

## Sections

- Database Migrations
- Configuration
- Domain Services — Rule-Based Parser
- Domain Services — NaturalLanguageScheduleParser Orchestrator
- LLM Fallback Path
- Telemetry Service
- Rate Limiting
- API Controller + Routes
- Scheduler Extension — Active Hours Evaluation
- Job Create/Update API Extension
- Frontend — JobForm.vue Extension
- Testing
- Implementation Sequence (Dependency Order)
- File Inventory
- Key Technical Decisions Embedded in Plan


## Risks

- LLM fallback adapter reuse: the existing InterrogationRunnerAdapter is tightly coupled to InterrogationSession. NlParseLlmService must work around this coupling by using the adapter's executable path directly rather than the full interface, which may require refactoring if the adapter internals change.
- Cron day-of-week convention mismatch: standard cron uses 0=Sunday..6=Saturday while active_hours_config uses ISO 1=Monday..7=Sunday. Any confusion between these two conventions in the parser or scheduler could produce incorrect schedules. Thorough unit testing of day mapping is critical.
- LLM response quality: the LLM may return non-numeric cron expressions (e.g., using MON, TUE) or invalid cron syntax. The NumericCronExpression validation gate mitigates this, but failed LLM parses will degrade to rule-based best-effort which may be unsatisfying for complex inputs.
- Active-hours evaluation in DispatchDueService hot path: adding a JSON deserialization and window check to every due-check iteration could impact scheduler tick performance if there are many jobs. Mitigation: the null check short-circuits immediately for jobs without active_hours_config.
- Frontend complexity: the NL mode adds significant state management (async polling, websocket listening, confirmation dialogs, degradation states) to an already complex JobForm.vue component. Consider extracting NL-specific logic into a composable to manage complexity.
- Idempotency window race condition: under high concurrency, two identical requests arriving simultaneously could both pass the fingerprint check before either creates a record. Using database-level unique constraint on input_fingerprint + a short time-bucketing approach or firstOrCreate with DB-level atomicity mitigates this.
- Timezone abbreviation ambiguity: abbreviations like CST could mean Central Standard Time (US) or China Standard Time. The parser maps to US timezones by default, which may be incorrect for non-US users. v1 is English-only which partially mitigates this, but a future consideration.
- 90-day purge job must handle large table volumes gracefully: chunked deletion is specified but if the purge job falls behind (e.g., never runs), the first execution could need to delete millions of rows. Adding a max-rows-per-run safety cap would be prudent.


## Assumptions

- The existing App.Models.User.{id} private channel in channels.php is sufficient for broadcasting NlParseCompleted events — no new channel authorization is needed.
- The InterrogationSetting model's getForUser() can be used to resolve the preferred LLM runner type for NL parsing, or a new setting key (nl_parse.runner_type) can be added with a fallback to the interrogation runner type.
- The existing AdapterFactory and ClaudeAdapter/CodexAdapter expose sufficient executable path information to build standalone CLI commands without needing a full InterrogationSession object.
- The default queue (not the 'agent' queue) is appropriate for ProcessNlParseJob to avoid competing with agent run jobs for queue workers.
- The platform's existing Redis/cache infrastructure supports the rate limiting implementation (Laravel RateLimiter using cache store).
- The existing broadcasting infrastructure (Pusher/Reverb/etc.) is configured and operational for the application, as evidenced by the existing InterrogationSessionUpdated event.
- The database supports JSON column type (MySQL 5.7+ / PostgreSQL 9.2+ / SQLite 3.38+) for the active_hours_config and result_payload columns.
- JobForm.vue already has access to axios or an HTTP client for making API calls to the parse endpoints, consistent with the existing Inertia.js / Vue 3 setup.
- The application uses Laravel Sanctum for API authentication on all internal endpoints, consistent with existing route middleware.
- No public API consumers currently depend on the agent_jobs schema, so adding a nullable column is safe without API versioning.

