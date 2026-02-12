# Minimal Local Cron + Agent Runner Task List (Final Baseline)

## Progress Notes (2026-02-12)
- Phase 1 is partially complete. Remaining gaps include strict enum enforcement and filtered active-name uniqueness behavior across all supported databases.
- Phase 0 DB compatibility validation is still pending for MySQL; SQLite and PostgreSQL have been exercised.
- Phase 2 validation guardrails are implemented behind `POST /agent/api/v1/jobs` and covered by feature tests.

## Phase -1: Project Scaffold Setup
- [x] Create Laravel 12 project at `/Users/garethdaine/Code/agent` if not already present.
- [x] Install Jetstream with Inertia stack and required flags (SSR + dark mode).
- [x] Install and build frontend dependencies for the Jetstream/Inertia app.
- [x] Configure base `.env` values (`APP_KEY`, DB connection, Redis connection, queue/cache drivers).
- [x] Run base Laravel and Jetstream migrations.
- [x] Publish/install Horizon config and ensure Horizon can boot in this project.
- [x] Verify initial auth flow works (register/login/logout) before feature work.

Definition of done:
- Laravel + Jetstream app is scaffolded and runnable.
- Inertia SSR + dark mode scaffolding is active.
- Auth pages load successfully.
- Migrations are applied and Horizon starts without config errors.

## Phase 0: Runtime Bootstrap and Prerequisites
- [x] Confirm platform targets in docs and environment checks: macOS + Linux supported, Windows warning only.
- [x] Provision Redis and configure Laravel queue/cache for Redis.
- [x] Configure Reverb server/client wiring (Laravel Reverb + Laravel Echo) for websocket support.
- [x] Configure Horizon with dedicated `agent` queue (`tries=1`, `backoff=0`, `timeout=86500`, `maxProcesses` default `2`, bounds `1..8`).
- [x] Enforce fixed queue binding in runtime config: `connection=redis`, `queue=agent`.
- [x] Confirm canonical run mode in docs and scripts: manual `php artisan horizon` + manual `php artisan schedule:work`.
- [x] Add `dragonmantank/cron-expression` (major v3 pinned).
- [ ] Validate DB compatibility for SQLite, MySQL, and PostgreSQL.

Definition of done:
- Auth app boots.
- Redis-backed queue/cache is functional.
- Horizon and scheduler run locally with documented startup order.
- Project passes initial bootstrap smoke checks on supported DBs.

## Phase 1: Schema, Constraints, and Models
- [x] Create migrations for:
- [x] `agent_jobs`
- [x] `agent_job_runs`
- [x] `agent_run_events`
- [x] `scheduler_heartbeats`
- [x] `agent_system_state`
- [x] `agent_audit_logs`
- [x] `agent_maintenance_checkpoints`
- [ ] Add all required columns and enums from final baseline, including:
- [x] soft-delete for jobs
- [ ] run statuses with `stopping` and `skipped`
- [x] `due_window_utc_minute`, `initiated_by_user_id`, `user_id` snapshot, `metadata_json`, `resolved_executable_path`
- [ ] Enforce required unique/index constraints:
- [x] scheduled-run idempotency key unique
- [x] event sequence unique per run
- [ ] filtered uniqueness for active job names per user
- [x] heartbeat source uniqueness
- [x] FK cascade behavior for hard-prune paths.
- [x] Implement models/relations/scopes for enabled, active, latest, deleted.
- [x] Add policies to enforce ownership-only access.

Definition of done:
- Migrations succeed on SQLite/MySQL/PostgreSQL.
- Schema constraints match the signed baseline exactly.
- Ownership and scoped queries are test-covered.

## Phase 2: Validation and Security Guardrails
- [x] Implement strict validation rules for all job fields (length/range/charset requirements).
- [x] Enforce 5-part numeric cron only and IANA timezone validation.
- [x] Enforce absolute path + `realpath()` checks with case-sensitive boundary-safe base restrictions.
- [x] Enforce markdown extension + text-sniff for task files.
- [x] Implement `env_json` constraints and forbidden key policy.
- [x] Implement binary allowlist enforcement with alias-to-realpath mapping.
- [x] Enforce canonical executable by `runner_type` (template executable not trusted).
- [x] Enforce `custom` template prefix and required placeholder rules.
- [x] Add save-time executable resolution validation.

Definition of done:
- Invalid cron/path/template/env/binary inputs consistently fail with `422 VALIDATION_ERROR`.
- Runtime and save-time policy checks are deterministic and test-covered.

