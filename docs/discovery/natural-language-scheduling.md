# Requirements Discovery Summary

Session: 5

## F-011: Natural Language Job Builder — Requirements Summary

### Overview
Add a Natural Language scheduling mode to the Agent Job Builder so users can describe schedules in plain English (e.g., "check inbox every 30 minutes", "weekdays at 9am", "every day between 9am and 5pm") and have the system convert this into valid cron + timezone configuration. Cron remains the canonical runtime format — no scheduler rewrite required.

### Architecture Decisions

**Hybrid Parser Strategy (F011-001)**
- **Rule-based parser** handles common, deterministic patterns with high confidence.
- **LLM fallback** handles ambiguous or complex inputs that the rule-based parser cannot resolve at ≥75% confidence.
- Rule-based path is synchronous (instant response). LLM fallback path is **asynchronous** with polling or websocket push for result delivery (F011-006).

**LLM Integration (F011-003, F011-017)**
- LLM provider is **configurable via environment/config** (not hardcoded to a single vendor).
- Reuse the existing **InterrogationRunnerAdapter / AdapterFactory / ClaudeAdapter / CodexAdapter** pattern plus **SystemPromptResolver** and per-user **InterrogationSetting**. Do not create a new ad-hoc LLM client.

**Confidence Threshold (F011-002)**
- **≥75% confidence**: auto-accepted (no confirmation dialog).
- **<75% confidence**: requires explicit user confirmation via a simple confirmation dialog showing parsed cron, human-readable explanation, and next 5 run previews (F011-019).

**Active-Hours / Time-Window Scheduling (F011-004, F011-OQ-002)**
- Use **cron range syntax** (e.g., `0 9-17 * * *`) when the time window is expressible in standard 5-field cron.
- For complex cases that cannot be represented in a single cron expression, store as a **JSON object in a new nullable `active_hours_config` column** on the `agent_jobs` table.
- The scheduler (`DispatchDueService`) deserializes and checks this window at dispatch time.

**Canonical Active-Hours Day Indexing (Amendment §1)**
- **ISO-8601 day numbering is canonical**: `1=Monday, 2=Tuesday, ..., 7=Sunday`.
- This indexing is used consistently across all layers:
  - **Parser output contract**: `active_hours.days` array uses `1-7` (1=Mon..7=Sun).
  - **DB storage contract**: `active_hours_config.days` JSON array uses `1-7`.
  - **Scheduler evaluation mapping**: `DispatchDueService` converts ISO day numbers to PHP `Carbon::dayOfWeekIso` (already 1=Mon..7=Sun) for comparison. No translation needed.
  - **UI display mapping**: Frontend maps `1-7` to localized day names for display (`1→Monday`, ..., `7→Sunday`).
- Full `active_hours_config` JSON schema:
  ```
  {
    "start": "09:00",       // HH:MM, 24-hour format
    "end": "17:00",         // HH:MM, 24-hour format
    "days": [1, 2, 3, 4, 5] // ISO day numbers, 1=Mon..7=Sun
  }
  ```

**Timezone Handling (F011-007)**
- Default to the **account-level timezone** (IANA format).
- Allow override via **NL input** (e.g., "at 9am EST") or a **timezone dropdown** in the UI.

**Run Preview (F011-014)**
- **Server-side generation** of next 5 run timestamps (local + UTC), returned as part of the parse API response.
- Reuse **CronExpression + timezone handling** from `AgentJobController` and `DispatchDueService`, extended to produce multi-run previews.

**API Exposure (F011-009)**
- **Internal-only service** for v1, consumed exclusively by the Job Builder UI.
- Public API wrapper planned as a fast follow-up.

**Cron Validation (F011-013, F011-017)**
- All NL-generated output must pass through existing **NumericCronExpression** validation (5-part numeric cron: numbers, `*`, ranges, lists, steps).
- Platform minimum interval: 1 minute (`* * * * *`). No explicit maximum cap; any valid 5-field cron is accepted.

### Async LLM Fallback API Contract (Amendment §2)

**Initial Parse Endpoint**: `POST /internal/api/schedule/parse`

Request:
```
{
  input_text: string,       // max 200 chars
  timezone: string           // IANA timezone (from account default or user override)
}
```

