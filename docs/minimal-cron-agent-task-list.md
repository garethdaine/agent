# Minimal Local Cron + Agent Runner Task List (Final Baseline)

## Progress Notes (2026-02-15)
- Fixed Discovery retry stalling at `discovering` with no new output/events: retry now resets discovery/setup phases to `setup` before re-queueing so `ExecuteInterrogationDiscoveryJob` can transition and run.
- Fixed Claude discovery CLI invocation for streamed output: `--verbose` is now included with `--output-format stream-json` under `--print` mode to satisfy CLI requirements.
- Fixed Claude discovery stream parsing for non-string payloads (`message/content` arrays), preventing `DISCOVERY_RUNTIME_EXCEPTION` / `Array to string conversion` crashes during discovery.
- Tightened Claude discovery activity parsing/output: suppress raw JSON/event usage dumps, emit concise tool progress summaries, and ignore empty stream messages so the Discovery Status card no longer fills with token/metadata noise.
- Discovery hardening follow-up:
  - ignore transient `<tool_use_error>Sibling tool call errored</tool_use_error>` status noise
  - constrain discovery prompt away from `vendor/`, `node_modules/`, `storage/`, and `bootstrap/cache/` to reduce tool churn
  - normalize malformed UTF-8 in interrogation event payloads before JSON persistence to prevent runtime failures while streaming
- Interrogation phase stability fixes:
  - replaced fallback runtime prompt source from `docs/interrogate.md` with a built-in non-interactive, schema-first prompt contract
  - prevented discovery CLI session reuse in first interrogation round (reset `cli_session_id` on phase transition to start interrogation fresh)
  - added `isSystemMessage` handling for interrogation round bootstrap/retry prompts so system bootstrap text is not recorded as a user answer event
  - updated Claude JSON parsing to unwrap CLI `type=result` envelopes (`structured_output` / `result` JSON-string) so question/summary parsing no longer fails with `ROUND_RESPONSE_PARSE_FAILED`
- Interrogation UX overhaul:
  - choice questions now render as clickable option cards (radio/checkbox style), not dropdowns
  - supports multi-select choice answers (`selected_options`) with server validation and answer-message handling
  - question presentation now de-duplicates option-heavy prompt text and avoids repeating options across the question card and answer controls
  - Q&A history now shows concise question previews instead of full long-form prompt dumps
  - reasoning presentation now renders as a structured panel (`Why this matters`, extracted `Key Considerations`, collapsible `View full reasoning`) instead of one oversized text block
  - second-pass interrogation visual redesign now uses a Claude-style card layout (large prompt typography, numbered selectable rows, inline "Something else" path, and cleaner action footer with explicit submit/skip controls)
  - visual polish follow-up aligns interrogation cards with the app's standard scale/style (normal typography, standard corner radii, and existing card spacing rhythm)
  - restored explicit skip-reason selection in interrogation answers and fixed skip submission to send the selected reason reliably
  - fixed interrogation event sequence race in `InterrogationEventWriter` by allocating sequence numbers under a session row lock, preventing duplicate `(session_id, sequence)` failures during concurrent round processing
  - fixed interrogation answer-linking so answer events persist `question_id`/answer metadata (choice, multi-choice, skip) instead of generic free-text only
  - fixed interrogation retry in phase 2 to resume on the latest unanswered question (no automatic "ask next question" when a pending question already exists)
  - fixed interrogation question prompt rendering to support safe markdown formatting (bold, inline code, lists, links) instead of showing raw markdown tokens
  - improved interrogation revision UX: Q&A history now explicitly selects a prior question for revision ("Answer/revise this question"), and the UI shows a clear "Return to latest question" state while editing earlier responses
  - improved prompt cleanup to strip inline option blocks from question markdown when structured `options` are already provided, preventing duplicated question+options rendering
  - de-duplicated Q&A history by `question_id` and sorted unresolved items first, preventing retry-induced repeated question rows from flooding the history panel
  - sanitized displayed interrogation reasoning to remove operational retry/meta preambles (for example re-issuing/id-mismatch control text) so reasoning stays relevant to the actual question
  - fixed Claude discovery tool-result error detection false-positive where PHP/code snippets containing identifiers like `ErrorEnvelope` were incorrectly surfaced as `Tool error`
  - added interrogation waiting-state spinners for initial/next-question generation in both question and answer panels
  - tightened question markdown cleanup to strip embedded option blocks (including `Option A/B/...` inline sections) when structured options are already shown in answer controls
  - stopped terminal completion notices from being tracked as unresolved questions by filtering completion-marker `question` payloads (`is_complete/progress=100`) across retry pending-question detection, Q&A history, and stats counts
  - improved Summary phase empty-state UX to show an explicit "Generating summary..." spinner while status is `summarizing`
  - upgraded summary markdown rendering: escaped newline payloads are normalized (for example `\n` to real line breaks) and markdown now renders as formatted HTML instead of raw `<pre>` text
  - refactored summary detail sections (`Goals`, `Constraints`, `Acceptance Criteria`, `Open Questions`, `Private Notes`) into expandable accordions for cleaner navigation on long summaries
  - added markdown table rendering support in interrogation/summary markdown presenter so pipe-table sections render as actual `<table>` blocks
  - added summary revision control actions: request amendments (with optional notes) and continue interrogation from summary to resolve remaining open questions before confirmation
  - summary confirmation is now gated when `open_questions` are still present, forcing explicit resolution flow instead of silently advancing to planning with unresolved ambiguity
  - fixed root-cause summary field leakage by tightening runner summary JSON schema requirements (`summary_markdown`, `goals`, `constraints`, `acceptance_criteria`, `open_questions`, `private_notes` all required) and adding explicit prompt constraints to forbid embedding arrays/tags inside markdown
  - added shared summary payload normalization at write/read/export boundaries to recover malformed legacy payloads and keep structured summary arrays canonical
  - improved summary markdown table readability with explicit header/cell/row styling (clear borders, striping, spacing) for faster scanning
  - improved private-notes rendering by normalizing enumerated prose markers (for example `(1)`, `(2)`) into markdown ordered-list structure before rendering
  - enforced single-question interrogation contract end-to-end (including continue-from-summary): runtime guard now rejects batched `Q1/Q2/...` payloads, attempts one automatic repair prompt, and fails fast with `ROUND_CONTRACT_VIOLATION` if the runner still returns batched/invalid question output
  - improved summary regeneration resilience after interrogation completion: summary job now attempts fallback generation with reconstructed transcript and a fresh non-resumed CLI context when initial summary command/parse fails
  - added open-question reconciliation against answered interrogation events so already-answered continuation questions are removed from `summary_json.open_questions` and no longer block summary confirmation as stale unresolved items

