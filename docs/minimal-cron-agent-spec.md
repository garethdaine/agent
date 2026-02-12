# Minimal Local Cron + Agent Runner Spec (Final Baseline)

## 1. Objective
Build a local-first Laravel 12 + Jetstream (Inertia, dark mode, SSR) app that:
- Lets authenticated users create and manage cron jobs.
- Dispatches due jobs to queue workers and spawns local subprocesses.
- Monitors scheduler health and per-run subprocess behavior in near real time.

This document is the final signed baseline for MVP (`v1.0`) requirements.

## 2. Scope and Non-Goals
### In Scope
- Job CRUD/toggle/run-now/stop in authenticated UI.
- Scheduler-driven due dispatch with bounded backfill and idempotency.
- Queue-backed subprocess execution with strict lifecycle states.
- Live run monitoring via polling APIs.
- Reverb / Echo websocket infrastructure for real-time events.
- Scheduler heartbeat monitoring.
- Soft-delete jobs with restore and delayed hard-prune.
- Audit logging for mutating actions.
- Pruning and maintenance commands with dry-run support.
- API versioning under `/agent/api/v1`.

### Out of Scope
- Remote/public access and mobile token access.
- Laravel AI SDK integration.
- Distributed scheduling or multi-node coordination.
- Advanced RBAC/admin role model.
- Automatic retry orchestration.

## 3. Actors, Ownership, Visibility
- Actors:
  - `owner`: authenticated user creating/managing jobs.
  - `system`: scheduler, queue worker, maintenance commands.
- Ownership:
  - Job ownership is `agent_jobs.user_id`.
  - Run ownership is inherited from job and snapshotted as `agent_job_runs.user_id`.
  - `initiated_by_user_id` is required for manual runs and nullable for scheduled runs.
- Visibility:
  - Cross-user visibility is prohibited for jobs/runs/events/audits.
  - Scheduler heartbeat is global system state visible to authenticated users.
- Ownership transfer is out of scope and disallowed.

## 4. Runtime and Platform Requirements
- Supported OS: macOS and Linux.
- Unsupported OS: Windows (warning only; no hard boot block).
- Required runtime components:
  - `php artisan schedule:work` (canonical).
  - `php artisan horizon` (required queue runtime).
  - Reverb server (Herd-managed local Reverb endpoint).
  - Redis (hard prerequisite; no fallback backend).
  - Queue binding is fixed in MVP: `connection=redis`, `queue=agent`.
  - Horizon defaults in MVP: `tries=1`, `backoff=0`, `timeout=86500`, `retry_after=90000`, `maxProcesses=2` (configurable bounds `1..8`).
- Secondary scheduler mode supported: cron invoking `php artisan schedule:run`.
- Database support in MVP: SQLite, MySQL, and PostgreSQL.
- Target versions:
  - PHP `8.3.x`
  - Laravel `12.x`
  - Jetstream aligned to Laravel 12
  - `dragonmantank/cron-expression` major `v3` (pinned)

## 5. Data Model
### 5.1 `agent_jobs`
- `id`
- `user_id`
- `name`
- `description` nullable
- `cron_expression`
- `timezone`
- `is_enabled`
- `max_runtime_seconds`
- `cooldown_seconds`
- `runner_type` enum: `claude|codex|custom`
- `command_template`
- `task_markdown_path`
- `working_directory`
- `env_json` nullable
- `last_validated_executable_path` nullable
- `scheduled_path_failure_streak` default `0` (system-managed consecutive scheduled path-failure counter)
- soft delete column `deleted_at`
- `created_at`, `updated_at`

### 5.2 `agent_job_runs`
- `id`
- `agent_job_id`
- `user_id` snapshot
- `initiated_by_user_id` nullable for schedule, required for manual
- `trigger_type` enum: `schedule|manual`
- `due_window_utc_minute` nullable for manual, required for schedule
- `status` enum: `queued|starting|running|stopping|succeeded|failed|killed|timed_out|skipped`
- `pid` nullable
- `resolved_executable_path` nullable
- `started_at` nullable
- `finished_at` nullable
- `exit_code` nullable
- `signal` nullable
- `duration_ms` default `0`
- `stdout_bytes_pre` default `0`
- `stdout_bytes_post` default `0`
- `stderr_bytes_pre` default `0`
- `stderr_bytes_post` default `0`
- `error_summary` nullable
- `error_code` nullable
- `metadata_json` with required defaults:
  - `output_truncated: false`
  - `redaction_count: 0`