**Synchronous response (rule-based success, confidence ≥75%)**:
```
{
  status: "completed",
  result: { <full parse response payload — see below> }
}
```

**Synchronous response (rule-based insufficient, LLM fallback queued)**:
```
{
  status: "queued",
  parse_attempt_id: string,  // UUID, used for polling and idempotency
  rule_based_result: { <best-effort parse response payload> } | null
}
```

**Polling Endpoint**: `GET /internal/api/schedule/parse/{parse_attempt_id}`

Response shapes by status:
```
// Queued — LLM request not yet started
{ status: "queued", parse_attempt_id: string }

// Running — LLM request in flight
{ status: "running", parse_attempt_id: string }

// Done — LLM result available
{ status: "completed", parse_attempt_id: string, result: { <full parse response payload> } }

// Failed — LLM call failed (timeout, error, rate limit)
{ status: "failed", parse_attempt_id: string, error: string, rule_based_result: { <best-effort> } | null }
```

**Websocket Event** (alternative to polling):
- Channel: `private-user.{user_id}`
- Event name: `NlParseCompleted`
- Payload: `{ parse_attempt_id: string, status: "completed" | "failed", result: { ... } | null, error: string | null, rule_based_result: { ... } | null }`

**Timeout Behavior**:
- LLM fallback timeout: **30 seconds** (configurable via `NL_PARSE_LLM_TIMEOUT_SECONDS`).
- If timeout is reached, status transitions to `failed`. Client receives the `rule_based_result` (best-effort) with a warning banner.
- Client polls every **2 seconds**, max **20 polls** (40s total including initial delay).

**Idempotency**:
- Repeated `POST` submissions with identical `input_text + timezone + user_id` within a **60-second window** return the existing `parse_attempt_id` and current status rather than creating a new LLM request.

### UI Design Decisions

**Placement (F011-005)**
- New **"Natural Language" tab/toggle** alongside existing Basic and Advanced modes in `JobForm.vue`.
- Extend `JobForm.vue` by adding an NL mode (reuse `buildCronFromBasicSchedule` / `parseCronIntoBasicSchedule` patterns). Do not create a separate form component.

**Input Constraints (F011-OQ-004)**
- NL input field enforces a **maximum of 200 characters**.

**Confirmation Flow (F011-019)**
- v1: **Simple confirmation dialog** — display parsed cron expression, human-readable explanation, next 5 run previews, and confidence indicator. User clicks Confirm or Cancel.
- Follow-up: Inline edit refinement (editable fields for time, days, interval).

**Frequency Guardrails (F011-010)**
- **Reject** NL input that produces a cron expression below platform minimum interval or otherwise deemed harmful.
- **Suggest the nearest valid schedule** as an alternative. Block save until user accepts a valid schedule.

**LLM Degradation UX (F011-016)**
- When LLM fallback is unavailable (outage, rate limit, timeout): show the **rule-based parser's best-effort result** even if below 75% confidence, accompanied by a warning banner.
- Let user **confirm the low-confidence result** or **switch to Basic/Advanced mode**.

### Domain Service Design

**New Service: NaturalLanguageScheduleParser** (name indicative)
- Implemented as a new domain service, not inline controller/component logic (F011-017).
- Orchestrates: (1) rule-based parse attempt → (2) confidence check → (3) LLM fallback if needed → (4) cron validation via `NumericCronExpression` → (5) run preview generation.

**Full Parse Response Payload:**
```
{
  cron_expression: string,       // 5-part numeric cron
  timezone: string,              // IANA timezone
  explanation: string,           // Human-readable interpretation
  active_hours: {                // Present only if time window detected
    start_time: string,          // HH:MM, 24-hour format
    end_time: string,            // HH:MM, 24-hour format
    days: int[]                  // ISO day numbers (1=Mon..7=Sun)
  } | null,
  next_runs: [                   // Next 5 run timestamps
    { local: ISO-8601, utc: ISO-8601 }
  ],
  confidence: float,             // 0.0–1.0
  parser_path: "rule_based" | "llm_fallback",
  ambiguous: boolean             // true if confidence < 0.75
}
```

