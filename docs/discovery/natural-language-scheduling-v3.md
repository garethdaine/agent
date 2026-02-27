# Requirements Discovery Summary

Session: 9

# Natural Language Scheduling (Natural Language Job Builder)

## Overview
Add a Natural Language scheduling mode to Agent Job Builder allowing users to describe schedules in plain English. The system produces valid cron + timezone configuration using a hybrid parser (rule-based first, LLM fallback for low confidence), while preserving cron as the canonical runtime format.

## Architecture

### Hybrid Parser Strategy
1. **Rule-based parser** executes first (deterministic, synchronous)
2. **LLM fallback** triggers only when rule-based confidence < 0.75, executed asynchronously with polling/WebSocket status updates
3. **LLM integration**: Reuse existing `InterrogationRunnerAdapter`, `AdapterFactory`, `Claude`, `Codex`, `SystemPromptResolver`, and `InterrogationSetting` infrastructure. For any direct API fallback not covered by CLI integration, implement provider-agnostic strategy interface with configurable default provider/model.

### Parser Output Contract
```json
{
  "cron_expression": "5-part numeric cron",
  "timezone": "IANA timezone string",
  "explanation": "Human-readable description",
  "active_hours": { "start": "HH:MM", "end": "HH:MM", "days": [1,2,3,4,5] } | null,
  "next_runs": [{ "local": "ISO8601", "utc": "ISO8601" }, ...],
  "confidence": 0.0-1.0,
  "parser_path": "rule_based | llm_fallback",
  "ambiguous": boolean
}
```

### Async State Machine
Parse attempts transition through states: `queued` → `running` → `completed` | `failed`
- On `failed` or LLM unavailable: return best-effort `rule_based_result` with degradation warning
- 30-second LLM timeout (configurable)
- 60-second idempotency window (configurable per environment)

### Rule-based Parser v1 Patterns
- `every X minutes` / `every X hours`
- `daily at TIME`
- `weekdays at TIME` / `weekends at TIME`
- `every Monday-Sunday at TIME`
- `every day between TIME and TIME`
- `hourly`
- `twice a day` (ambiguous, suggests 9am/5pm, requires confirmation)
- `every X hours starting at TIME` (populates active_hours_config)
- `every X minutes during business hours` (populates active_hours_config)
- Bare time references without AM/PM (e.g., "at 5") treated as ambiguous, show both AM/PM options

## Database Schema

### Migration: Add `active_hours_config` to `agent_jobs`
```sql
ALTER TABLE agent_jobs ADD COLUMN active_hours_config JSON NULL;
```
Schema:
```json
{
  "start": "HH:MM",
  "end": "HH:MM",
  "days": [1,2,3,4,5]  // ISO-8601: 1=Mon..7=Sun
}
```

### New Table: `nl_parse_attempts`
| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid | FK to users |
| input_text | varchar(200) | Full NL input (200 char max) |
| timezone | varchar(64) | IANA timezone |
| parser_path | enum | rule_based, llm_fallback |
| confidence | decimal(3,2) | 0.00-1.00 |
| cron_result | varchar(100) | Generated cron expression |
| active_hours_result | json | Generated active_hours config |
| status | enum | queued, running, completed, failed |
| user_confirmed | boolean | Whether user confirmed result |
| error_message | text | Error details if failed |
| created_at | timestamp | |
| completed_at | timestamp | |

**Retention**: 90 days via `nl-parse:cleanup` scheduled command (daily)
**Access Control**: Admin and analytics roles only (RBAC-gated)

## API Endpoints (Internal Only)

### POST `/internal/api/schedule/parse`
Request:
```json
{
  "input": "every weekday at 9am",
  "timezone": "America/New_York"
}
```
Response (high confidence rule-based):
```json
{
  "status": "completed",
  "parse_attempt_id": "uuid",
  "result": { /* Parser Output Contract */ }
}
```
Response (low confidence / LLM queued):
```json
{
  "status": "queued",
  "parse_attempt_id": "uuid"
}
```
Response (failed with fallback):
```json
{
  "status": "failed",
  "parse_attempt_id": "uuid",
  "rule_based_result": { /* best-effort Parser Output Contract */ },
  "error": "LLM timeout"
}
```