- optional metadata keys:
  - `termination_mode`, `truncate_bytes`, `skip_reason`, `reconcile_reason`, `pid_not_found`
- `created_at`, `updated_at`

### 5.3 `agent_run_events`
- `id`
- `agent_job_run_id`
- `event_type` enum: `stdout|stderr|lifecycle`
- `sequence` (strictly increasing per run; gaps allowed)
- `payload` (max 8192 bytes post-normalization/redaction)
- `event_ts` (UTC RFC3339 with milliseconds)
- `created_at`

### 5.4 `scheduler_heartbeats`
- `id`
- `source` unique (MVP source: `scheduler_dispatch`)
- `last_seen_at`
- `meta_json` containing:
  - `dispatched_count`
  - `skipped_overlap_count`
  - `skipped_cooldown_count`
  - `error_count`
  - `tick_started_at`
  - `tick_finished_at`
  - `watermark_minute_utc`

### 5.5 `agent_system_state`
- key/value store for system cursors.
- required key: `dispatch_last_minute_utc`.

### 5.6 `agent_audit_logs`
- immutable mutating-action audit records.
- required fields include:
  - actor type/id
  - action
  - target type/id
  - before/after changed fields
  - request id
  - IP
  - user-agent
  - hostname
  - outcome/error
  - timestamp

### 5.7 `agent_maintenance_checkpoints`
- stores chunked prune checkpoints and progress metadata.

### 5.8 Indexes and Constraints
- jobs index `(user_id, is_enabled, deleted_at)`
- jobs unique filtered `(user_id, name)` on non-deleted rows
- runs index `(agent_job_id, status, created_at)`
- runs index `(user_id, created_at)`
- runs unique `(agent_job_id, due_window_utc_minute, trigger_type)` for scheduled runs
- events unique `(agent_job_run_id, sequence)`
- heartbeat unique `(source)`
- audit index `(user_id, created_at)`
- audit index `(action, created_at)`
- FK behavior:
  - soft-delete of job leaves children intact
  - hard-pruned jobs cascade to runs
  - hard-pruned runs cascade to events

## 6. Validation Rules
### 6.1 Job Fields
- `name`: required, 3..120 chars, UTF-8, no control chars.
- `description`: optional, 0..4000 chars UTF-8.
- `cron_expression`: required, max 100 chars, printable ASCII only, 5-part numeric cron syntax (numbers with `*`, ranges, lists, and step values; no month/day names).
- `timezone`: required valid IANA identifier, max 64 chars.
- `command_template`: single-line, max 2000 chars, required for `custom`, optional override for `claude`/`codex`.
- `task_markdown_path`: required absolute path (unless inline markdown content is supplied), max 1024 chars, existing readable regular file, extension `.md|.markdown`, UTF-8 text-like.
- `task_markdown_content`: optional inline markdown text. When provided, server persists it to a managed `.md` file under allowed task bases and stores the resolved file path in `task_markdown_path`.
- `working_directory`: required absolute path, max 1024 chars, existing readable/executable directory.
- `max_runtime_seconds`: 10..86400.
- `cooldown_seconds`: 0..86400.

### 6.2 Path and Base Restrictions
- Relative paths are rejected.
- Symlinks are allowed only if `realpath()` succeeds.
- Matching is case-sensitive after `realpath()` normalization.
- Boundary-safe prefix checks are required (`base + '/'` semantics).
- Allowed `working_directory` bases:
  - `/Users/garethdaine/Code`
  - `/Users/garethdaine/Code/agent`
- Allowed `task_markdown_path` bases:
  - `/Users/garethdaine/Code/agent/tasks`
  - `/Users/garethdaine/Code/agent/prompts`