### Rule-Based Parser Patterns (F011-018, F011-OQ-003)
The following NL patterns must be resolved deterministically without LLM in v1:
1. `every X minutes` / `every X hours`
2. `daily at TIME`
3. `weekdays at TIME` / `weekends at TIME`
4. `every Monday/Tuesday/.../Sunday at TIME`
5. `every day between TIME and TIME` (active-hours window)
6. `hourly`
7. `twice a day` — treated as **ambiguous** (confidence < 75%), defaults to 9am/5pm as suggested interpretation, requires user confirmation

### Rate Limiting (F011-015, F011-OQ-001, Amendment §3)

**LLM fallback path only** — rate limited with configurable defaults:
| Config Key | Env Variable | Default | Scope |
|---|---|---|---|
| `nl_parse.llm.rate_limit.per_minute` | `NL_PARSE_LLM_RATE_LIMIT_PER_MINUTE` | `10` | Per user |
| `nl_parse.llm.rate_limit.per_hour` | `NL_PARSE_LLM_RATE_LIMIT_PER_HOUR` | `60` | Per user |

- Enforced in middleware on the `POST /internal/api/schedule/parse` endpoint, applied **only when the request triggers the LLM fallback path** (rule-based attempts that resolve at ≥75% confidence bypass rate limiting entirely).
- When rate limit is exceeded, respond with HTTP 429 and include `Retry-After` header. Client shows warning banner and offers rule-based best-effort or mode switch.
- **Rule-based parsing remains unlimited** — no rate limiting applied.

### Telemetry / Audit (F011-011, F011-OQ-005, Amendment §4)

**Hybrid storage approach:**
- **Dedicated database table** (`nl_parse_attempts`) for queryable analytics and correction-rate dashboards.
- **Application logs** (Laravel Log) for real-time observability.

**Fields captured per parse attempt** (both DB and log):
- `input_text` — raw user input
- `parser_path` — `rule_based` or `llm_fallback`
- `confidence` — float 0.0–1.0
- `cron_result` — resulting 5-part cron expression (or null if parse failed)
- `user_confirmed` — boolean, whether user accepted or corrected the result
- `user_id` — foreign key to users table
- `created_at` / `updated_at` — timestamps

**Data Governance (Amendment §4):**

| Concern | Policy |
|---|---|
| **Retention period** | 90 days for `nl_parse_attempts` DB table. Rows older than 90 days are purged by a scheduled cleanup job. Application log retention follows existing platform log rotation policy. |
| **Input text storage** | Full `input_text` (up to 200 chars) is stored in the `nl_parse_attempts` DB table for analytics and parser improvement. |
| **Log redaction** | Application logs store a **truncated input** (first 80 characters) plus a SHA-256 hash of the full input for correlation. Full text is NOT written to application logs to limit PII surface in log aggregators. |
| **Access controls** | `nl_parse_attempts` table is queryable only by users with admin/analytics role. No public API exposes this data. Dashboard access is gated by existing RBAC. |

### Database Changes

**1. New nullable column on `agent_jobs`**: `active_hours_config` (JSON)
- Schema: `{"start": "09:00", "end": "17:00", "days": [1, 2, 3, 4, 5]}` (ISO day indexing, 1=Mon..7=Sun).
- Nullable — existing jobs with `active_hours_config = null` are completely unaffected.
- Migration is additive only (nullable JSON column add). No data rewrite, no backfill, no default value. Zero impact on existing rows.

**2. New table `nl_parse_attempts`**:
- Columns: `id` (UUID PK), `user_id` (FK), `input_text` (string 200), `parser_path` (enum: rule_based, llm_fallback), `confidence` (float), `cron_result` (string nullable), `user_confirmed` (boolean nullable), `parse_attempt_id` (UUID, for async tracking), `created_at`, `updated_at`.
- 90-day retention, purged by scheduled artisan command.

### Backward Compatibility + Scheduler Evaluation Order (Amendment §5)

**Existing Job Safety:**
- Jobs with `active_hours_config = null` behave **exactly as they do today**. The null check is the first guard — if null, the scheduler skips all active-hours logic and proceeds directly with the existing cron-due dispatch path.