## Progress Notes (2026-02-13)
- Improved job command builder UX with explicit runner-aware permission profiles and flag visibility for both `codex` and `claude`, plus quick insertion of permission tokens into manual templates.
- Implemented the Requirements Discovery feature plan baseline (`docs/plans/requirements-discovery-feature.md`): schema/models/policy, interrogation services/jobs/events/API/routes, Tools navigation/routes, wizard UI components, and initial integration/config tests.
- Added Discovery session recovery controls: UI delete/restore actions and new per-session retry endpoint (`POST /agent/api/v1/interrogation/sessions/{id}/retry`) that clears failure state and re-queues work based on current phase.

## Progress Notes (2026-02-12)
- Phases 0-7 are now complete against the current baseline.
- Enabled Sanctum stateful API middleware bootstrap so logged-in Inertia sessions authenticate correctly on `/agent/api/v1/*`.
- Added create/edit form-level API error banners so non-field failures (for example `401 UNAUTHENTICATED`) are shown directly in the UI.
- Added Monitor approval handling UX: detects approval-needed output and offers UI actions to approve (reconfigure + rerun) or deny (stop run), with runner-aware templates for `codex` and `claude` and guarded handling for `custom`.
- Fixed PostgreSQL run finalization regression where fractional `duration_ms` could crash runner transitions, leaving runs stuck and causing scheduler heartbeat degradation.
- Improved approval UX to per-run alerts in Latest Runs with modal actions, and clear approval-required state when runs become terminal so killed/failed runs no longer show stale approval banners.
- Reconciliation now uses immutable launch fingerprints captured at run start, preventing false `pid_reused_or_mismatch` failures after job template/path edits.
- Reconciliation fingerprint matching now includes launch-time executable token/configured executable candidates, avoiding false mismatches for symlinked CLIs (for example `claude`).
- Monitor polling now guards against overlapping refresh calls and de-duplicates events by ID, preventing duplicate lifecycle rows in Event Tail.
- Monitor Event Tail now surfaces a clear "active but no stdout/stderr yet" signal for long-silent runs.
- Fixed long-running run redelivery failures by aligning Redis queue `retry_after` (`90000`) above Horizon agent timeout, preventing `MaxAttemptsExceededException` on in-flight runs.
- Added Phase 9 dashboard metrics endpoint + UI cards with window selector (`24h`/`7d`), skipped-run exclusion for success-rate, and global queued-age calculation.
- Added `agent:benchmark-slo` command to seed/load benchmark data and measure endpoint p95 with 200 repeated requests.
- Phase 9 SLO benchmark validation run (2026-02-12 UTC) on seeded dataset (`100 jobs`, `2000 runs`, `100000 events`) passed:
  - jobs index p95: `16.52ms` (`<500ms`)
  - run-now ACK p95: `9.59ms` (`<400ms`)
  - monitor poll p95: `25.41ms` (`<700ms`)