- Runtime path re-check is mandatory; failures finalize run as:
  - status: `failed`
  - `error.code`: `RUN_PATH_NOT_FOUND`
- Scheduled path-failure auto-disable policy:
  - after 3 consecutive scheduled `RUN_PATH_NOT_FOUND` failures, job is auto-disabled.
  - implementation tracks this with `agent_jobs.scheduled_path_failure_streak`.
  - counter resets on any successful run (scheduled or manual).
  - manual `RUN_PATH_NOT_FOUND` failures do not increment this counter.

### 6.3 Environment Overrides
- `env_json` is not exposed in MVP UI.
- key regex: `^[A-Z][A-Z0-9_]{0,63}$`.
- max 50 keys.
- max value length 1024.
- max payload 16 KB.
- merged over process environment for allowed keys only.
- protected/forbidden keys: `PATH`, `HOME`, `SHELL`, `USER`, `LOGNAME`, `APP_KEY`, and keys matching `(SECRET|TOKEN|PASSWORD|PASS|PRIVATE|CREDENTIAL)` case-insensitive.

## 7. Binary Allowlist and Command Policy
- Canonical allowlisted executable realpaths:
  - `/Users/garethdaine/.local/bin/claude`
  - `/opt/homebrew/bin/codex`
  - `/Users/garethdaine/Code/agent/bin/agent-runner`
- Alias mapping:
  - `claude -> /Users/garethdaine/.local/bin/claude`
  - `codex -> /opt/homebrew/bin/codex`
  - `custom -> /Users/garethdaine/Code/agent/bin/agent-runner`
- Executable path is enforced by `runner_type` alias mapping; template executable is not trusted.
- For `custom`, template must start with `/Users/garethdaine/Code/agent/bin/agent-runner`.
- Dispatch-time executable re-resolution is required.
- If re-resolved path is not allowlisted: create terminal failed run (`BINARY_NOT_ALLOWED`/validation failure category).
- `agent_jobs.last_validated_executable_path` is updated on each successful dispatch-time validation.
- Each run snapshots immutable `resolved_executable_path`.
- Save-time validation fails if executable cannot be resolved for runner/template.

## 8. Template and Placeholder Rules
- Allowed placeholders only:
  - `{{run_id}}`
  - `{{job_id}}`
  - `{{task_markdown_path}}`
  - `{{working_directory}}`
  - `{{job_name}}`
- Unknown placeholders fail validation.
- Placeholders must occupy whole tokens and resolve to single argv tokens.
- Shell operators/features are prohibited: `|`, `>`, `<`, `;`, `&&`, `||`, command substitution, subshells.
- Execution is argv token-based only (no shell commandline execution).
- `custom` template must include `{{task_markdown_path}}`.
- `{{working_directory}}` in template is optional because process CWD is set independently.
- Default templates:
  - `claude`: `/Users/garethdaine/.local/bin/claude -p {{task_markdown_path}}`
  - `codex`: `/opt/homebrew/bin/codex exec {{task_markdown_path}}`
- User-supplied template for `claude|codex` is allowed and overrides defaults after validation.

## 9. Scheduler and Dispatch Semantics
- Dispatcher command: `agent:dispatch-due`.
- Schedule registration: every minute with overlap lock.
- Lock TTL target: 120 seconds.
- Effective single scheduler behavior:
  - extra scheduler instances self-suppress via lock failure.
  - DB idempotency remains safety net.
- Due source of truth: `dragonmantank/cron-expression`.
- Due computation in job timezone; normalize to UTC minute window.
- Watermark key: `dispatch_last_minute_utc` in `agent_system_state`.
- Window scanned per tick: `(watermark, now]` (start exclusive, end inclusive).
- First tick with no watermark scans current minute only.
- Clock rollback handling: clamp effective start to `watermark + 1 minute`.
- Backfill caps:
  - max 15 minutes lookback per tick globally.
  - max 5 schedule windows per job per tick.
  - max 20 runnable dispatches per tick.