### GET `/internal/api/schedule/parse/{parse_attempt_id}`
Poll endpoint returning status: queued | running | completed | failed

### WebSocket Event
- Channel: `private-user.{userId}`
- Event: `NlParseCompleted`
- Payload: `{ parse_attempt_id, status, result | error, rule_based_result? }`

## Configuration Values
| Key | Default | Description |
|-----|---------|-------------|
| `nl_parse.confidence_threshold` | 0.75 | Auto-accept threshold |
| `nl_parse.llm_timeout_seconds` | 30 | LLM request timeout |
| `nl_parse.idempotency_window_seconds` | 60 | Deduplication window |
| `nl_parse.rate_limit_per_minute` | 10 | LLM fallback rate limit per user |
| `nl_parse.rate_limit_per_hour` | 60 | LLM fallback rate limit per user |
| `nl_parse.max_input_length` | 200 | Max characters for NL input |
| `nl_parse.min_interval_minutes` | 1 | Minimum schedule interval |
| `nl_parse.retention_days` | 90 | Telemetry retention period |

## UI Components

### JobForm Modes
Add "Natural Language" tab alongside existing Basic/Advanced modes. Existing flows remain fully backward compatible.

### NL Input Section
- Text input field (200 char max)
- Timezone dropdown (defaults to account timezone, overridable via NL or dropdown)
- "Parse" button triggering async parse

### Confirmation Modal
Displayed when confidence < 0.75 or ambiguous flag set. Blocking modal with:
- Parsed cron expression (read-only, no inline editing)
- Human-readable explanation
- Timezone
- Next 5 runs (filtered by active_hours_config, showing true dispatch times in local + UTC)
- For ambiguous times: both AM/PM options displayed
- Buttons: "Confirm", "Cancel", "Edit in Advanced Mode" (pre-fills generated cron)

### LLM Degradation Warning
When LLM unavailable (timeout/rate-limit/outage), show warning banner with best-effort rule-based result. User must explicitly confirm to proceed.

### Active Hours UI
- In Advanced mode: "Disable active hours restriction" checkbox (nullifies active_hours_config on save)
- Active hours only settable via NL parsing, not directly editable
- Jobs edited always open in Advanced (cron) mode; NL input not persisted

## Scheduler Behavior

### Dispatch Evaluation Order
1. Cron due check
2. Active-hours check (only if `active_hours_config IS NOT NULL`)
3. Dispatch or skip

### Active Hours Skip Logging
```json
{
  "skip_reason": "outside_active_hours",
  "job_id": "uuid",
  "scheduled_time": "ISO8601",
  "active_hours_config": { ... }
}
```

### Backward Compatibility
Jobs with `active_hours_config = null` behave exactly as before. No scheduler rewrite required.

## Services and Classes

### NlScheduleParserService
Orchestrates hybrid parsing: rule-based first, LLM fallback if needed. Integrates with existing `InterrogationRunnerAdapter` and `AdapterFactory`.

### RuleBasedScheduleParser
Deterministic pattern matching for v1 patterns. Returns confidence score.

### LlmScheduleParserStrategy (Interface)
Provider-agnostic interface for direct API LLM parsing with implementations for each provider. Used only when CLI integration path is unavailable.

### NlParseAttemptRepository
Manages `nl_parse_attempts` CRUD and idempotency lookup.

### ActiveHoursEvaluator
Evaluates whether a given timestamp falls within active_hours_config window.

### NextRunsCalculator
Computes next N runs accounting for both cron expression and active_hours_config filtering.

## Commands

### `nl-parse:cleanup`
Daily scheduled command deleting `nl_parse_attempts` older than 90 days.

## Logging and Privacy
- Application logs: redacted/truncated input (first 80 chars) + SHA-256 hash
- Database (`nl_parse_attempts.input_text`): full text stored (200 char max) for parser improvement analysis
- Telemetry table access: admin/analytics RBAC roles only

## Day Indexing Standard
ISO-8601 canonical indexing throughout: 1=Monday through 7=Sunday. Applied to parser output, database storage, scheduler evaluation, and UI display.

## Goals