- Phase 10 acceptance/sign-off artifacts are now complete:
  - mandatory acceptance scenarios are covered by feature tests and passing
  - endpoint status-code contract coverage includes `200/201/202/401/404/409/422/429/503`
  - release artifacts published under `docs/release/` (`phase10-test-report.md`, logs, screenshots)
- Added usage/rate-limit management hardening:
  - output-level rate-limit detection with parsed reset time metadata
  - failed rate-limited runs now emit `error_code=RATE_LIMITED`
  - temporary per-job hold state persisted in `agent_system_state` to suppress repeated scheduled failures until reset/default hold
  - scheduler dispatch now records skipped runs with `skip_reason=rate_limited`
  - run-now now returns `409 JOB_RATE_LIMITED` while hold is active (with optional `ignore_rate_limit_hold=true` override)
  - monitor UI now surfaces rate-limit alerts and allows explicit "Run Anyway Now" override
- Improved path/template UX hardening:
  - configurable additional allowed directory bases via `.env` (`AGENT_ADDITIONAL_WORKING_DIRECTORY_BASES`, `AGENT_ADDITIONAL_TASK_MARKDOWN_BASES`)
  - path inputs are trimmed server-side before validation/save
  - paths containing spaces are accepted when within allowed base restrictions
  - job form now includes interactive command-template presets + placeholder token insertion
- DB compatibility smoke checks were executed on all supported engines:
  - SQLite (`php artisan test` / in-memory)
  - PostgreSQL (`php artisan migrate:fresh --database=pgsql --force`)
  - MySQL (`DB_PORT=3306 php artisan migrate:fresh --database=mysql --force --no-interaction`)
- Phase 2 validation guardrails are implemented behind `POST /agent/api/v1/jobs` and covered by feature tests.
- Phase 8 is now complete:
  - immutable audit entries for mutating actions
  - structured log mirroring
  - unified `agent:prune` command (`--runs --events --jobs --audit`, dry-run, JSON)
  - chunked prune checkpoints + scheduled maintenance windows
- Baseline phases and post-baseline operational hardening items above are implemented.

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
- [x] Configure Horizon with dedicated `agent` queue (`tries=1`, `backoff=0`, `timeout=86500`, `retry_after=90000`, `maxProcesses` default `2`, bounds `1..8`).
- [x] Enforce fixed queue binding in runtime config: `connection=redis`, `queue=agent`.
- [x] Confirm canonical run mode in docs and scripts: manual `php artisan horizon` + manual `php artisan schedule:work`.
- [x] Add `dragonmantank/cron-expression` (major v3 pinned).
- [x] Validate DB compatibility for SQLite, MySQL, and PostgreSQL.

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
- [x] Add all required columns and enums from final baseline, including:
- [x] soft-delete for jobs
- [x] run statuses with `stopping` and `skipped`
- [x] `due_window_utc_minute`, `initiated_by_user_id`, `user_id` snapshot, `metadata_json`, `resolved_executable_path`
- [x] Enforce required unique/index constraints:
- [x] scheduled-run idempotency key unique
- [x] event sequence unique per run
- [x] filtered uniqueness for active job names per user
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
- [x] Implement `agent:dispatch-due`.
- [x] Add scheduler registration with overlap lock and target lock TTL 120s.
- [x] Implement timezone-aware due evaluation and UTC minute normalization.
- [x] Implement watermark state in `agent_system_state` (`dispatch_last_minute_utc`) with transactionally consistent update.
- [x] Implement bounded backfill:
- [x] global lookback cap 15 minutes/tick
- [x] max 5 due windows/job/tick
- [x] max 20 runnable dispatches/tick
- [x] deterministic ordering (`due_window_utc_minute ASC`, `agent_job_id ASC`)
- [x] Implement first-tick behavior (current minute only).
- [x] Implement rollback clock clamp behavior.
- [x] Implement deferred-capacity audit records when global cap is reached.
- [x] Write heartbeat and heartbeat metadata each tick.