- Dispatch order under caps:
  - `due_window_utc_minute ASC`, then `agent_job_id ASC`.
- Windows deferred by cap are not dropped:
  - deferred to next tick
  - audit record `deferred_capacity`
  - no run row created for defer.
- Idempotency key for scheduled runs:
  - `(agent_job_id, due_window_utc_minute, trigger_type='schedule')`
  - enforced by unique DB constraint.

## 10. Overlap, Cooldown, Missed Runs, Run-Now
- Active overlap states: `queued|starting|running|stopping`.
- Overlap policy is per-job only.
- No global concurrency cap in MVP.
- Overlap check precedence is before cooldown.
- Cooldown uses previous non-skipped run `finished_at`.
- Cooldown applies after `succeeded|failed|timed_out|killed`.
- Scheduled overlap/cooldown blocks create `skipped` run rows.
- `skipped` rows count in run history and retention totals.
- If both overlap and cooldown apply:
  - primary skip reason: `overlap`
  - secondary reasons stored in metadata.
- `run now`:
  - allowed even when job is disabled
  - bypasses cooldown
  - does not bypass overlap
  - blocked on active overlap states, including `queued`
  - overlap block response: `409` with `RUN_OVERLAP_ACTIVE`
- `run now` idempotency:
  - key fingerprint: `sha256("run-now|user_id|job_id")`
  - state stored in Redis cache
  - TTL: 3 seconds
  - cleanup via automatic key expiry
  - replay returns `202` accepted payload with `idempotent_replay=true`
  - if Redis/Horizon queue path is unavailable at request time, return `503` with `error.code=QUEUE_UNAVAILABLE`.

## 11. Run Lifecycle and Transition Rules
- States:
  - `queued`, `starting`, `running`, `stopping`, `succeeded`, `failed`, `killed`, `timed_out`, `skipped`
- Allowed transitions:
  - `queued -> starting|skipped|failed`
  - `starting -> running|failed|timed_out|killed`
  - `running -> stopping|succeeded|failed|timed_out|killed`
  - `stopping -> killed|failed`
  - terminal states have no outgoing transitions
- Transition ownership:
  - dispatcher: `queued|skipped`
  - runner: `starting|running|succeeded|failed|timed_out|killed`
  - stop endpoint: requests `stopping`; finalization by runner/stop handler
- Invalid transition:
  - API-triggered: `409`
  - otherwise: no-op and warning log
- Timestamp and PID rules:
  - `started_at` set only after successful process start
  - `pid` set only after successful process start
  - pre-start failures keep `started_at=null`
- Terminal mapping:
  - exit code `0` => `succeeded`
  - non-zero exit => `failed`
  - timeout => `timed_out`
  - user stop => `killed`
  - spawn exception => `failed`
  - external termination => `failed` with signal metadata
- `skipped` bookkeeping:
  - `started_at=null`
  - `pid=null`
  - `exit_code=null`
  - `duration_ms=0`
  - `finished_at` required
- Duration formula:
  - if `started_at` exists: `max(0, finished_at - started_at)`
  - else `0`

## 12. Timeout and Stop Semantics
- Timeout clock uses monotonic runtime from process start.
- Stop endpoint behavior:
  - active run request accepted: `202`
  - already terminal: `200` idempotent no-op
  - invalid transition conflict: `409`
- `RUN_ALREADY_TERMINAL` is internal-only log reason, not public API code.
- Stop `202` payload fields:
  - `run_id`
  - `status=stopping`
  - `accepted_at`
  - `requested_by_user_id`
  - `poll_after_ms`
- Kill escalation:
  - `SIGTERM`
  - 10-second grace
  - `SIGKILL` once
- If `SIGKILL` fails: final status `failed`, `error.code=TERMINATION_FAILED`.
- Missing PID on active stop:
  - finalize with `killed`
  - `termination_mode="pid_missing"`
  - `pid_not_found=true`
- Stop races with natural completion/timeout:
  - first successful terminal CAS wins
  - later conflicting actions become idempotent no-op.