**Dispatch Evaluation Order in `DispatchDueService`:**
1. **Cron due check**: Evaluate `cron_expression` against current time + timezone. If NOT due → skip (no change from current behavior).
2. **Active-hours window check** (new, only if `active_hours_config IS NOT NULL`):
   a. Deserialize JSON config.
   b. Check if current day-of-week (ISO: `Carbon::dayOfWeekIso`, 1=Mon..7=Sun) is in `days` array.
   c. Check if current time is within `start`–`end` window (inclusive start, exclusive end).
   d. If outside window → **skip dispatch** with explicit skip reason.
3. **Existing dispatch logic**: deduplication, cooldown, overlap prevention, DST-safe behavior — all unchanged.

**Skip Reason Metadata:**
- When a job is skipped due to active-hours window, log a structured skip event: `{ job_id, skip_reason: "outside_active_hours", current_time, window_start, window_end, window_days }`.
- This metadata is logged but does NOT change the job's state or create user-visible error records.

**Migration Safety Notes:**
- `active_hours_config` column is nullable JSON with no default — `ALTER TABLE ADD COLUMN` only, no data rewrite.
- No existing column is modified or removed.
- Rollback: drop the column. No data loss to existing fields.
- `nl_parse_attempts` is a new table — no impact on existing schema.

### Scope Boundaries

**In Scope (v1):**
- NL parsing as standalone fresh schedule only (F011-012) — no context-aware editing of existing cron.
- English language only (F011-008).
- Internal service only (F011-009).
- Simple confirmation dialog (F011-019).
- 200-character max input length (F011-OQ-004).
- Async LLM fallback with polling + websocket, idempotency, and 30s timeout.
- 90-day telemetry retention with log redaction policy.

**Out of Scope (v1) / Follow-ups:**
- Context-aware schedule editing ("change to hourly instead") — follow-up (F011-012).
- Inline edit refinement in confirmation UI — follow-up (F011-019).
- Public API wrapper — fast follow-up (F011-009).
- Multi-language/locale support — future (F011-008).
- Event-driven triggers/webhooks (covered by F-054/F-007).
- Replacing cron dispatcher internals.
- Full conversational agent interface.

### Testing Scope (F011-020)
Full v1 test coverage including:
1. Successful NL parse (rule-based path) → correct cron output
2. Ambiguous NL parse (e.g., "twice a day") → confirmation dialog triggered with 9am/5pm suggestion
3. Invalid/unparseable NL input → appropriate error
4. Input exceeding 200 characters → rejected at validation layer
5. Save/update roundtrip → cron persisted correctly via existing create/update APIs
6. LLM fallback timeout handling (30s) → graceful degradation to rule-based result
7. LLM rate limit exceeded (HTTP 429) → graceful degradation with warning
8. LLM provider unavailable → graceful degradation with warning
9. Timezone override via NL input (e.g., "at 9am EST") → correct IANA timezone in output
10. Active-hours cron range generation (e.g., "between 9am and 5pm" → `0 9-17 * * *`)
11. Complex active-hours stored as JSON in `active_hours_config` column with ISO day indexing (1-7)
12. Existing Basic/Advanced mode regression → unchanged behavior confirmed
13. Telemetry written to both `nl_parse_attempts` table and application logs (with log redaction verified)
14. Existing jobs with `active_hours_config = null` dispatch unchanged (backward compatibility)
15. Scheduler evaluation order: cron check → active-hours check → dispatch/skip with reason metadata
16. Async polling lifecycle: queued → running → completed/failed status transitions
17. Idempotency: duplicate POST within 60s returns existing `parse_attempt_id`
18. `nl_parse_attempts` table purge job correctly removes rows older than 90 days

## Goals