Definition of done:
- Scheduled runs dispatch once per due window idempotency key.
- Backfill and cap behavior matches baseline under delayed tick scenarios.
- Heartbeat and watermark behavior is correct under normal and clock-skew cases.

## Phase 4: Runner Lifecycle, Process Control, and Reconciliation
- [x] Implement queued runner job with argv token execution only.
- [x] Implement lifecycle transitions and atomic transition enforcement.
- [x] Implement process start/finish semantics for `started_at`, `pid`, `duration_ms`, terminal mapping.
- [x] Implement overlap/cooldown skip-run creation semantics for scheduled runs.
- [x] Implement cooldown rules against prior non-skipped `finished_at`.
- [x] Implement timeout (monotonic clock) and stop escalation (`SIGTERM` + 10s + `SIGKILL`).
- [x] Implement stop idempotency and terminal-race resolution (first terminal CAS wins).
- [x] Implement `pid_missing` and `TERMINATION_FAILED` handling.
- [x] Implement dispatch-time executable re-validation and failed-run behavior on mismatch.
- [x] Implement runtime path re-check and `RUN_PATH_NOT_FOUND`.
- [x] Implement auto-disable after 3 consecutive scheduled `RUN_PATH_NOT_FOUND` failures.
- [x] Ensure counter resets on any successful run and ignores manual-path-failure increments.
- [x] Implement reconciliation on scheduler boot + each tick with PID/fingerprint matching.

Definition of done:
- Terminal outcomes, stop behavior, timeout behavior, and reconciliation outcomes match baseline matrix and race rules.

## Phase 5: Stream Capture, Redaction, and Event Contracts
- [x] Implement stdout/stderr line-buffering with 250ms flush and 4KB chunking.
- [x] Implement long-line hard split with `payload.continuation`.
- [x] Implement sequence allocation strategy (strictly increasing unique per run; gaps allowed).
- [x] Implement UTF-8 normalization and highly-binary chunk summarization.
- [x] Implement max payload split behavior (8192-byte post-redaction limit).
- [x] Implement combined output cap 5MB with one truncation notice and `output_truncated` metadata.
- [x] Implement degraded capture mode and persistent failure threshold logic.
- [x] Implement required redaction patterns/tokens and precedence ordering.
- [x] Track pre/post redaction byte counters and replaced segment count.
- [x] Implement lifecycle payload schema variants and `event_ts` format.

Definition of done:
- Event stream contract, ordering, truncation, and redaction behavior are stable and test-verified.

## Phase 6: Versioned API and HTTP Contracts
- [x] Implement versioned JSON API under `/agent/api/v1/...`.
- [x] Remove non-versioned legacy JSON API routes.
- [x] Return `404` for unsupported/non-goal API surfaces.
- [x] Ensure all responses include `X-Agent-Api-Version: 1.0`.
- [x] Implement standard error envelope for all failure classes.
- [x] Implement endpoint contracts and required statuses, including:
- [x] `run now` overlap conflict (`409 RUN_OVERLAP_ACTIVE`)
- [x] idempotent run-now replay (`202` + `idempotent_replay=true`)
- [x] stop accepted/no-op/conflict paths
- [x] queue unavailable path (`503 QUEUE_UNAVAILABLE`)
- [x] owner access to run detail/events remains valid even when parent job is soft-deleted.
- [x] Implement rate limiting for mutating endpoints (30/min/user shared) with `429 RATE_LIMITED`.
- [x] Exclude scheduler/maintenance system actions from HTTP rate limits.

Definition of done:
- All endpoint response shapes/statuses/headers match baseline and are covered by tests.