## 13. Subprocess Output Capture and Diagnostics
- Capture mode: line-buffered, force flush every 250 ms, chunk size 4 KB.
- Long lines are hard-split into 4 KB segments with `payload.continuation=true`.
- Event payload max: 8192 bytes post-normalization/redaction; overflow split into additional sequential events.
- Sequence:
  - strictly increasing and unique per run
  - gaps allowed
- Global ordering across stdout/stderr uses sequence assignment.
- Binary handling:
  - if a 1024-byte chunk has >30% non-printable/invalid UTF-8 bytes, persist summary placeholder `[binary output omitted: N bytes]`.
- Total persisted stream cap per run: 5 MB combined stdout/stderr.
- After cap:
  - process continues
  - capture halts
  - emit one truncation lifecycle notice
  - set `output_truncated=true` and `truncate_bytes` metadata.
- Stream write failures:
  - enter degraded capture mode first
  - fail run on persistent failure threshold:
    - 5 consecutive write failures, or
    - 10 failures within 30 seconds

## 14. Redaction and Secret Handling
- Never persist raw secrets intentionally.
- Redaction runs before DB writes and structured logs.
- Required redaction tokens:
  - `[REDACTED_API_KEY]`
  - `[REDACTED_BEARER_TOKEN]`
  - `[REDACTED_PASSWORD]`
  - `[REDACTED_PRIVATE_KEY]`
  - fallback `[REDACTED]`
- Pattern precedence:
  - private keys
  - bearer/auth headers
  - API key formats
  - password assignments
  - generic secret tokens
- `redaction_count` counts replaced segments.
- Track pre/post redaction byte counters for stdout/stderr.
- Disallowed persisted classes include plaintext passwords, API tokens, private keys, session cookies, SSH keys, credential file contents, and env secret values.

## 15. Reconciliation Rules
- Reconciliation runs:
  - once at `schedule:work` process boot
  - at start of every dispatcher tick
- Re-identification uses PID + commandline fingerprint (+ start-time when available).
- PID exists but fingerprint mismatch:
  - mark `failed`
  - `reconcile_reason=pid_reused_or_mismatch`
- Orphaned active runs:
  - if prior stop intent exists => `killed`
  - otherwise => `failed`

## 16. Heartbeat Health Semantics
- Heartbeat written by `agent:dispatch-due` each tick.
- Server computes authoritative health status.
- Default thresholds (configurable global):
  - `healthy <= 90s`
  - `degraded <= 300s`
  - `down > 300s`
- If heartbeat record absent, status is `unknown` indefinitely.

## 17. API Surface and Contracts
- Canonical JSON API routes are versioned under `/agent/api/v1/...`.
- Legacy non-versioned JSON routes are removed.
- Attempted access to unsupported/non-goal API surfaces returns `404`.
- All responses include header: `X-Agent-Api-Version: 1.0`.
- All errors use:
  - `{ "error": { "code": "...", "message": "...", "details": { ... } } }`
- Validation failures are always `422` with `code=VALIDATION_ERROR`.
- Non-owner resource access returns `404` to avoid enumeration.
- Canonical endpoint set:
  - `GET /agent/api/v1/jobs`
  - `POST /agent/api/v1/jobs`
  - `PUT /agent/api/v1/jobs/{id}`
  - `POST /agent/api/v1/jobs/{id}/toggle`
  - `POST /agent/api/v1/jobs/{id}/run-now`
  - `GET /agent/api/v1/jobs/{id}/runs`
  - `GET /agent/api/v1/runs/{id}`
  - `GET /agent/api/v1/runs/{id}/events?after_sequence=n&limit=m`
  - `POST /agent/api/v1/runs/{id}/stop`
  - `GET /agent/api/v1/dashboard/metrics?window=24h|7d`
  - `GET /agent/api/v1/health/scheduler`
- Run visibility when parent job is soft-deleted:
  - owner can still access `GET /agent/api/v1/runs/{id}` and `GET /agent/api/v1/runs/{id}/events`.
  - placement in job-centric pages is scoped to deleted-job views.