- Implement a hybrid NL-to-cron translation service (NaturalLanguageScheduleParser) with rule-based parsing for common patterns and configurable LLM fallback for ambiguous/complex inputs
- Add a 'Natural Language' tab/toggle in JobForm.vue alongside existing Basic and Advanced schedule modes
- Return structured parse responses containing: cron_expression (5-part), IANA timezone, human-readable explanation, active-hours interpretation with ISO day indexing (1=Mon..7=Sun), next 5 run previews (local + UTC), confidence score, parser path indicator, and ambiguity flag
- Implement a ≥75% confidence threshold: auto-accept high-confidence results, require user confirmation dialog for lower-confidence results
- Support active-hours scheduling via cron range syntax (e.g., 0 9-17 * * *) where possible, with JSON metadata fallback in a new nullable active_hours_config column on agent_jobs using ISO day indexing (1=Mon..7=Sun)
- Implement async LLM fallback with parse_attempt_id tracking, polling endpoint (GET /internal/api/schedule/parse/{id}), websocket event (NlParseCompleted), 30s timeout, and 60s idempotency window
- Provide server-side next-5-run preview generation reusing CronExpression and timezone handling from AgentJobController/DispatchDueService
- Reject NL inputs that produce out-of-bounds frequencies and suggest the nearest valid schedule alternative
- Implement graceful degradation when LLM fallback is unavailable: show rule-based best-effort result with a warning, allow user to confirm or switch modes
- Persist final cron_expression and timezone through existing job create/update APIs with no changes to the scheduler runtime
- Extend DispatchDueService with active-hours evaluation: cron due check → active_hours_config window/day check (if present) → dispatch/skip with structured skip reason metadata
- Capture hybrid telemetry: dedicated nl_parse_attempts DB table (90-day retention) plus application logs with truncated+hashed input redaction, gated by admin/analytics RBAC
- Enforce 200-character max input length on NL schedule text field
- Implement configurable LLM rate limits via env/config (defaults: 10/min/user, 60/hour/user) with HTTP 429 + Retry-After on the LLM fallback path only
- Ensure full backward compatibility: existing jobs with active_hours_config = null behave identically to pre-feature behavior


## Constraints

- Must produce valid 5-part numeric cron expressions that pass existing NumericCronExpression validation (numbers, *, ranges, lists, steps)
- Must preserve current scheduler reliability guarantees: deduplication, cooldown, overlap prevention, DST-safe behavior
- Must not degrade or alter existing Basic/Advanced cron input flows in JobForm.vue
- Must reuse existing LLM integration patterns: InterrogationRunnerAdapter, AdapterFactory, ClaudeAdapter/CodexAdapter, SystemPromptResolver, InterrogationSetting — no new ad-hoc LLM client
- Must reuse NumericCronExpression for cron validation and CronExpression + AgentJobController/DispatchDueService patterns for run preview generation
- LLM provider must be configurable via environment/config, not hardcoded to a single vendor
- Rule-based parsing path must be synchronous; LLM fallback path must be asynchronous (polling or websocket)
- LLM fallback rate limits configurable via NL_PARSE_LLM_RATE_LIMIT_PER_MINUTE (default 10) and NL_PARSE_LLM_RATE_LIMIT_PER_HOUR (default 60), both per-user scoped, enforced only when LLM fallback is triggered
- Rule-based parsing path has no rate limiting
- v1 supports English language only
- v1 is internal-only service (no public API endpoint); public wrapper is a planned follow-up
- v1 parses NL input as standalone fresh schedule only — no context-aware editing of existing cron
- v1 uses simple confirmation dialog only — no inline edit refinement
- Platform minimum cron interval is 1 minute (* * * * *); no explicit maximum cap; any valid 5-field cron is accepted
- NL input field enforces a maximum of 200 characters
- Active-hours day indexing uses ISO-8601 canonical representation (1=Mon..7=Sun) across parser output, DB storage, scheduler evaluation, and UI display
- Complex active-hours metadata stored as nullable JSON column (active_hours_config) on agent_jobs table; DispatchDueService deserializes and evaluates at dispatch time after cron-due check
- Existing jobs with active_hours_config = null must behave exactly as before — null check is the first guard, all active-hours logic is bypassed
- Database migration is additive only: nullable JSON column add on agent_jobs, new nl_parse_attempts table. No existing column modification, no data rewrite, no backfill
- Telemetry: full input_text (up to 200 chars) stored in nl_parse_attempts DB table; application logs store truncated input (first 80 chars) + SHA-256 hash only
- nl_parse_attempts table has 90-day retention enforced by a scheduled purge job
- Telemetry data access gated by admin/analytics RBAC role — no public API exposure
- LLM fallback timeout is 30 seconds, configurable via NL_PARSE_LLM_TIMEOUT_SECONDS
- Idempotency: duplicate POST with identical input_text + timezone + user_id within 60 seconds returns existing parse_attempt_id
- When dispatch is skipped due to active-hours window, log structured skip event with job_id, skip_reason, current_time, window metadata — no user-visible error records created


## Acceptance Criteria