## Phase 7: Jetstream/Inertia UX
- [x] Implement Jobs index with required columns, filters, sorting, pagination.
- [x] Implement Create/Edit forms with all validation and confirmation UX requirements.
- [x] Implement Deleted tab/filter and restore UX behavior.
- [x] Implement Monitor page with:
- [x] latest runs cap 50
- [x] default range 24h
- [x] active/inactive/hidden-tab polling cadence (2s/10s/15s)
- [x] retry backoff and failure banner behavior
- [x] auto-follow tail pause/resume behavior
- [x] de-duplicate event stream merges and serialize poll cycles to avoid duplicated lifecycle output
- [x] queue lag warning thresholds
- [x] Implement scheduler health card with `unknown/healthy/degraded/down`.
- [x] Ensure Inertia pages consume the same versioned JSON API under `/agent/api/v1` (no parallel data-loading contract).

Definition of done:
- End-to-end workflow is achievable from UI and matches behavior contracts exactly.

## Phase 8: Audit, Retention, and Maintenance
- [x] Implement immutable audit logging for mutating actions only.
- [x] Mirror audit entries to structured Laravel logs.
- [x] Enforce owner-scoped audit access policies.
- [x] Implement unified prune command with domain flags (`--runs --events --jobs --audit`).
- [x] Implement `--dry-run` output modes (human default, JSON with `--json`).
- [x] Implement chunked prune with checkpoints in `agent_maintenance_checkpoints`.
- [x] Implement prune retention rules:
- [x] runs >=30 days and keep last 20/job
- [x] failed/timed_out >=30 days
- [x] events >=7 days except active runs
- [x] deleted jobs hard-pruned after 30 days with cascading children
- [x] Schedule prune jobs at:
- [x] 03:10 local app time (runs/events)
- [x] 03:20 local app time (deleted jobs)
- [x] Ensure prune actions emit system audit entries.

Definition of done:
- Retention and prune semantics are reproducible, resumable, and match baseline constraints.

## Phase 9: Dashboard Metrics and Performance Verification
- [x] Implement global dashboard cards for runs today, success rate, average duration, backlog count, oldest queued age, scheduler health.
- [x] Implement metric formulas and windows (default 24h, selectable 7d) with skipped-run exclusion for success rate.
- [x] Ensure backlog oldest queued age is computed globally (not per-job).
- [x] Build benchmark command/script for SLO verification dataset:
- [x] 100 jobs, 2000 runs, 100k events
- [x] 200 repeated requests per endpoint
- [x] Validate p95 targets:
- [x] jobs index <500ms warm
- [x] run-now ACK <400ms
- [x] monitor poll <700ms (`limit=100`, up to 5 active monitors)

Definition of done:
- Manual release checklist includes passing performance measurements for all required SLOs.

## Phase 10: Acceptance and Sign-Off Artifacts
- [x] Execute mandatory tests:
- [x] DST spring-forward skip
- [x] DST fall-back double window
- [x] overlap/cooldown precedence
- [x] delayed scheduler bounded backfill
- [x] stop/timeouts races and idempotent stop
- [x] duplicate dispatch idempotency
- [x] soft-delete/restore/hard-prune paths
- [x] Validate endpoint code coverage for `200/201/202/401/404/409/422/429/503`.
- [x] Produce required sign-off artifacts:
- [x] test report
- [x] UI screenshots
- [x] command/runtime logs

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
- [x] Job CRUD/toggle/run-now/stop works with ownership and validation.
- [x] Scheduler dispatches due windows once with bounded backfill and idempotency.
- [x] Runner lifecycle/status transitions are correct and observable.
- [x] Live monitor polling and event stream behavior meet contract.
- [x] Heartbeat health states are correct, including `unknown`.
- [x] Redaction and secret-handling policies are enforced.
- [x] Prune/retention policies execute correctly with dry-run support.
- [x] Versioned API contract and headers are enforced.
- [x] Required release artifacts are produced.

## Build Layer Delivery Checklist

- [x] Add interrogation build endpoints (`generate-build-tasks`, `start-build`, `pause-build`, `resume-build`, `build/clarify`)
- [x] Add build task generation job (`GenerateInterrogationBuildTasksJob`)
- [x] Add build orchestration job (`ExecuteInterrogationBuildJob`)
- [x] Add run factory for build tasks (`BuildTaskRunFactory`)
- [x] Extend session payload with build summary, active task/run, approval and rate-limit signals
- [x] Add planning UI build panel with task status list, active run log tail, clarification input, and controls
- [x] Add export feedback state for plan export actions in planning UI
- [x] Add feature tests for build endpoint workflow
- [x] Add unit tests for build generation and orchestration jobs