- Implement Natural Language scheduling mode in JobForm UI alongside existing Basic/Advanced modes
- Build hybrid parser with rule-based first pass, reusing existing InterrogationRunnerAdapter/AdapterFactory/Claude/Codex/SystemPromptResolver/InterrogationSetting infrastructure for LLM fallback
- Implement provider-agnostic LlmScheduleParserStrategy interface for direct API fallback when CLI integration unavailable
- Support v1 deterministic patterns including time windows that populate active_hours_config
- Add active_hours_config JSON column to agent_jobs table for complex scheduling windows
- Implement async parse workflow with internal API endpoints (/internal/api/schedule/parse) and WebSocket completion events on user.{userId} channel
- Create blocking confirmation modal for low-confidence/ambiguous results showing cron (read-only), explanation, timezone, and filtered next 5 runs
- Add telemetry via nl_parse_attempts table with 90-day retention, daily cleanup command, and admin/analytics RBAC-gated access
- Integrate active hours evaluation into dispatch flow without scheduler rewrite
- Maintain full backward compatibility for existing jobs and Basic/Advanced creation flows


## Constraints

- NL input maximum length: 200 characters
- Confidence threshold for auto-accept: 0.75 (75%)
- LLM timeout default: 30 seconds (configurable)
- Idempotency window: 60 seconds default (configurable per environment)
- Rate limiting on LLM fallback path only: 10/minute and 60/hour per user
- Minimum schedule interval: 1 minute (same as existing cron minimum)
- Day indexing must be ISO-8601 (1=Mon..7=Sun) across all layers
- Cron remains canonical storage format; NL input not persisted on jobs
- Jobs always open in Advanced mode for editing
- Active hours only settable via NL parsing, not directly editable
- Telemetry table stores full input text; application logs use first 80 chars + SHA-256 hash
- Telemetry table access restricted to admin/analytics RBAC roles only
- Existing jobs with null active_hours_config must behave unchanged
- Parse confirmation modal must be blocking (not inline or toast) with no inline cron editing
- Next runs preview must show true dispatch times filtered by active hours
- API endpoints are internal only: /internal/api/schedule/parse and /internal/api/schedule/parse/{parse_attempt_id}
- Must reuse existing InterrogationRunnerAdapter, AdapterFactory, Claude, Codex, SystemPromptResolver, and InterrogationSetting infrastructure
- On LLM failed/unavailable, return rule_based_result fallback with degradation warning


## Acceptance Criteria

- User can create job via NL mode with input like 'every weekday at 9am' and system generates valid cron
- High-confidence rule-based parse (>=0.75) returns completed status immediately without modal
- Low-confidence or ambiguous parse triggers blocking confirmation modal with cron (read-only), explanation, timezone, next 5 filtered runs
- Ambiguous time input (e.g., 'at 5') shows both AM and PM options in modal
- Modal includes 'Edit in Advanced mode' button that pre-fills generated cron in Advanced tab; no inline cron editing
- LLM fallback queues parse attempt and returns parse_attempt_id for polling via internal API
- Parse attempt transitions through states: queued → running → completed | failed
- WebSocket event NlParseCompleted fires on private-user.{userId} channel when async parse completes or fails
- LLM timeout/outage returns failed status with rule_based_result fallback and warning banner requiring explicit confirmation
- Duplicate parse submissions within 60-second idempotency window reuse existing parse attempt
- Rate limiting enforced on LLM fallback path (10/min, 60/hour per user)
- active_hours_config populated from NL patterns like 'every 2 hours from 9am to 5pm'
- Scheduler skips dispatch when outside active_hours window and logs skip_reason: outside_active_hours
- Jobs with null active_hours_config dispatch on cron match only (unchanged behavior)
- Advanced mode shows 'Disable active hours restriction' checkbox that nullifies config on save
- nl_parse_attempts table stores full input text, parser path, confidence, result, and confirmation status
- nl_parse_attempts table access restricted to admin/analytics RBAC roles
- nl-parse:cleanup command runs daily and deletes records older than 90 days
- Application logs contain first 80 chars of input + SHA-256 hash (not full text)
- All NL-generated cron passes existing NumericCronExpression validation
- Existing Basic/Advanced job creation flows unaffected
- Timezone defaults to account timezone but NL input and dropdown can override
- LLM fallback reuses existing InterrogationRunnerAdapter/AdapterFactory/Claude/Codex/SystemPromptResolver/InterrogationSetting infrastructure