## Phase 3: Dispatcher, Scheduling, and Idempotency
- [ ] Implement `agent:dispatch-due`.
- [ ] Add scheduler registration with overlap lock and target lock TTL 120s.
- [ ] Implement timezone-aware due evaluation and UTC minute normalization.
- [ ] Implement watermark state in `agent_system_state` (`dispatch_last_minute_utc`) with transactionally consistent update.
- [ ] Implement bounded backfill:
- [ ] global lookback cap 15 minutes/tick
- [ ] max 5 due windows/job/tick
- [ ] max 20 runnable dispatches/tick
- [ ] deterministic ordering (`due_window_utc_minute ASC`, `agent_job_id ASC`)
- [ ] Implement first-tick behavior (current minute only).
- [ ] Implement rollback clock clamp behavior.
- [ ] Implement deferred-capacity audit records when global cap is reached.
- [ ] Write heartbeat and heartbeat metadata each tick.

Definition of done:
- Scheduled runs dispatch once per due window idempotency key.
- Backfill and cap behavior matches baseline under delayed tick scenarios.
- Heartbeat and watermark behavior is correct under normal and clock-skew cases.

## Phase 4: Runner Lifecycle, Process Control, and Reconciliation
- [ ] Implement queued runner job with argv token execution only.
- [ ] Implement lifecycle transitions and atomic transition enforcement.
- [ ] Implement process start/finish semantics for `started_at`, `pid`, `duration_ms`, terminal mapping.
- [ ] Implement overlap/cooldown skip-run creation semantics for scheduled runs.
- [ ] Implement cooldown rules against prior non-skipped `finished_at`.
- [ ] Implement timeout (monotonic clock) and stop escalation (`SIGTERM` + 10s + `SIGKILL`).
- [ ] Implement stop idempotency and terminal-race resolution (first terminal CAS wins).
- [ ] Implement `pid_missing` and `TERMINATION_FAILED` handling.
- [ ] Implement dispatch-time executable re-validation and failed-run behavior on mismatch.
- [ ] Implement runtime path re-check and `RUN_PATH_NOT_FOUND`.
- [ ] Implement auto-disable after 3 consecutive scheduled `RUN_PATH_NOT_FOUND` failures.
- [ ] Ensure counter resets on any successful run and ignores manual-path-failure increments.
- [ ] Implement reconciliation on scheduler boot + each tick with PID/fingerprint matching.

Definition of done:
- Terminal outcomes, stop behavior, timeout behavior, and reconciliation outcomes match baseline matrix and race rules.

## Phase 5: Stream Capture, Redaction, and Event Contracts
- [ ] Implement stdout/stderr line-buffering with 250ms flush and 4KB chunking.
- [ ] Implement long-line hard split with `payload.continuation`.
- [ ] Implement sequence allocation strategy (strictly increasing unique per run; gaps allowed).
- [ ] Implement UTF-8 normalization and highly-binary chunk summarization.
- [ ] Implement max payload split behavior (8192-byte post-redaction limit).
- [ ] Implement combined output cap 5MB with one truncation notice and `output_truncated` metadata.
- [ ] Implement degraded capture mode and persistent failure threshold logic.
- [ ] Implement required redaction patterns/tokens and precedence ordering.
- [ ] Track pre/post redaction byte counters and replaced segment count.
- [ ] Implement lifecycle payload schema variants and `event_ts` format.

Definition of done:
- Event stream contract, ordering, truncation, and redaction behavior are stable and test-verified.

## Phase 6: Versioned API and HTTP Contracts
- [ ] Implement versioned JSON API under `/agent/api/v1/...`.
- [ ] Remove non-versioned legacy JSON API routes.
- [ ] Return `404` for unsupported/non-goal API surfaces.
- [ ] Ensure all responses include `X-Agent-Api-Version: 1.0`.
- [ ] Implement standard error envelope for all failure classes.
- [ ] Implement endpoint contracts and required statuses, including:
- [ ] `run now` overlap conflict (`409 RUN_OVERLAP_ACTIVE`)
- [ ] idempotent run-now replay (`202` + `idempotent_replay=true`)
- [ ] stop accepted/no-op/conflict paths
- [ ] queue unavailable path (`503 QUEUE_UNAVAILABLE`)
- [ ] owner access to run detail/events remains valid even when parent job is soft-deleted.
- [ ] Implement rate limiting for mutating endpoints (30/min/user shared) with `429 RATE_LIMITED`.
- [ ] Exclude scheduler/maintenance system actions from HTTP rate limits.