- `GET /jobs` response envelope:
  - `data`, `meta`, `links`, `filters`, `sort`
  - filter params: `q,is_enabled,runner_type,active,deleted`
  - sort params: `sort,dir`
- `GET /jobs/{id}/runs` default sort:
  - `created_at DESC`
- `GET /runs/{id}` includes:
  - run core fields
  - `metadata_json`
  - `output_stats` (`stdout_bytes_pre/post`, `stderr_bytes_pre/post`, `redaction_count`, `output_truncated`)
  - `links` to events
- `GET /runs/{id}/events` includes:
  - `data: [{id,run_id,sequence,event_type,payload,created_at,event_ts}]`
  - `meta: {after_sequence,returned,has_more,next_after_sequence}`
- Lifecycle payload schema variants:
  - `state_transition`
  - `skip_reason`
  - `truncation_notice`
  - `redaction_notice`
  - `system_notice`

## 18. HTTP Status and Error Code Semantics
- Success:
  - create: `201`
  - edit/toggle/list/detail: `200`
  - run-now accepted: `202`
  - stop accepted: `202`
  - stop terminal no-op: `200`
- Errors:
  - `401` `UNAUTHENTICATED`
  - `404` `NOT_FOUND` for non-owner/absent
  - `409` `CONFLICT`/domain conflict (for example `RUN_OVERLAP_ACTIVE`, `RUN_NOT_STOPPABLE`, `RUN_TRANSITION_CONFLICT`)
  - `422` `VALIDATION_ERROR`
  - `429` `RATE_LIMITED`
  - `503` `QUEUE_UNAVAILABLE`
- `FORBIDDEN_ACTION` is reserved and not used in MVP public responses.

## 19. Rate Limiting
- Mutating endpoints are rate-limited to `30 requests/minute/user`.
- Limit is shared across mutating endpoints.
- System internal actions are excluded.
- Breach response: `429` with standard error envelope and `error.code=RATE_LIMITED`.

## 20. Jetstream/Inertia UI Requirements
### Jobs Index
- Required columns:
  - name
  - enabled
  - runner type
  - cron
  - timezone
  - next run
  - last run status
  - last run finished
  - active run indicator
- Default sort:
  - enabled first
  - next run ascending
  - name ascending
- Pagination default 25, max 100.
- Deleted jobs available via dedicated filter/tab.
- `next run` display is pure cron-next-time (ignores overlap/cooldown).

### Job Form
- Create/edit with all validated fields.
- Save-time executable validation required.

### Monitor
- Default range: last 24h.
- Latest runs widget cap: 50.
- Active view poll interval: 2s.
- Inactive views poll interval: 10s.
- Hidden-tab poll interval: 15s.
- Auto-follow tail on by default; pause on scroll-away; explicit resume.
- Poll failure backoff: `2s,4s,8s,15s` max.
- Warning banner after 3 consecutive failures; retries continue indefinitely.
- Live freshness target p95 <= 3s capture-to-display.
- Queue lag warning threshold:
  - oldest queued run age > 60s, or
  - queued count > 10.
- Inertia pages must consume the same versioned JSON API endpoints under `/agent/api/v1` with no separate parallel data-loading contract.

## 21. Dashboard Metrics
- Global-only metrics in MVP:
  - runs today
  - success rate
  - average duration
  - queue backlog count
  - backlog oldest queued age
  - scheduler health
- Success rate formula:
  - `succeeded / (succeeded + failed + timed_out + killed)` over selected window.
- Skipped runs excluded from success/failure denominator.
- Average duration uses terminal non-skipped runs.
- Default window: 24h with selectable 7d.
- Backlog oldest queued age is computed globally across all queued runs.

## 22. Security, Authorization, Auditing
- All routes require authenticated session.
- Authorization by ownership.
- CSRF/session security remains Jetstream defaults.
- Mutating actions audited; read-only views are not audited.
- Audit storage:
  - DB table `agent_audit_logs` (retention 90 days)
  - mirrored structured Laravel logs
- Audit records immutable.
- Owner can view only own audit records through policy (no dedicated MVP UI).
- System actions write audit with actor `system`, `actor_user_id=null`.