- Users can create and update jobs from plain-English schedule descriptions without manually writing cron expressions
- The NL tab/toggle appears alongside existing Basic and Advanced modes in the Job Schedule UI without altering those modes
- Rule-based parser correctly handles all v1 patterns: 'every X minutes/hours', 'daily at TIME', 'weekdays/weekends at TIME', 'every Monday-Sunday at TIME', 'every day between TIME and TIME', 'hourly', 'twice a day'
- Active-hours NL examples (e.g., 'every day between 9am and 5pm') produce correct cron range syntax (e.g., 0 9-17 * * *) and display accurately in previews
- Complex active-hours windows stored in active_hours_config JSON column use ISO day indexing (1=Mon..7=Sun) consistently across parser, DB, scheduler, and UI
- Parse results with ≥75% confidence are auto-accepted; results below 75% trigger a confirmation dialog showing cron, explanation, and next 5 runs
- 'Twice a day' without explicit times is treated as ambiguous, defaults to 9am/5pm suggestion, and requires user confirmation
- Ambiguous or unparseable phrases are blocked from save and display clear error messaging or require explicit user confirmation
- Out-of-bounds frequency inputs (e.g., 'every 1 second') are rejected with a suggested nearest valid schedule alternative
- NL input exceeding 200 characters is rejected at the validation layer
- Server-side next-5-run previews are included in the parse response, showing both local and UTC timestamps
- Account-level default timezone is pre-filled; timezone overrides via NL input (e.g., 'at 9am EST') or dropdown are correctly reflected in the stored IANA timezone
- LLM fallback path is invoked only when rule-based confidence is below threshold; LLM provider is resolved from environment/config via existing adapter pattern
- Async LLM fallback returns parse_attempt_id with status queued/running; polling endpoint and NlParseCompleted websocket event deliver completed/failed results
- Duplicate POST submissions with identical input within 60s return existing parse_attempt_id (idempotency verified)
- LLM fallback times out at 30 seconds and transitions to failed status; client receives rule-based best-effort with warning
- When LLM fallback is unavailable (outage, timeout, rate limit), the UI gracefully degrades to show rule-based best-effort result with a warning banner
- LLM fallback rate limiting enforced at configurable defaults (10/min/user, 60/hour/user via NL_PARSE_LLM_RATE_LIMIT_PER_MINUTE and NL_PARSE_LLM_RATE_LIMIT_PER_HOUR); HTTP 429 with Retry-After header returned when exceeded
- Rule-based parsing path has no rate limiting applied
- Final cron_expression and timezone are persisted through existing job create/update APIs; saved jobs execute on the expected schedule
- Existing Basic and Advanced cron workflows continue to function identically (regression verified)
- Existing jobs with active_hours_config = null dispatch exactly as before with no behavioral change
- Scheduler evaluation follows defined order: (1) cron due check, (2) active_hours_config window/day check if present, (3) dispatch or skip with structured skip reason metadata logged
- Active-hours skip events are logged with job_id, skip_reason='outside_active_hours', current_time, and window metadata — no user-visible error records created
- Telemetry captured for every parse attempt in nl_parse_attempts DB table: full input_text, parser_path, confidence, cron_result, user_confirmed, user_id
- Application logs contain truncated input (first 80 chars) + SHA-256 hash — full input text is NOT written to application logs
- nl_parse_attempts table access is restricted to admin/analytics RBAC roles
- Scheduled purge job removes nl_parse_attempts rows older than 90 days
- Database migration is additive only: nullable active_hours_config column on agent_jobs (no default, no backfill) and new nl_parse_attempts table — rollback is column/table drop with no data loss
- End-to-end tests cover: successful rule-based parse, ambiguous parse with confirmation (including 'twice a day'), invalid/unparseable input, 200-char limit rejection, save/update roundtrip, LLM timeout (30s) degradation, LLM rate limit (HTTP 429) degradation, LLM provider outage degradation, timezone override via NL, active-hours cron range generation, complex active-hours JSON storage with ISO day indexing, existing Basic/Advanced mode regression, backward compatibility for null active_hours_config, scheduler evaluation order verification, async polling lifecycle (queued→running→completed/failed), idempotency for duplicate POST, telemetry dual-write with log redaction, and 90-day purge job