Definition of done:
- All endpoint response shapes/statuses/headers match baseline and are covered by tests.

## Phase 7: Jetstream/Inertia UX
- [ ] Implement Jobs index with required columns, filters, sorting, pagination.
- [ ] Implement Create/Edit forms with all validation and confirmation UX requirements.
- [ ] Implement Deleted tab/filter and restore UX behavior.
- [ ] Implement Monitor page with:
- [ ] latest runs cap 50
- [ ] default range 24h
- [ ] active/inactive/hidden-tab polling cadence (2s/10s/15s)
- [ ] retry backoff and failure banner behavior
- [ ] auto-follow tail pause/resume behavior
- [ ] queue lag warning thresholds
- [ ] Implement scheduler health card with `unknown/healthy/degraded/down`.
- [ ] Ensure Inertia pages consume the same versioned JSON API under `/agent/api/v1` (no parallel data-loading contract).

Definition of done:
- End-to-end workflow is achievable from UI and matches behavior contracts exactly.

## Phase 8: Audit, Retention, and Maintenance
- [ ] Implement immutable audit logging for mutating actions only.
- [ ] Mirror audit entries to structured Laravel logs.
- [ ] Enforce owner-scoped audit access policies.
- [ ] Implement unified prune command with domain flags (`--runs --events --jobs --audit`).
- [ ] Implement `--dry-run` output modes (human default, JSON with `--json`).
- [ ] Implement chunked prune with checkpoints in `agent_maintenance_checkpoints`.
- [ ] Implement prune retention rules:
- [ ] runs >=30 days and keep last 20/job
- [ ] failed/timed_out >=30 days
- [ ] events >=7 days except active runs
- [ ] deleted jobs hard-pruned after 30 days with cascading children
- [ ] Schedule prune jobs at:
- [ ] 03:10 local app time (runs/events)
- [ ] 03:20 local app time (deleted jobs)
- [ ] Ensure prune actions emit system audit entries.

Definition of done:
- Retention and prune semantics are reproducible, resumable, and match baseline constraints.

## Phase 9: Dashboard Metrics and Performance Verification
- [ ] Implement global dashboard cards for runs today, success rate, average duration, backlog count, oldest queued age, scheduler health.
- [ ] Implement metric formulas and windows (default 24h, selectable 7d) with skipped-run exclusion for success rate.
- [ ] Ensure backlog oldest queued age is computed globally (not per-job).
- [ ] Build benchmark command/script for SLO verification dataset:
- [ ] 100 jobs, 2000 runs, 100k events
- [ ] 200 repeated requests per endpoint
- [ ] Validate p95 targets:
- [ ] jobs index <500ms warm
- [ ] run-now ACK <400ms
- [ ] monitor poll <700ms (`limit=100`, up to 5 active monitors)

Definition of done:
- Manual release checklist includes passing performance measurements for all required SLOs.

## Phase 10: Acceptance and Sign-Off Artifacts
- [ ] Execute mandatory tests:
- [ ] DST spring-forward skip
- [ ] DST fall-back double window
- [ ] overlap/cooldown precedence
- [ ] delayed scheduler bounded backfill
- [ ] stop/timeouts races and idempotent stop
- [ ] duplicate dispatch idempotency
- [ ] soft-delete/restore/hard-prune paths
- [ ] Validate endpoint code coverage for `200/201/202/401/404/409/422/429/503`.
- [ ] Produce required sign-off artifacts:
- [ ] test report
- [ ] UI screenshots
- [ ] command/runtime logs

Definition of done:
- All baseline acceptance criteria are met and sign-off artifacts are present.

## Suggested Delivery Order
1. Phase -1
2. Phase 0
3. Phase 1
4. Phase 2
5. Phase 3
6. Phase 4
7. Phase 5
8. Phase 6
9. Phase 7
10. Phase 8
11. Phase 9
12. Phase 10

## MVP Milestone Checklist
- [ ] Job CRUD/toggle/run-now/stop works with ownership and validation.
- [ ] Scheduler dispatches due windows once with bounded backfill and idempotency.
- [ ] Runner lifecycle/status transitions are correct and observable.
- [ ] Live monitor polling and event stream behavior meet contract.
- [ ] Heartbeat health states are correct, including `unknown`.
- [ ] Redaction and secret-handling policies are enforced.
- [ ] Prune/retention policies execute correctly with dry-run support.
- [ ] Versioned API contract and headers are enforced.
- [ ] Required release artifacts are produced.