## 23. Retention, Pruning, and Maintenance
- Runs:
  - retain at least 30 days
  - always retain last 20 runs per job
  - keep failed/timed_out runs for at least 30 days
- Events:
  - retain 7 days
  - never prune events for active runs
- Soft-deleted jobs:
  - restore allowed within 30 days
  - hard-pruned after 30 days
  - hard-prune cascades child runs/events even if children are recent
- Prune command:
  - one command with domain flags (`--runs --events --jobs --audit`)
  - supports `--dry-run`
  - `--dry-run` outputs human-readable by default and JSON with `--json`
  - chunked partial deletion allowed
  - resumable idempotent behavior with progress logging
  - checkpoints stored in `agent_maintenance_checkpoints`
- Schedules:
  - runs/events prune daily at `03:10` local app time
  - soft-deleted job hard-prune daily at `03:20` local app time

## 24. Capacity and Performance Targets
- Capacity targets:
  - up to 100 jobs
  - up to 10 concurrent active runs
  - up to 200 runs/day
  - up to 5 MB output/run
- SLO targets:
  - Jobs index API p95 < 500ms (warm steady state)
  - Run-now ACK p95 < 400ms
  - Monitor polling p95 < 700ms for `limit=100` with up to 5 concurrent active monitors
- Measurement method:
  - seeded dataset: 100 jobs, 2000 runs, 100k events
  - 200 repeated requests per measured endpoint
  - benchmark command: `php artisan agent:benchmark-slo --seed --measure --jobs=100 --runs=2000 --events=100000 --requests=200 --json`
- SLO verification is manual release checklist in MVP.

## 25. Acceptance Criteria
- Job CRUD enforces validation and ownership constraints.
- Scheduler dispatches due windows once per idempotency key.
- Delayed scheduler performs bounded backfill only.
- Overlap/cooldown produce deterministic skipped runs with reasons.
- Run-now bypasses cooldown but not overlap and supports 3-second idempotent replay.
- Runner captures PID, statuses, stdout/stderr events, and terminal outcomes correctly.
- Stop semantics are idempotent and obey escalation rules.
- Heartbeat reports `healthy/degraded/down/unknown` per configured rules.
- Polling monitor shows near-live output and stable ordering.
- Redaction occurs before persistence/logging and no raw secrets are intentionally stored.
- Retention/prune behavior enforces all minimums and caps.
- Soft-delete/restore/hard-prune behaviors match policy.
- API headers/envelopes/status codes and endpoint contracts match spec.

## 26. Required Test Coverage
- Unit:
  - cron validation and due window logic
  - transition matrix
  - cooldown/overlap precedence
  - redaction and output chunking
- Feature:
  - ownership/authorization and status contracts
  - run-now and stop semantics
  - soft-delete/restore flows
  - rate-limit and queue-unavailable paths
- Integration:
  - subprocess lifecycle success/failure/timeout/kill
  - stream persistence and degradation behavior
  - reconciliation behavior
- Mandatory scenarios:
  - DST spring-forward skip
  - DST fall-back double-window
  - stop race and timeout race
  - duplicate dispatch idempotency
  - delayed scheduler bounded backfill
- Endpoint tests must include all applicable codes: `200`, `201`, `202`, `401`, `404`, `409`, `422`, `429`, `503`.

## 27. Operator Runbook Requirements
- First-use sequence:
  - run migrations
  - configure allowed directories and executable allowlist
  - ensure Redis available
  - start `php artisan horizon`
  - start `php artisan schedule:work`
  - create task markdown file
  - login and create job
  - run-now smoke test
  - verify monitor output
- Runbook must document:
  - required processes and startup order
  - health checks and expected logs
  - restart/recovery order
  - orphan reconciliation expectations

## 28. Contract Stability
- Semantic versioning required.
- Stable in v1:
  - IDs
  - enums
  - timestamps
  - pagination envelope
  - cursor contract (`after_sequence`)
  - error envelope
- Potentially unstable:
  - non-core `metadata_json` inner keys beyond documented core set.
