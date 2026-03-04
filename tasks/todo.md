# Agent Platform — Task Log

> Active project status: see `docs/PROJECT-STATUS.md`

---

## Current — Open Items

### Session 20 Discovery — Native Research + Grounded Answer Brief (Completed)

- [x] Define a native Perplexica-inspired research subsystem as a first-class bounded context inside Agent.
- [x] Specify modular pipeline architecture (query planning, retrieval, acquisition, chunking, ranking, grounded generation, streaming).
- [x] Map the subsystem to current Agent Ops primitives (jobs, runs, events, artifacts, queue workers).
- [x] Publish implementation brief with phased delivery, acceptance criteria, and verification gates.

Review
- Created discovery brief: `docs/discovery/native-research-grounded-answer-integration-brief.md`.
- Brief includes:
  - A Laravel-native `Research` bounded context with domain primitives (`ResearchJob`, `Source`, `Document`, `EvidenceChunk`, `EvidencePack`, `GroundedAnswer`).
  - Clear service contracts for query pipeline, pluggable search providers, document extraction, chunk/index lifecycle, hybrid ranking, and citation-enforced generation.
  - Scheduler integration design for a reusable `ResearchStep` usable by any workflow/agent task.
  - Production controls copied from Perplexica-style UX knobs (`vertical`, `mode`, history-aware query expansion, diversity constraints, multi-layer cache).
  - A pragmatic build order, testing matrix, operational metrics, and definition-of-done gates.

### Session 19 Discovery — Credentials Manager Integration Brief (Completed)

- [x] Define a centralized credential domain model for API keys, OAuth tokens, secrets, and provider metadata.
- [x] Specify encryption/decryption design with key versioning, rotation workflow, and auditability requirements.
- [x] Document OAuth lifecycle handling (connect, refresh, revoke, expiry, failure recovery) for extensible provider integrations.
- [x] Publish implementation brief with phased delivery plan and release acceptance criteria.

Review
- Created discovery brief: `docs/discovery/credentials-manager-integration-brief.md`.
- Brief includes:
  - A provider-agnostic credentials architecture (`CredentialStore` contract + encrypted repository + provider driver registry).
  - Secure storage and cryptography requirements (envelope encryption, key versions, redaction, access policy, audit logs).
  - OAuth-specific lifecycle orchestration and background refresh strategy for multi-provider integrations.
  - Extensibility rules so new providers can be added without core schema/service rewrites.
  - Phased implementation roadmap, testing matrix, and definition-of-done criteria.

### Session 18 Discovery — n8n Workflow Automation Integration Brief (Completed)

- [x] Review Agent integration surfaces and constraints (jobs/runs/events/auth/token model).
- [x] Review official n8n docs for workflow execution, webhooks, API, queue mode, and platform constraints.
- [x] Draft a concise feature brief covering fit, benefits, MVP scope, architecture, and expansion roadmap.
- [x] Publish brief in project docs and log review notes.

Review
- Created discovery brief: `docs/discovery/n8n-workflow-automation-integration-brief.md`.
- Brief includes:
  - Strategic fit with existing Agent architecture (`/agent/api/v1` jobs/runs/events + token-based integrations).
  - Concrete MVP design for outbound Agent events to n8n and inbound n8n action calls into Agent.
  - Security, reliability, and operational guardrails (signatures, idempotency, retries, rate limits, audit logs).
  - Phased expansion roadmap for templates, approval loops, and deeper orchestration.
  - Official n8n source links (docs + site) for capability and constraint validation.

### Session 17 Hotfix — Snapshot Drift False Positives + Resume Unblock (Completed)

- [x] Prevent snapshot drift pauses when only generated run-output paths (`tasks/`, `docs/`) changed.
- [x] Ensure paused drift sessions can resume without getting stuck in a drift pause loop.
- [x] Add regression tests for tolerated drift paths and drift-resume behavior.
- [x] Run targeted backend tests + frontend build.

Review
- Drift-tolerant change detection:
  - Added `repo_analysis.scan.drift_tolerated_paths` in `config/repo_analysis.php` (default includes `tasks/`, `docs/`; env override via `REPO_ANALYSIS_DRIFT_TOLERATED_PATHS`).
  - Updated `app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php` drift pause logic to compare manifest file hashes and skip `SNAPSHOT_DRIFT_DETECTED` when all changed paths are within tolerated directories.
- Resume unblock:
  - Updated `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php` resume flow to auto-set `metadata_json.drift_decision=continue_old_snapshot` and clear operator-action drift flags when resuming drift-paused sessions.
- Regression coverage:
  - Added integration test for tolerated-path drift handling in `tests/Integration/RepoAnalysis/RepoAnalysisExecutionPipelineTest.php`.
  - Added API lifecycle test for drift resume decision handling in `tests/Feature/Api/V1/RepoAnalysis/RepoAnalysisApiLifecycleTest.php`.
  - Extended config coverage for drift tolerated paths in `tests/Unit/Config/RepoAnalysisConfigTest.php`.
- Verification:
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` (pass)
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest` (pass)
  - `php artisan test --filter=RepoAnalysisConfigTest` (pass)
  - `npm run build` (pass)

### Session 17 Design Update — Rate Limit Modal Spacing + In-App Confirm Dialogs (Completed)

- [x] Add top padding offset for the rate-limit modal shown in Monitor.
- [x] Introduce a shared in-app confirmation dialog utility + global host in the app layout.
- [x] Replace all frontend `confirm()` / `window.confirm()` browser modal usages with in-app confirmation dialogs.
- [x] Verify no browser confirm calls remain in `resources/js` and run frontend build.

Review
- Rate limit modal spacing:
  - Updated `resources/js/Pages/Agent/Monitor/Index.vue` to anchor the rate-limit modal from the top with added top padding (`items-start` + `pt-16 sm:pt-20`) for clearer vertical breathing room.
- In-app confirm modal system:
  - Added shared state/service at `resources/js/Support/confirmDialog.js`.
  - Added global modal host component `resources/js/Components/AppConfirmDialog.vue`.
  - Mounted host once in `resources/js/Layouts/AppLayout.vue`.
- Browser confirm migration:
  - Replaced all frontend browser confirm usages in:
    - `resources/js/Pages/Messenger/DeadLetters/Index.vue`
    - `resources/js/Pages/Messenger/DeadLetters/Show.vue`
    - `resources/js/Pages/Agent/Delegation/ProfileIndex.vue`
    - `resources/js/Pages/Tools/CodeAnalysis/Index.vue`
    - `resources/js/Pages/Tools/Messenger/Index.vue`
    - `resources/js/Pages/Tools/Discovery/Wizard.vue`
    - `resources/js/Pages/Tools/Discovery/Index.vue`
    - `resources/js/Components/Interrogation/BuildPanel.vue`
    - `resources/js/Pages/Agent/Org/Agents/Index.vue`
    - `resources/js/Pages/Agent/Org/Councils/Index.vue`
    - `resources/js/Pages/Agent/Org/Rituals/Index.vue`
- Verification:
  - `rg -n --glob '*.vue' "(window\\.)?(alert|confirm)\\s*\\(" resources/js` -> no matches.
  - `npm run build` -> pass (client + SSR builds).

### Session 17 Follow-up — Monitor Modal Alignment + Internal Header Padding (Completed)

- [x] Keep modal overlay centered in viewport for rate-limit modal.
- [x] Add additional top padding inside Monitor modal card content so titles are not visually flush.
- [x] Keep styling consistent across Monitor modals.
- [x] Run frontend build verification.

Review
- Updated `resources/js/Pages/Agent/Monitor/Index.vue`:
  - Restored rate-limit modal overlay alignment to centered (`items-center justify-center`).
  - Increased modal card content padding from `p-5` to `p-6 pt-7` for retry, approval, rate-limit, and clarification modals.
- Verification:
  - `npm run build` -> pass (client + SSR builds).

### Session 17 Follow-up 2 — Global Modal Standardization + Center Alignment (Completed)

- [x] Audit all modal instances and isolate non-standard custom overlays.
- [x] Replace Monitor custom modals with standard Jetstream modal styling.
- [x] Ensure all Jetstream modals are centered in viewport.
- [x] Verify modal usage consistency and run frontend build.

Review
- Modal audit:
  - Non-standard custom modal overlays existed only in `resources/js/Pages/Agent/Monitor/Index.vue` (retry, approval, rate-limit, clarification).
  - Remaining overlay at `resources/js/Components/NotificationDrawer.vue` is a side drawer backdrop, not a modal dialog.
- Styling standardization:
  - Replaced all four Monitor custom overlays with Jetstream `ConfirmationModal` usage.
- Center alignment:
  - Updated base `resources/js/Components/Modal.vue` layout to center modal panels (`flex min-h-full items-center justify-center`), applying globally to Dialog/Confirmation modals.
- Verification:
  - `rg -n "fixed inset-0 z-\\d+.*bg-black/50" resources/js --glob '*.vue'` (only notification drawer backdrop remains).
  - `npm run build` -> pass (client + SSR builds).

### Session 17 Follow-up 3 — Task Graph Label Cleanup + Analyzer/Dependency Badges (Completed)

- [x] Remove hash IDs from Task display labels in task graph table.
- [x] Convert Task/Analyzer/Dependency labels from snake_case to title case.
- [x] Render Analyzer as color-coded badges with deterministic per-analyzer colors.
- [x] Render Depends On as color-coded badges using the same analyzer color mapping.
- [x] Verify frontend build.

Review
- Updated `resources/js/Components/CodeAnalysis/TaskGraphPanel.vue`:
  - Added label normalization helpers:
    - strip `:<hex-id>` suffix from task keys.
    - convert snake_case labels to title case with acronym handling (`AI`, `API`, `UI`, `ID`).
  - Added deterministic analyzer color palette + hash-based class selection so each analyzer key gets a stable badge color.
  - Task column now displays cleaned title-cased task labels (no appended hash IDs).
  - Analyzer column now uses colored badges instead of plain muted text.
  - Depends On column now uses per-dependency colored badges and a `+N more` badge for overflow.
- Verification:
  - `npm run build` -> pass (client + SSR builds).

### Session 2 Hotfix — AI Task Timeout Root-Cause and Recovery Hardening (Completed)

- [x] Collect and record runtime evidence for the failed `ai_overview` task in session `2` (failed_jobs + task/event timeline + queue timeout path).
- [x] Implement timeout hardening: split AI process timeout from queue worker timeout with an explicit buffer and safer defaults.
- [x] Implement AI session continuity hardening: persist runner `cli_session_id` during stream processing so retries can resume prior AI context.
- [x] Add/adjust automated tests for timeout config semantics and summary/session-id parsing continuity.
- [x] Run focused verification suite and document outcome in Review.

Review
- Root cause:
  - Session `2` failed at `2026-03-03 10:42:14 UTC` with `Illuminate\Queue\TimeoutExceededException` after exactly `1200s` while `ai_overview` was still streaming progress events.
  - Queue worker timeout and AI process timeout were both set to `1200s`, so the worker hard-killed the job before graceful timeout handling.
  - AI summary parsing did not reliably persist `cli_session_id`; retries therefore lacked robust resume context continuity.
- Fix summary:
  - Introduced explicit timeout model in config:
    - `repo_analysis.ai.task_timeout_seconds` default `3600`,
    - `repo_analysis.ai.queue_timeout_buffer_seconds` default `180`,
    - queue supervisor timeout now defaults to `task_timeout + buffer` (with floor/ceiling guards).
  - Updated Horizon code-analysis supervisor default timeout derivation to match the buffered model.
  - Updated `ExecuteRepoAnalysisTaskJob` to:
    - resolve queue timeout from supervisor/buffered config,
    - map `ProcessTimedOutException` to `EXECUTE_TASK_TIMEOUT` (task + session + event) instead of generic non-retryable failure.
  - Updated `AiTaskRunner` to persist `ai_cli_session_id` during stream processing whenever a runner session id appears, and reuse it in final result metadata.
  - Updated Codex/Claude summary parsing to preserve `cli_session_id` from structured/result envelopes (including top-level `session_id` propagation).
- Verification:
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` (pass, includes new process-timeout mapping and timeout buffer test)
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest` (pass)
  - `php artisan test --filter=RepoAnalysisConfigTest` (pass)
  - `php artisan test --filter=InterrogationCodexAdapterCommandTest` (pass)
  - `php artisan test --filter=InterrogationClaudeAdapterStructuredOutputTest` (pass)

### Session 16 Hotfix — Purge Sessions and Realtime Status Reliability (Completed)

- [x] Add permanent purge API endpoint for Code Analysis sessions that force-deletes the session and all related records.
- [x] Ensure purge cleans up session-linked exported report/docs files and artifact storage paths with path safety guards.
- [x] Add Code Analysis index UI action for permanent delete with explicit confirmation.
- [x] Improve Code Analysis realtime delivery by switching session update broadcasts to immediate mode.
- [x] Add Echo subscriptions in Code Analysis index to live-update row status/phase/updated timestamps.
- [x] Add feature test coverage for purge behavior and file cleanup.
- [x] Verify with focused backend tests and frontend production build.

Review
- Root cause:
  - Existing delete endpoint only soft-deleted sessions, leaving connected records and exported files intact.
  - Code Analysis realtime events used queued broadcasting, which could lag/fail when broadcast queue workers were not active.
  - Code Analysis index view had no Echo subscription, so session list rows remained stale without manual refresh.
- Fix summary:
  - Added `/agent/api/v1/code-analysis/sessions/{id}/purge` for irreversible cleanup.
  - Purge now force-deletes session graph and removes linked report/artifact files inside approved roots.
  - Switched `RepoAnalysisSessionUpdated` to `ShouldBroadcastNow`.
  - Added index-page Echo listeners per session channel and a realtime availability banner.
- Verification:
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest` (pass, includes purge coverage)
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` (pass)
  - `npm run build` (pass)

### Session 16 Hotfix — Restore Wizard Lifecycle Controls and Fix Paused Retry Dead-End (Completed)

- [x] Restore lifecycle actions in Code Analysis wizard (`Pause`, `Resume`, `Retry Session`, `Restart`) without reintroducing `Run Next Step`, Coverage card, Artifacts card, or Event Stream card.
- [x] Fix `retry-task` API path so phase-3 sessions in `paused|failed` transition back to `executing` before dispatching execution.
- [x] Clear `task_retry_decision_required` operator-action metadata when retrying a failed task.
- [x] Add/adjust API lifecycle test coverage for paused-session task retry behavior.
- [x] Verify with focused backend tests and frontend production build.

Review
- Root cause:
  - Wizard simplification removed the whole session action surface, including required lifecycle controls.
  - `retry-task` dispatched execute jobs while session stayed `paused`, so execute jobs no-op'd at guard checks.
- Fix summary:
  - Reintroduced a compact `Session State` card with lifecycle buttons.
  - `retry-task` now resumes/retries session state first (phase 3), then dispatches execute.
- Verification:
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest` (pass)
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` (pass)
  - `npm run build` (pass)

### Session 16 Task 10 — Wire Web Routes, Inertia Pages/Components, Navigation Discoverability, and Realtime UX (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis backend/API, lifecycle jobs, and deterministic event sequencing exist, but there are no user-facing web routes/pages/components under Tools for Code Analysis.
- Tools landing currently has no Code Analysis discoverability card/link, so operators cannot reach this flow from `/tools` without manual URL entry.
- Existing Discovery wizard already demonstrates websocket + polling patterns, but Code Analysis has no equivalent UI or stale-client supersede handling yet.
- Code Analysis feature flag defaults to disabled; UI exposure must be permission-aware and provide an actionable blocked message when feature access is unavailable.

TASK
- Ship Code Analysis user/operator surfaces under Tools with route wiring and navigation discoverability.
- Ensure authorized users can reach index/create/wizard/settings from Tools, open sessions from list to wizard, and see lifecycle actions based on role/state.
- Implement ordered realtime + polling fallback event ingestion so event sequence appends correctly and stale client state is superseded by server truth.
- Prove behavior with tests-first workflow (fail then pass) for navigation visibility, route reachability, action visibility, and sequence append ordering.

ACTION
- [x] Add failing web/UI tests first:
  - [x] `tests/Feature/Web/Tools/RepoAnalysisNavigationTest.php` for Tools card visibility, route reachability, wizard link discoverability, and authorization visibility.
  - [x] JS unit test for sequence merge/poll cursor behavior to prove ordered append and dedupe.
- [x] Run targeted tests and capture red-state evidence.
- [x] Implement minimum route/UI changes:
  - [x] web routes and Inertia bindings for `tools.code-analysis.{index,create,wizard,settings}`,
  - [x] Tools index Code Analysis card visibility with feature + authorization gating,
  - [x] Code Analysis pages/components under `resources/js/Pages/Tools/RepoAnalysis/*` and `resources/js/Components/RepoAnalysis/*`,
  - [x] wizard lifecycle controls (`pause/resume/retry/restart/export`) with state/role-based visibility.
- [x] Implement websocket subscription + polling fallback with strict sequence merge and stale-state supersede handling.
- [x] Re-run targeted tests, then run required verification commands, and document evidence/limitations in this section.

RESULT
- Completion is proven by fail-then-pass test evidence showing:
  - authorized users see Code Analysis in Tools and can reach index/create/wizard/settings without manual URL entry,
  - session list links to wizard and action controls are visible/hidden by status/ownership/admin override rules,
  - event ingestion appends ordered by sequence with dedupe and polling cursor progression,
  - websocket-unavailable fallback continues via polling without sequence regressions,
  - unauthorized/disabled surface is hidden or blocked with an actionable message.

Assumptions and scope boundaries
- Assumption: Code Analysis APIs remain at `/agent/api/v1/code-analysis/*` and are the data source for these pages.
- Assumption: “authorized user” means authenticated user with Code Analysis feature enabled and `create/view` policy permission.
- Scope boundary: this task targets web routes, Inertia pages/components, navigation discoverability, and frontend realtime/polling behavior; it does not redesign backend lifecycle semantics.

Failure modes to guard
- Malicious-caller mode: non-owner users attempt to access owner sessions via direct wizard URL; UI must not expose unauthorized actions and server errors should be surfaced with guidance.
- Tired-maintainer mode: duplicate/out-of-order event merges from websocket + polling lead to incorrect wizard state; sequence ordering and cursor handling must remain deterministic.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state:
    - `php artisan test tests/Feature/Web/Tools/RepoAnalysisNavigationTest.php` failed with missing props/routes (`repoAnalysis.available` missing; `tools.code-analysis.*` routes undefined).
    - `npm run test:unit -- resources/js/Pages/Tools/RepoAnalysis/__tests__/eventStream.spec.ts` failed with missing module `../eventStream`.
  - Implemented:
    - Web route wiring and access gating in `routes/web.php` for `tools.code-analysis.index/create/wizard/settings`.
    - Tools card discoverability + gating props in `resources/js/Pages/Tools/Index.vue`.
    - New pages:
      - `resources/js/Pages/Tools/RepoAnalysis/Index.vue`
      - `resources/js/Pages/Tools/RepoAnalysis/Create.vue`
      - `resources/js/Pages/Tools/RepoAnalysis/Wizard.vue`
      - `resources/js/Pages/Tools/RepoAnalysis/Settings.vue`
    - New components:
      - `resources/js/Components/RepoAnalysis/TaskGraphPanel.vue`
      - `resources/js/Components/RepoAnalysis/CoveragePanel.vue`
      - `resources/js/Components/RepoAnalysis/ReportViewer.vue`
      - `resources/js/Components/RepoAnalysis/ArtifactInspector.vue`
    - Ordered merge utilities + tests:
      - `resources/js/Pages/Tools/RepoAnalysis/eventStream.js`
      - `resources/js/Pages/Tools/RepoAnalysis/__tests__/eventStream.spec.ts`
    - Realtime broadcast channel/event wiring:
      - `app/Events/RepoAnalysisSessionUpdated.php`
      - `app/Support/RepoAnalysis/EventWriter.php`
      - `routes/channels.php`
    - Tools nav active-route coverage update:
      - `resources/js/Layouts/AppLayout.vue`
  - Green state:
    - `php artisan test tests/Feature/Web/Tools/RepoAnalysisNavigationTest.php` => `4 passed (122 assertions)`.
    - `npm run test:unit -- resources/js/Pages/Tools/RepoAnalysis/__tests__/eventStream.spec.ts` => `3 passed`.
    - `php artisan test --filter=RepoAnalysis` => `63 passed (422 assertions)`.
    - `npm run build` completed client + SSR builds successfully.
- Conditions where this works:
  - Code Analysis feature flag is enabled (`repo_analysis.enabled=true`) for UI route access.
  - User is authenticated and policy-authorized (`create/view/update`) for the session.
  - Realtime uses `code-analysis.{sessionId}` private channel when Echo is available; otherwise polling fallback continues.
  - Event merge correctness assumes monotonic `sequence` values from backend contract.
- Explicit non-goals / limitations:
  - Composer dev check could not run end-to-end because local script calls `php artisan pail`, which is unavailable in this environment; command exited before full stack remained up.
  - Browser login flow against local serve instance returned `419 Page Expired`, so manual click-through verification could not be completed via Playwright; route-level reachability and navigation flow are covered by passing feature tests.

### Session 16 Task 4 — Implement Sequenced Event Writer and Event Read Contract (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis schema/models exist (`repo_analysis_sessions`, `repo_analysis_events`) and transition service is in place, but there is no `EventWriter` for Code Analysis.
- There is currently no Code Analysis events read endpoint/helper implementing incremental `since_sequence` retrieval with strict ordering.
- Existing event writers in the codebase show two relevant patterns:
  - deterministic, transaction-safe per-session sequencing + redaction/UTF-8 normalization (`InterrogationEventWriter`),
  - lightweight per-scope sequence append (`DelegationEventWriter`).

TASK
- Add a Code Analysis `EventWriter` that performs append-only event writes with:
  - monotonic per-session sequence assignment under transaction lock,
  - payload UTF-8 normalization + secret redaction before persistence,
  - a broadcast hook using the same normalized payload and assigned sequence used for storage.
- Add a Code Analysis event read contract helper for ordered incremental fetch using `since_sequence` semantics.
- Prove behavior with test-first coverage for sequence monotonicity, normalization/redaction, and ordered incremental retrieval including empty results.

ACTION
- [x] Add failing tests first:
  - [x] `tests/Unit/Support/RepoAnalysis/EventWriterTest.php` for monotonic sequence assignment, stale writer safety, normalization/redaction, nested payload handling, and broadcast parity.
  - [x] Include incremental read tests (`since_sequence` ordering and empty result behavior) via writer/read helper unit coverage.
- [x] Run `php artisan test --filter=EventWriterTest` and capture red-state evidence.
- [x] Implement `app/Support/RepoAnalysis/EventWriter.php` with:
  - [x] transactional lock + sequence assignment (`max(sequence)+1` per session under lock),
  - [x] recursive UTF-8 normalization and key/value sanitization,
  - [x] nested secret redaction,
  - [x] broadcast hook invocation with storage-equivalent envelope.
- [x] Implement minimal read contract helper (model scope/service method) for `since_sequence` ordered retrieval.
- [x] Re-run `php artisan test --filter=EventWriterTest` and confirm green.
- [x] Record review evidence, correctness conditions, non-goals, and limitations in this section.

RESULT
- Completion is proven by fail-then-pass test evidence showing:
  - strict per-session sequence monotonicity with no gaps/duplicates from writer behavior,
  - malformed UTF-8 payload content normalized for stored JSON,
  - secret values redacted including nested payload keys/values,
  - incremental reads return strictly ordered events after `since_sequence`,
  - incremental reads return an empty result set when no new events exist.

Assumptions and scope boundaries
- Assumption: this task is backend-only for Code Analysis event write/read contracts and does not require full Code Analysis controller/routing rollout yet.
- Assumption: sequence uniqueness is guaranteed by both transactional assignment and existing DB unique constraint on `(repo_analysis_session_id, sequence)`.
- Scope boundary: only `app/Support/RepoAnalysis/EventWriter.php`, minimal event read query helper/model usage, and targeted tests for this behavior.

Failure modes to guard
- Malicious-caller mode: payloads containing secrets in nested structures attempt to bypass redaction; payloads containing malformed UTF-8 or control characters attempt to poison event stream.
- Tired-maintainer mode: stale writer instances or concurrent writes causing duplicate/non-monotonic sequences; broadcast payload drift from persisted payload.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state (`php artisan test --filter=EventWriterTest`) before implementation failed with:
    - `Class "App\Support\RepoAnalysis\EventWriter" not found`
    - `5 failed` in `Tests\Unit\Support\RepoAnalysis\EventWriterTest`.
  - Implemented:
    - `app/Support/RepoAnalysis/EventWriter.php` (transactional append, normalization/redaction, broadcast hook, `readSinceSequence` helper).
    - `app/Models/RepoAnalysisEvent.php` query scopes for session filter, `since_sequence`, and deterministic ordering.
    - `tests/Unit/Support/RepoAnalysis/EventWriterTest.php` for monotonic sequence, stale writer safety, nested redaction/UTF-8 normalization, broadcast parity, and ordered incremental reads.
  - Green state (`php artisan test --filter=EventWriterTest`) passed:
    - `Tests\Unit\Support\RepoAnalysis\EventWriterTest` => `5 passed`
    - Overall filtered run => `20 passed, 59 assertions`.
- Conditions where this works:
  - Event writes go through `EventWriter::append()` so sequence assignment occurs under session row lock and per-session `max(sequence)+1`.
  - DB uniqueness on `(repo_analysis_session_id, sequence)` remains in place as a second safety layer.
  - Payloads are arrays; malformed UTF-8 and control characters are normalized before persistence.
  - Redaction handles both value patterns (`token=...`, `Bearer ...`) and nested secret-like keys (`api_token`, `password`, `secret`).
  - Incremental reads use `EventWriter::readSinceSequence()` with positive `since_sequence` and bounded limit.
- Explicit non-goals / limitations:
  - No Code Analysis API controller/routes were added in this task; read contract is currently service/model-level.
  - Broadcast integration is implemented as a callback hook, not yet wired to Laravel broadcast channels/events.
  - True multi-process race simulation is not covered in tests; stale-writer sequencing is validated via interleaved inserts.

### Session 16 Task 2 — Create Code Analysis Schema and Eloquent Models (Completed)

Pre-Execution Goal Articulation

SITUATION
- Code Analysis configuration and Horizon defaults now exist, but dedicated persistence tables/models for sessions, events, tasks, artifacts, and reports do not yet exist.
- Existing interrogation tables are already in production use and must remain untouched.
- This task requires additive-only schema work plus new Eloquent models and verification through fail-then-pass tests.

TASK
- Add dedicated Code Analysis schema and model layer so the application can persist deterministic session lifecycle, event stream, task graph state, artifacts, and reports.
- Ensure required per-session uniqueness rules are enforced for event sequencing, task keys, and artifact keys.
- Ensure foreign key cascade behavior is correct, session soft deletes are supported, and status/phase query indexes exist for lifecycle reads.

ACTION
- [x] Add `tests/Feature/RepoAnalysis/RepoAnalysisSchemaTest.php` first with explicit assumptions:
  - [x] migrations are additive-only,
  - [x] existing interrogation tables remain untouched.
- [x] Run `php artisan test --filter=RepoAnalysisSchemaTest` and confirm red state.
- [x] Add five new additive migrations under `database/migrations/*repo_analysis*`:
  - [x] `repo_analysis_sessions`,
  - [x] `repo_analysis_events`,
  - [x] `repo_analysis_tasks`,
  - [x] `repo_analysis_artifacts`,
  - [x] `repo_analysis_reports`.
- [x] Add five new models under `app/Models/RepoAnalysis*.php` with casts and relationships.
- [x] Re-run `php artisan test --filter=RepoAnalysisSchemaTest` until green.
- [x] Run `php artisan test --filter=RepoAnalysisSession` if model tests are added.

RESULT
- Completion is proven by fail-then-pass test evidence showing:
  - tables and expected columns exist,
  - unique constraints enforce per-session uniqueness for `(session, sequence)`, `(session, task_key)`, `(session, artifact_key)`,
  - foreign keys cascade session deletion to events/tasks/artifacts/reports,
  - session soft deletes and status/phase indexes exist,
  - nullable error columns and JSON defaults are represented in schema/model behavior.

Assumptions and scope boundaries
- Assumption: migrations are additive-only and must not alter existing interrogation schema.
- Assumption: Code Analysis schema naming follows `repo_analysis_*` table conventions and `RepoAnalysis*` model naming.
- Scope boundary: only new migrations in `database/migrations/*repo_analysis*`, new models in `app/Models/RepoAnalysis*.php`, and test coverage needed to verify this task.

Failure modes to guard
- Malicious-caller mode: duplicate sequence/task/artifact keys inserted for the same session must be rejected by DB constraints.
- Tired-maintainer mode: nullable error fields or JSON defaults omitted, causing runtime null-handling regressions and brittle lifecycle queries.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=RepoAnalysisSchemaTest` failed before implementation (`6 failed, 1 passed`) with missing table errors:
    - `Failed asserting that false is true.` for `Schema::hasTable('repo_analysis_sessions')`
    - `SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "repo_analysis_sessions" does not exist`
  - Implemented:
    - Added `tests/Feature/RepoAnalysis/RepoAnalysisSchemaTest.php`.
    - Added migrations:
      - `database/migrations/2026_03_02_190000_create_repo_analysis_sessions_table.php`
      - `database/migrations/2026_03_02_190100_create_repo_analysis_events_table.php`
      - `database/migrations/2026_03_02_190200_create_repo_analysis_tasks_table.php`
      - `database/migrations/2026_03_02_190300_create_repo_analysis_artifacts_table.php`
      - `database/migrations/2026_03_02_190400_create_repo_analysis_reports_table.php`
    - Added models:
      - `app/Models/RepoAnalysisSession.php`
      - `app/Models/RepoAnalysisEvent.php`
      - `app/Models/RepoAnalysisTask.php`
      - `app/Models/RepoAnalysisArtifact.php`
      - `app/Models/RepoAnalysisReport.php`
  - Green state: `php artisan test --filter=RepoAnalysisSchemaTest` passed (`7 passed, 43 assertions`).
  - `php artisan test --filter=RepoAnalysisSession` was not run because no `RepoAnalysisSession*` model test class was added in this task.
- Conditions where this works:
  - Migrations run in order so `repo_analysis_sessions` exists before child tables with foreign keys.
  - Database backend enforces unique constraints and FK cascades (validated on `pgsql_testing`).
  - Code Analysis writes use per-session uniqueness for event sequencing/task keys/artifact keys.
- Explicit non-goals / limitations:
  - No API endpoints, services, jobs, policies, or frontend surfaces were added in this task.
  - This task does not yet enforce enum/check constraints for allowed status/event/task values.

### Session 16 Task 1 — Add Code Analysis Configuration and Queue Defaults (Completed)

Pre-Execution Goal Articulation

SITUATION
- `config/repo_analysis.php` does not yet exist, so Code Analysis defaults are not codified in runtime config.
- `config/horizon.php` currently has no dedicated `code-analysis` queue wait threshold or supervisor definition.
- Existing config unit tests cover other features but not Code Analysis defaults, safety excludes, or retention policy shape.

TASK
- Add deterministic, feature-flagged Code Analysis configuration with safe defaults and retention settings.
- Add bounded Horizon queue/supervisor defaults for `code-analysis`.
- Add and pass a dedicated unit test suite proving defaults and fallback/safety behavior.

ACTION
- [x] Add `tests/Unit/Config/RepoAnalysisConfigTest.php` first, including required assumptions docblock and failure-path checks.
- [x] Run `php artisan test --filter=RepoAnalysisConfigTest` and confirm red state before implementation.
- [x] Implement `config/repo_analysis.php` with feature flag default off, deterministic defaults, mandatory excludes, and retention fields.
- [x] Update `config/horizon.php` with `code-analysis` wait threshold and bounded supervisor config.
- [x] Re-run `php artisan test --filter=RepoAnalysisConfigTest` and confirm green.
- [x] Record verification evidence and completion review in this task log section.

RESULT
- Completion is proven by fail-then-pass test evidence and config assertions showing:
  - `max_active_sessions_per_user = 2`,
  - `narrative_synthesis_default = false`,
  - mandatory excludes include `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `.git/`,
  - retention policy keys exist,
  - feature flag defaults off, and fallback guards handle invalid/missing overrides safely.

Assumptions and scope boundaries
- Assumption: Redis and Horizon are available in target environments.
- Assumption: Code Analysis remains disabled by default unless explicitly enabled.
- Scope boundary: only `config/repo_analysis.php`, `config/horizon.php`, and config unit tests are changed; no API/UI behavior changes.

Failure modes to guard
- Malicious-caller mode: hostile or malformed environment overrides attempt to disable mandatory excludes or set unsafe session limits.
- Tired-maintainer mode: missing env keys or invalid override values accidentally produce null/empty config branches and runtime instability.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=RepoAnalysisConfigTest` failed (`5 failed`) before implementation, with null config assertions and missing file error:
    - `Failed asserting that null is false.`
    - `require(.../config/repo_analysis.php): Failed to open stream: No such file or directory`.
  - Implemented:
    - `config/repo_analysis.php` with feature flag default off, bounded override fallback, mandatory excludes, and retention policy defaults.
    - `config/horizon.php` with `redis:code-analysis` wait threshold and `supervisor-code-analysis` defaults + environment entries.
  - Green state: `php artisan test --filter=RepoAnalysisConfigTest` passed (`5 passed, 35 assertions`).
- Conditions where this works:
  - Runtime is using this repository config set and Horizon reads `config/horizon.php`.
  - Optional env overrides for Code Analysis remain within defined bounds; invalid values intentionally fall back to safe defaults.
  - Mandatory excludes remain enforced through merged defaults, even with empty/invalid exclude override input.
- Explicit non-goals / limitations:
  - No API routes, controllers, models, migrations, jobs, or frontend pages were changed.
  - This task does not enable Code Analysis by default; it only establishes configuration and queue defaults.

### Session 16 Task 1 — Draft deterministic code analysis tool implementation spec (Completed)

Pre-Execution Goal Articulation

SITUATION
- Requirements Discovery already provides a robust wizard/state-machine/event-stream foundation across setup, discovery, interrogation, summary, planning, and build.
- Existing discovery behavior is primarily LLM-driven during discovery/interrogation and does not provide deterministic, reproducible repository-wide analysis artifacts.
- The requested capability is a robust codebase/code analysis tool with task-based execution, proper planning, full-repo understanding, and report generation/storage.

TASK
- Produce an implementation-ready technical plan for a new deterministic code-analysis tool integrated into Agent’s existing architecture.
- Align proposed design with existing conventions: queue jobs, phase transitions, event streaming, wizard UX, exports, and policy enforcement.
- Define concrete scaffolding for schema, routes, controllers, services, jobs, artifacts, reports, and test strategy.

ACTION
- [x] Inspect existing discovery flow and lifecycle contracts (`InterrogationSession`, controller routes/actions, discovery/summary/build jobs, event writer, export services).
- [x] Extract reusable patterns (state transitions, queue orchestration, websocket event flow, metadata and build-style task orchestration).
- [x] Draft implementation-ready spec in `docs/plans/code-analysis-tool.md` with:
  - [x] deterministic architecture and reproducibility guarantees,
  - [x] schema/model design,
  - [x] API surface and wizard phase mapping,
  - [x] queue/jobs/services scaffolding,
  - [x] report generation/storage contracts,
  - [x] verification/testing and rollout plan.

RESULT
- Completion is proven by a committed plan document that can be executed directly by engineering to implement the new code-analysis capability with deterministic behavior and testable acceptance criteria.

Assumptions and scope boundaries
- Assumption: code-analysis should be integrated as a new tool that reuses existing platform primitives rather than replacing Requirements Discovery.
- Assumption: deterministic analyzers should be first-class, with LLM usage optional and downstream of locked deterministic artifacts.
- Scope boundary: this task produces architecture/specification only (no runtime feature implementation in this task).

Failure modes to guard
- Malicious-caller mode: path traversal, hidden-file secret leakage, and unbounded scanning must be blocked via strict path policy and configurable excludes/limits.
- Tired-maintainer mode: partial scans, non-reproducible outputs, and unclear failure handling must be surfaced by deterministic coverage gates and explicit task-level evidence.

Review
- [x] Evidence summary with exact files produced.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Added implementation plan: `docs/plans/code-analysis-tool.md`.
  - Plan includes concrete class/file scaffolding, API contracts, state machine, job orchestration, artifact schema, report formats, and rollout gates.
- Conditions where this works:
  - Existing interrogation queue/event architecture remains available for reuse.
  - Path and policy validation continue to enforce allowed working directories and bounded execution.
- Explicit non-goals / limitations:
  - No migrations/models/controllers/jobs are implemented in this task.
  - No frontend pages/components are implemented in this task.

### Session 15 Task 12 — Wire shared sync automation for pre-commit and deploy enforcement (Completed)

Pre-Execution Goal Articulation

SITUATION
- `docs:sync` exists and writes manifest + dispatches async reindex, but deploy mode does not currently enforce bounded reindex retries or fail deployment on exhausted retry budget.
- Repository currently has no checked-in `.githooks/pre-commit`, no shared `scripts/docs/sync.sh`, and no project-scoped Claude/Codex pre-commit hook entrypoints.
- No integration test currently verifies commit-mode automation behavior (auto-regenerate + auto-stage + non-block on sync failure) or deploy-mode strict retry-fail behavior.
- `.github/workflows` is currently absent, so deploy enforcement must be introduced by creating workflow YAML in-repo.

TASK
- One shared sync pipeline must be callable from git pre-commit and Claude/Codex hook entrypoints.
- Commit mode must auto-regenerate and auto-stage stale docs artifacts, and must not block commit when sync command fails.
- Deploy mode must enforce bounded reindex retries (3 retries, 30s interval, 2m cap) and fail with non-zero exit after retry budget exhaustion.
- CI/deploy workflow must run `php artisan docs:sync --mode=deploy --source=repo` so deploy fails when bounded reindex retries cannot recover.

ACTION
- [x] Add failing integration tests in `tests/Integration/Documentation/DocsAutomationFlowTest.php` for:
  - [x] commit mode stale artifact auto-regenerate + auto-stage behavior
  - [x] commit mode sync failure non-block behavior
  - [x] deploy mode bounded retry policy (3 retries, 30s interval, 2m cap) and terminal failure
- [x] Implement shared script `scripts/docs/sync.sh` with commit/deploy behavior split and robust non-blocking commit semantics.
- [x] Wire shared script from `.githooks/pre-commit` and project Claude/Codex hook entrypoints.
- [x] Implement deploy-time strict retry/fail behavior in docs sync runtime path.
- [x] Add deploy workflow YAML under `.github/workflows/` invoking `php artisan docs:sync --mode=deploy --source=repo`.
- [x] Run verification commands:
  - [x] `php artisan test --filter=DocsAutomationFlowTest`
  - [x] `bash scripts/docs/sync.sh --mode=commit --source=repo` (local run)
  - [x] `bash scripts/docs/sync.sh --mode=deploy --source=repo` (local run)

RESULT
- Completion is proven by fail-then-pass integration test evidence, working local script execution in both modes, and deploy gate behavior producing non-zero exit when retry budget is exhausted.

Assumptions and scope boundaries
- Assumption: adding repo-local hook scripts (`.githooks`, `.claude/hooks`, `.codex/hooks`) is acceptable in this project.
- Assumption: deploy workflow may be introduced as a new `.github/workflows/*.yml` file because none currently exists.
- Scope boundary: this task only changes automation scripts/hooks and deploy-time docs sync/reindex gate behavior; it does not add docs authoring features or alter docs UI contracts.

Failure modes to guard
- Malicious-caller mode: hook/script execution in partial environments (missing git metadata, missing shared script, missing command dependencies) must fail safely without corrupting repo state.
- Tired-maintainer mode: stale artifact drift and silent deploy reindex failures must be surfaced deterministically, with bounded retry behavior that fails hard once budget is exhausted.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test tests/Integration/Documentation/DocsAutomationFlowTest.php` failed with:
    - missing `scripts/docs/sync.sh`,
    - missing hook/workflow entrypoint files,
    - deploy-mode command still returning exit code `0` instead of failure for exhausted reindex retries.
  - Green state:
    - `php artisan test --filter=DocsAutomationFlowTest` passed (`4 passed, 28 assertions`).
    - `php artisan test --filter=DocsSyncCommandTest` passed (`5 passed, 27 assertions`).
    - `bash scripts/docs/sync.sh --mode=commit --source=repo` passed:
      - `Docs sync completed.`
      - `Mode: commit | Source: repo | Entries: 12 | Fragments: 14 | Links: 61`
    - `bash scripts/docs/sync.sh --mode=deploy --source=repo` passed:
      - `Docs sync completed.`
      - `Mode: deploy | Source: repo | Entries: 12 | Fragments: 14 | Links: 61`
  - Deploy retry-fail gate verification:
    - `DocsAutomationFlowTest::test_deploy_mode_retries_three_times_with_thirty_second_interval_then_fails` confirms deploy reindex executes initial attempt + 3 retries with sleep cadence `[30, 30, 30]` and returns non-zero.
- Conditions where this works:
  - `scripts/docs/sync.sh` remains executable and hook files are installed/executable.
  - Deploy runtime has required Scout/Typesense env config; on repeated timeout/failure it now fails with bounded retry policy.
  - Commit-time hook runs inside a git worktree so auto-staging can add regenerated docs artifacts.
- Explicit non-goals / limitations:
  - This task does not install `core.hooksPath` automatically on developer machines.
  - Workflow file is introduced as a deploy gate entrypoint; repository-specific release orchestration beyond this gate is not changed here.

### Session 15 Task 10 — Add coverage audit service and strict CI gates (Completed)

Pre-Execution Goal Articulation

SITUATION
- `docs:validate` currently validates schema-level contract only and does not enforce documentation-surface completeness.
- There is no `docs:coverage` command, no codified coverage inventory, and no strict `--fail-on-missing` gate for CI usage.
- Existing docs data is sparse and does not yet satisfy strict per-surface criteria, so gate logic must be test-driven with isolated fixtures.

TASK
- Introduce a strict coverage audit that enforces required documentation surfaces and pass criteria:
  - published overview doc,
  - settings detail,
  - concrete example,
  - troubleshooting guidance,
  - linked tooltip coverage.
- Add command-level enforcement via `php artisan docs:coverage --fail-on-missing`.
- Extend `docs:validate` output to include link/orphan checks for missing tooltip `ui_key`, broken `learn_more_slug`, and missing critical route docs.

ACTION
- [x] Add failing tests in `tests/Feature/Documentation/DocsCoverageGateTest.php` for strict pass/fail behavior and validation link/orphan reporting.
- [x] Run targeted test command to confirm intentional red state.
- [x] Add `config/docs_coverage.php` surface inventory and implement `CoverageAuditService`.
- [x] Implement `DocsCoverageCommand` with `--fail-on-missing`.
- [x] Extend `DocsValidateCommand` to run and report link/orphan coverage checks.
- [x] Run verification commands:
  - `php artisan docs:coverage --fail-on-missing`
  - `php artisan docs:validate`
  - `php artisan test --filter=DocsCoverageGateTest`

RESULT
- Completion is proven by fail-then-pass test evidence for coverage gates, successful command execution, and explicit validation output that reports/blocks orphan-link and missing-surface failures.

Assumptions and scope boundaries
- Assumption: route/surface inventory defined in task context is accepted and stable for codification in config.
- Assumption: this task is backend validation/coverage only; no UI routing/component changes are required.
- Scope boundary: add strict audit logic and command gates only; do not implement new docs content authoring flows.

Failure modes to guard
- Malicious-caller mode: invalid/missing docs references should fail predictably and not produce false pass coverage.
- Tired-maintainer mode: route or settings renames causing stale mappings should surface as actionable validation failures.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=DocsCoverageGateTest` failed with:
    - `The command "docs:coverage" does not exist.`
    - `Expected status code 1 but received 0.` for `docs:validate` orphan checks.
  - Green state: `php artisan test --filter=DocsCoverageGateTest` passed (`3 passed, 12 assertions`).
  - Required command verification:
    - `php artisan docs:coverage --fail-on-missing` passed with `Coverage: 100.00% (12/12 surfaces)` and `Link/orphan checks passed.`
    - `php artisan docs:validate` passed with `Validated markdown files: 12, tooltip files: 2, tooltip fragments: 14` and `Link/orphan checks: passed.`
  - Regression slice: `php artisan test --filter=Documentation` passed (`52 passed, 352 assertions`).
- Conditions where this works:
  - `docs_coverage` inventory is maintained when routes, settings, or tooltip keys change.
  - Documentation markdown continues to include the required section markers (`Settings`, `Example`, `Troubleshooting`).
  - Tooltip fragments maintain valid `learn_more_slug` references to non-deprecated docs.
- Explicit non-goals / limitations:
  - This task does not add CI workflow YAML because no `.github/workflows` directory exists in this repository.
  - This task does not implement new docs UI; it focuses on validation and command-level gating only.

### Session 15 Task 6 — Add authenticated read-only docs HTTP contracts (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs web routes (`/docs`, `/docs/{slug}`) and docs API read routes (`/agent/api/v1/docs/search`, `/agent/api/v1/docs/fragments/{uiKey}`, `/agent/api/v1/docs/coverage`) are already present in `routes/web.php` and `routes/api.php`.
- Existing feature coverage in `tests/Feature/Documentation/DocsAuthorizationTest.php` already validates authenticated access, guest denial/redirect, coverage authorization, unknown slug/ui key handling, malformed query params, and read-only method exposure.
- Controllers/requests for docs read endpoints already exist under `app/Http/Controllers/Docs/`, `app/Http/Controllers/Api/V1/Docs/`, and `app/Http/Requests/Docs/`.

TASK
- Ensure docs HTTP contracts remain authenticated and read-only with no write surface.
- Verify authorization boundaries and edge/failure paths for unknown slugs/ui keys and malformed query parameters.
- Prove completion with route and test evidence.

ACTION
- [x] Execute `php artisan test --filter=DocsAuthorizationTest`.
- [x] Execute `php artisan route:list --path=docs` and verify web + API docs read routes exist.
- [x] Execute a mutating-method route scan for docs URIs and verify no POST/PUT/PATCH/DELETE routes are registered.
- [x] Update task log with assumptions, verification evidence, correctness conditions, and non-goals.

RESULT
- Completion is proven when:
  - `DocsAuthorizationTest` passes,
  - docs route listing contains only the expected GET/HEAD contracts,
  - no mutating docs routes are present.

Assumptions and scope boundaries
- Assumption: existing auth middleware (`auth:sanctum` and web auth stack) remains the canonical guard for private docs surfaces.
- Assumption: `view-docs-coverage` gate remains mapped to the intended internal role/capability policy.
- Scope boundary: no create/update/delete docs endpoints, no docs authoring UI, and no search/indexing logic changes.

Failure modes to guard
- Malicious-caller mode: unauthenticated requests or unauthorized roles attempting to access docs APIs, especially `/agent/api/v1/docs/coverage`.
- Tired-maintainer mode: accidentally introducing mutating docs routes or weakening auth middleware while adding docs-related routes.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - `php artisan test --filter=DocsAuthorizationTest` passed (`9 passed, 18 assertions`).
  - `php artisan route:list --path=docs` reported:
    - `GET|HEAD docs`
    - `GET|HEAD docs/{slug}`
    - `GET|HEAD agent/api/v1/docs/search`
    - `GET|HEAD agent/api/v1/docs/fragments/{uiKey}`
    - `GET|HEAD agent/api/v1/docs/coverage`
  - Mutating method scan over docs URIs (`POST|PUT|PATCH|DELETE`) returned no matches.
- Conditions where this works:
  - Auth middleware and gate configuration remain active as currently wired in `routes/web.php` and `routes/api.php`.
  - Coverage authorization continues to be enforced by `can:view-docs-coverage`.
- Explicit non-goals / limitations:
  - No new docs route/controller code was required in this task run because the target contracts were already implemented.
  - This task does not add any docs write endpoints or in-app authoring surfaces.

### Session 15 Task 5 — Ingest Scramble OpenAPI artifacts into docs domain (Completed)

Pre-Execution Goal Articulation

SITUATION
- `ApiDocArtifact` schema/model exists, but there is no ingest command to import Scramble OpenAPI operations into runtime metadata.
- There is no feature coverage yet for OpenAPI artifact ingest behavior (`operation_id` import, checksum/version updates, linked narrative slug integrity, idempotent reruns).
- Existing docs sync/search pipelines already index `ApiDocArtifact`, so this task should focus on import path and integrity rules, not search API/UI rewiring.

TASK
- Implement `docs:openapi:ingest` to parse exported OpenAPI artifact metadata and upsert `ApiDocArtifact` rows safely and idempotently.
- Ensure ingest behavior handles required edge/failure paths: missing `operationId`, changed endpoint path/method, broken narrative slug links, checksum/version changes.
- Keep Scramble export as canonical artifact source and wire ingest to the project-standard export path.

ACTION
- [x] Add failing tests first in `tests/Feature/Documentation/OpenApiArtifactIngestTest.php` for:
  - operation import and field mapping,
  - checksum/spec-version updates on artifact changes,
  - linked narrative slug integrity (`linked_doc_slugs`),
  - idempotent rerun behavior,
  - failure on missing `operationId`,
  - behavior when endpoint path/method changes.
- [x] Run `php artisan test --filter=OpenApiArtifactIngestTest` and confirm red state.
- [x] Implement `app/Console/Commands/DocsOpenApiIngestCommand.php`:
  - load spec from configured/project-standard export path,
  - parse JSON or YAML,
  - normalize operations and linked slugs,
  - upsert by `operation_id`,
  - update path/method/summary/description/tags/spec checksum/version/published timestamp.
- [x] Add required config wiring for OpenAPI artifact path/format assumptions if missing.
- [x] Ensure ingestion is idempotent and safe for reruns.
- [x] Run verification commands:
  - `php artisan docs:openapi:ingest`
  - `php artisan test --filter=OpenApiArtifactIngestTest`

RESULT
- Completion evidence requires fail-then-pass for `OpenApiArtifactIngestTest`, successful `docs:openapi:ingest` execution, and DB assertions proving correct upsert/update/idempotency/integrity behavior.

Assumptions and scope boundaries
- Assumption: Scramble artifact file is available at repo-standard path (or configured equivalent) and is valid JSON/YAML.
- Assumption: API narrative docs are already synced into `documentation_entries` (`domain=api_doc`) when link integrity checks run.
- Scope boundary: ingest command + runtime metadata integrity only; no public docs portal/search UX changes.

Failure modes to guard
- Malicious-caller mode: malformed spec payload, missing required operation metadata (`operationId`), invalid linked slug references.
- Tired-maintainer mode: duplicate operation IDs, stale path/method drift, repeated reruns creating duplicate artifacts.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=OpenApiArtifactIngestTest` failed (`4 failed`) with `The command "docs:openapi:ingest" does not exist.`
  - Green state: `php artisan test --filter=OpenApiArtifactIngestTest` passed (`4 passed, 27 assertions`).
  - Runtime command verification:
    - `php artisan docs:sync --mode=commit --source=repo` passed (`Entries: 2 | Fragments: 1 | Links: 6`).
    - `php artisan docs:openapi:ingest --path=storage/app/docs-sync/openapi-ingest-verification.yaml` passed (`Operations: 1 | Version: 1.2.3`) with checksum output.
- Conditions where this works:
  - OpenAPI artifact path points to a valid JSON or YAML OpenAPI document containing `paths`.
  - Each ingestible operation includes `operationId`.
  - Each ingestible operation includes linked narrative slugs using configured extension key (default `x-linked-doc-slugs`).
  - Linked narrative slugs resolve to existing `documentation_entries` rows in `domain=api_doc`.
- Explicit non-goals / limitations:
  - This task does not run or configure Scramble export itself; it ingests an already-exported artifact.
  - This task does not create API narrative docs automatically for unresolved slugs.
  - This task does not build public API portal views; scope is artifact import + runtime link integrity.

### Session 15 Task 4 — Implement Scout+Typesense docs search service (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs API search currently uses `DocsCatalog` in-memory stubs rather than runtime models and has no Scout/Typesense dependency.
- `laravel/scout` config/package is not present yet, so there is no Typesense-backed indexing contract in code.
- Existing docs runtime entities (`DocumentationEntry`, `DocumentationFragment`, `DocumentationLink`, `ApiDocArtifact`) exist and are populated by `docs:sync`, which gives a reliable base for searchable data.
- Constraints:
  - Typesense must be treated as mandatory search backend.
  - Search API must provide outage contract (`search temporarily unavailable`) and must not return fallback results when search backend is unavailable.
  - Scope is backend indexing/search API behavior only (no UI work).

TASK
- Implement a docs search stack where indexing and queries are Scout+Typesense-backed, with route-affinity ranking and strict outage behavior.
- Ensure API returns required payload fields: `title`, `snippet`, `domain`, `section`, `route_affinity`, `updated_at`.
- Ensure async index freshness path dispatches reindex work targeting <=60s freshness.
- Ensure invalid filters and empty query are handled explicitly, and Typesense outage returns deterministic unavailable response with no fallback results.

ACTION
- [x] Add tests first:
  - `tests/Feature/Documentation/DocsSearchApiTest.php`
  - `tests/Unit/Documentation/DocsSearchServiceTest.php`
  covering domain filters, route-affinity boost/order, payload shape, empty query + invalid params, and outage response contract.
- [x] Run `php artisan test --filter=DocsSearchApiTest` to confirm red state.
- [x] Add Scout and Typesense integration:
  - install required packages,
  - add `config/scout.php`,
  - add env defaults in examples,
  - add searchable mappings (`Searchable`, `toSearchableArray`, Typesense schema options) on docs models.
- [x] Implement `app/Support/Documentation/DocsSearchService.php` and wire `DocsSearchController` to use it.
- [x] Add async reindex job for changed docs entities and trigger from docs sync path.
- [x] Re-run `php artisan test --filter=DocsSearch`.
- [x] Run `php artisan scout:sync-index-settings` with `SCOUT_DRIVER=typesense` and capture result.

RESULT
- Completion is proven by:
  - fail-then-pass evidence for `DocsSearchApiTest` and `DocsSearchServiceTest`,
  - passing docs-search test slice (`--filter=DocsSearch`),
  - successful `scout:sync-index-settings` execution under Typesense driver,
  - API response evidence for required payload fields and outage behavior.

Assumptions and scope boundaries
- Assumption: test DB/runtime env is already configured and safe for non-destructive test runs.
- Assumption: Typesense credentials/host are provided through environment when command-level sync is executed.
- Scope boundary: search indexing/query pipeline and API payloads only; docs pages/tooltip UI integration remain unchanged.

Failure modes to guard
- Malicious-caller mode: invalid domain/section/route filters and empty query should fail predictably (validation) and avoid expensive backend work.
- Tired-maintainer mode: silent fallback to local/in-memory search during Typesense outage must be prevented to avoid misleading results.

Review
- Added tests:
  - `tests/Feature/Documentation/DocsSearchApiTest.php`
  - `tests/Unit/Documentation/DocsSearchServiceTest.php`
- Added Scout/Typesense integration:
  - `composer.json` + `composer.lock` include `laravel/scout` and `typesense/typesense-php`.
  - `config/scout.php` with Typesense client settings and docs model search parameters.
  - `.env.example` and `.env.testing.example` now include Scout/Typesense env defaults.
- Added new docs search runtime path:
  - `app/Support/Documentation/DocsSearchIndexClient.php`
  - `app/Support/Documentation/ScoutDocsSearchIndexClient.php`
  - `app/Support/Documentation/DocsSearchService.php`
  - `app/Support/Documentation/DocsSearchUnavailableException.php`
  - `app/Http/Controllers/Api/V1/Docs/DocsSearchController.php` now returns 503 outage contract (`search temporarily unavailable`) with no fallback data.
  - `app/Http/Requests/Docs/SearchDocsRequest.php` now requires non-empty `q` and supports `route`.
  - `app/Providers/AppServiceProvider.php` binds `DocsSearchIndexClient` to `ScoutDocsSearchIndexClient`.
- Added async freshness hook:
  - `app/Jobs/ReindexDocumentationSearchJob.php`
  - `app/Support/Documentation/DocsSyncService.php` now disables inline Scout syncing during upsert and dispatches delayed reindex job (default 5s delay, <=60s freshness target in config).
  - `config/documentation.php` includes `search.reindex_delay_seconds` and `search.freshness_target_seconds`.
- Verification evidence:
  - Red state: `php artisan test --filter=DocsSearchApiTest` failed (missing classes/contracts and old API behavior).
  - Green state: `php artisan test --filter=DocsSearch` passed (`8 passed, 24 assertions`).
  - Contract check: `php artisan test --filter=DocsSearchApiTest` passed (`4 passed, 15 assertions`).
  - Regression safety: `php artisan test --filter=DocsAuthorizationTest` and `php artisan test --filter=DocsSyncCommandTest` both passed.
  - Command check: `SCOUT_DRIVER=typesense php artisan scout:sync-index-settings` exits successfully and reports: `The \"typesense\" engine does not support updating index settings.`
- Conditions where this works:
  - Typesense is reachable and configured via `TYPESENSE_*` env values.
  - `SCOUT_DRIVER=typesense` is enabled in runtime environments.
  - Queue workers process `ReindexDocumentationSearchJob` on the `agent` queue.
- Explicit non-goals / limitations:
  - `scout:sync-index-settings` does not apply settings for Typesense in Scout v10 (engine limitation); collection schema is driven by model schema on import/reindex.
  - This task does not rewire docs page rendering (`DocsPageController`) away from `DocsCatalog`.

### Session 15 Task 3 — Build sync pipeline and command orchestration (Completed)

Pre-Execution Goal Articulation

SITUATION
- Runtime docs schema/models and contract validators exist, but there is no ingestion orchestration that parses repo files and persists deterministic runtime state.
- `docs:sync` command does not exist yet, so there is no canonical entrypoint for commit/deploy sync mode orchestration.
- Existing docs read paths currently use in-memory catalog stubs, so this task must stay scoped to ingestion/persistence and not rewire search/ranking behavior.

TASK
- Implement a parse-validate-normalize-upsert-link pipeline with repo-wins conflict behavior for duplicate `slug`/`ui_key` keys against existing runtime records.
- Add `docs:sync` orchestration (`--mode=commit|deploy --source=repo`) that writes deterministic sync manifest output and is idempotent on rerun.
- Ensure failure paths are explicit and safe: duplicate keys across files, unresolved `learn_more_slug`, partial parse/validation failures, and stale runtime rows being overwritten by repo content.

ACTION
- [x] Add failing feature tests in `tests/Feature/Documentation/DocsSyncCommandTest.php` for:
  - repo-wins overwrite on same `slug` / `ui_key`
  - checksum updates when repo content changes
  - link upserts from route/setting/flag associations
  - deterministic and idempotent manifest output
- [x] Run `php artisan test --filter=DocsSyncCommandTest` and capture expected red-state evidence.
- [x] Implement `app/Support/Documentation/DocsIngestionPipeline.php`.
- [x] Implement `app/Support/Documentation/DocsSyncService.php`.
- [x] Implement `app/Console/Commands/DocsSyncCommand.php` with strict option validation for `--mode` and `--source`.
- [x] Ensure transaction-safe upsert semantics and repo-wins overwrite behavior for pre-existing runtime records.
- [x] Ensure manifest artifact is written to `storage/app/docs-sync/manifest.json` with stable ordering/content.
- [x] Run `php artisan docs:sync --mode=commit --source=repo`.
- [x] Re-run `php artisan test --filter=DocsSyncCommandTest` until green.

RESULT
- Completion is proven by:
  - fail-then-pass evidence for `DocsSyncCommandTest`,
  - successful `php artisan docs:sync --mode=commit --source=repo` execution,
  - deterministic `storage/app/docs-sync/manifest.json` content across idempotent reruns,
  - persisted runtime rows showing repo-wins overwrite + link upsert behavior.

Assumptions and scope boundaries
- Assumption: schema/migrations from earlier tasks are present and available in `APP_ENV=testing` with `DB_CONNECTION=pgsql_testing`.
- Assumption: repository docs contract files under configured paths are readable.
- Scope boundary: ingestion orchestration + persistence only; search ranking/index relevance logic remains out of scope.

Failure modes to guard
- Malicious-caller mode: invalid file payloads or unresolved cross-links must not partially corrupt persisted runtime state.
- Tired-maintainer mode: duplicate keys across files, stale row drift, and nondeterministic manifest ordering must be surfaced/blocked.

Review
- Added feature coverage in `tests/Feature/Documentation/DocsSyncCommandTest.php` for:
  - repo-wins overwrite of stale `DocumentationEntry`/`DocumentationFragment` rows by `slug`/`ui_key`
  - checksum refresh behavior on content updates
  - deterministic link replacement/upsert behavior
  - deterministic manifest output with idempotent rerun behavior
  - failure paths for duplicate keys and unresolved `learn_more_slug`
- Added ingestion orchestration implementation:
  - `app/Support/Documentation/DocsIngestionPipeline.php`
  - `app/Support/Documentation/DocsSyncService.php`
  - `app/Console/Commands/DocsSyncCommand.php`
- Added sync config for command constraints + artifact path in `config/documentation.php`.
- Verification evidence:
  - Red state: `php artisan test --filter=DocsSyncCommandTest` failed with `The command "docs:sync" does not exist.` (5 failing tests).
  - Green state: `php artisan test --filter=DocsSyncCommandTest` passed (`5 passed, 27 assertions`).
  - Required command: `php artisan docs:sync --mode=commit --source=repo` passed (`Entries: 2 | Fragments: 1 | Links: 6`) and wrote manifest to `storage/app/docs-sync/manifest.json`.
  - Regression slice: `php artisan test --filter=Documentation` passed (`32 passed, 105 assertions`).
- Conditions where this works:
  - Docs contract directories configured in `config/documentation.php` are readable and contain valid phase-1 contract content.
  - DB writes run inside transaction boundaries; unresolved cross-links and duplicate keys fail the run without partial persistence.
  - Command options must be `--mode=commit|deploy` and `--source=repo`.
- Explicit non-goals / limitations:
  - Search ranking behavior and Typesense relevance logic are out of scope.
  - This task does not rewire docs read APIs/pages from `DocsCatalog` to runtime DB-backed retrieval.

### Runtime Documentation Schema and Models (Completed)

Pre-Execution Goal Articulation

SITUATION
- Documentation runtime tables/models for entries, fragments, links, and API artifacts are not present yet.
- Existing test suite has docs auth/navigation tests but no schema-level constraint coverage for the new docs runtime entities.
- Repository patterns enforce DB constraints directly, with PostgreSQL checks and pragmatic test behavior for SQLite/other drivers.

TASK
- Add runtime data-layer support for `DocumentationEntry`, `DocumentationFragment`, `DocumentationLink`, and `ApiDocArtifact` with enforceable uniqueness, FK integrity, enum constraints, and required indexes.
- Provide feature tests in `tests/Feature/Documentation/DocumentationSchemaTest.php` proving these constraints and relationships.

ACTION
- [x] Add failing schema tests for table shape + constraints:
  - unique `documentation_entries` (`domain`, `slug`, `locale`)
  - unique `documentation_fragments` (`ui_key`, `locale`)
  - enum/check behavior for `domain` and `severity`
  - FK integrity for cross-links (`documentation_links`, `api_doc_artifacts`)
- [x] Run `php artisan test --filter=DocumentationSchemaTest` and capture red state (missing schema/constraints).
- [x] Add migrations for:
  - `documentation_entries`
  - `documentation_fragments`
  - `documentation_links`
  - `api_doc_artifacts`
  with indexes on `domain`, `section`, `updated_at` where applicable.
- [x] Add models with casts/relationships:
  - `DocumentationEntry`
  - `DocumentationFragment`
  - `DocumentationLink`
  - `ApiDocArtifact`
- [x] Re-run `php artisan test --filter=DocumentationSchemaTest` until green.
- [x] Run `php artisan migrate --pretend` and confirm SQL generation is non-destructive.

RESULT
- Completion proof is a fail-then-pass test sequence for `DocumentationSchemaTest` plus `migrate --pretend` output showing create/index/check/FK SQL for new docs runtime tables.

Assumptions and scope boundaries
- Assumption: PostgreSQL test connection (`pgsql_testing`) is available and enforces enum/FK/unique constraints at database level.
- Assumption: runtime docs data remains private/authenticated; this task does not add route/controller/search wiring.
- Scope boundary: data layer only (migrations + models + schema tests); no ingestion, search, API response shaping, or UI behavior.

Failure modes to guard
- Malicious-caller mode: invalid `domain`/`severity` values or broken FK references must fail at DB write time.
- Tired-maintainer mode: accidental duplicate `domain+slug+locale` or `ui_key+locale` should be blocked by unique constraints.

Review
- Added feature test: `tests/Feature/Documentation/DocumentationSchemaTest.php`.
- Added migrations:
  - `database/migrations/2026_03_02_150000_create_documentation_entries_table.php`
  - `database/migrations/2026_03_02_150100_create_documentation_fragments_table.php`
  - `database/migrations/2026_03_02_150200_create_documentation_links_table.php`
  - `database/migrations/2026_03_02_150300_create_api_doc_artifacts_table.php`
- Added models:
  - `app/Models/DocumentationEntry.php`
  - `app/Models/DocumentationFragment.php`
  - `app/Models/DocumentationLink.php`
  - `app/Models/ApiDocArtifact.php`
- Verification evidence:
  - Red state: `php artisan test --filter=DocumentationSchemaTest` failed with undefined docs runtime tables.
  - Green state: `php artisan test --filter=DocumentationSchemaTest` passed (7 tests, 14 assertions).
  - `php artisan migrate --pretend` output: `INFO  Nothing to migrate.` (migrations already applied in this environment).
  - Migration registration evidence: `php artisan migrate:status` shows all four new documentation migrations as `Ran`.

### Docs Center Pages + Auth Navigation Discoverability (Completed)

Pre-Execution Goal Articulation

SITUATION
- `/docs` and `/docs/{slug}` web routes already exist and are authenticated.
- `DocsPageController` already returns Inertia views `Docs/Index` and `Docs/Show`.
- `resources/js/Pages/Docs/Index.vue` and `resources/js/Pages/Docs/Show.vue` do not exist yet.
- Shared authenticated navigation in `resources/js/Layouts/AppLayout.vue` currently has no Docs nav item.
- Existing docs feature tests cover auth/read-only behavior, but no dedicated `DocsNavigationTest` exists for discoverability requirements.

TASK
- Authenticated users can reach `/docs` and `/docs/{slug}` through working Inertia pages.
- Shared authenticated primary navigation includes a Docs entry linking to `/docs`.
- A dedicated feature test (`DocsNavigationTest`) proves docs route reachability, nav discoverability, slug resolution, unknown slug 404 behavior, and guest redirect behavior.

ACTION
- [x] Add `tests/Feature/Documentation/DocsNavigationTest.php` with assertions for:
  - authenticated `/docs` route + expected Inertia component,
  - docs navigation link discoverability in primary layout,
  - `/docs/{slug}` resolution and unknown slug 404,
  - guest redirect on docs routes.
- [x] Run `php artisan test --filter=DocsNavigationTest` and capture initial failure.
- [x] Implement docs UI shell pages:
  - `resources/js/Pages/Docs/Index.vue`
  - `resources/js/Pages/Docs/Show.vue`
- [x] Add Docs entry to authenticated navigation in `resources/js/Layouts/AppLayout.vue` (desktop + responsive menus).
- [x] Re-run `php artisan test --filter=DocsNavigationTest` and confirm pass.
- [x] Run `npm run build` and confirm pass.

RESULT
- Evidence is:
  - failing-then-passing `DocsNavigationTest` output,
  - successful `npm run build`,
  - test assertions proving docs link target and docs route resolution from in-app navigation target.

Assumptions and scope boundaries
- Assumption: `AppLayout.vue` is the shared authenticated navigation for targeted user roles.
- Assumption: route names `docs.index` and `docs.show` remain stable.
- Scope boundary: docs center shell + nav discoverability only; no tooltip/help hint integration.
- Scope boundary: no changes to docs ingestion/search architecture in this task.

Failure modes to guard
- Malicious-caller mode: unauthenticated access to docs routes must remain blocked by auth middleware.
- Tired-maintainer mode: route/link drift between nav and route names should be caught by explicit test assertions.

Review
- Added `tests/Feature/Documentation/DocsNavigationTest.php` with assertions for docs index reachability, layout nav discoverability, slug resolution, unknown slug 404, and guest redirects.
- Added docs pages:
  - `resources/js/Pages/Docs/Index.vue`
  - `resources/js/Pages/Docs/Show.vue`
- Added `Docs` navigation entry to both desktop and responsive authenticated nav menus in `resources/js/Layouts/AppLayout.vue`.
- Verification evidence:
  - Initial red state: `php artisan test --filter=DocsNavigationTest` failed due missing Inertia pages (`Docs/Index`, `Docs/Show`) and missing docs nav target in `AppLayout.vue`.
  - Final green state: `php artisan test --filter=DocsNavigationTest` passed (5 tests, 32 assertions).
  - Build validation: `npm run build` passed (client + SSR).

### Documentation + Tooltip Platform Discovery Brief (Completed)

- [x] Audit current app surfaces (routes/pages/models/settings) to define documentation coverage boundaries.
- [x] Compare API-doc and human-doc tooling options (Scramble, Scribe, LaRecipe, Jigsaw, Docusaurus) against project needs.
- [x] Define requirements for searchable user docs using Laravel Scout.
- [x] Define requirements for contextual helper text and hover tooltips across the UI.
- [x] Produce a requirements discovery brief with recommendations, phased rollout, and acceptance criteria.

Review
- Added a new requirements discovery brief at `docs/discovery/documentation-and-tooltip-system-requirements-brief.md`.
- Recommendation from discovery:
  - Keep `Scramble` focused on OpenAPI generation.
  - Build first-party human documentation + tooltip domain in-app (with Scout indexing and unified UI context integration).
  - Optionally export static docs later (Docusaurus/Jigsaw) for public docs, while retaining in-app authoring/search as system-of-record.
- Noted current gap: there is no `packages/` directory yet; brief includes a package-ready architecture plan if/when the folder is introduced.

### Agent Org Layer — Remove Raw JSON UX (Completed)

- [x] Replace raw JSON textareas in Org Agents form with structured controls (authority overrides + output schema builder).
- [x] Replace raw JSON textareas in Org Rituals form with structured controls (context inputs + verification + delivery targets).
- [x] Replace raw JSON textareas in Org Councils form with structured controls (evidence/response schema + report sections).
- [x] Preserve existing API contracts by encoding/decoding form controls to the same payload JSON shapes.
- [x] Run targeted verification (`php artisan test` Org API slice + `npm run build`) and record outcomes.

Review
- Replaced JSON textareas with non-technical builders across Org forms:
  - Agents: authority override rule builder + default output field/schema builder.
  - Rituals: context input map builder, typed verification strategy rule builder, delivery target map builder.
  - Councils: evidence/member-response schema field builders + report section list builder.
- Preserved backend payload contracts by converting form controls into the same JSON object/array shapes in submit handlers.
- Verification:
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org` (34 passed)
  - `npm run build` (client + SSR build succeeded)

### Interrogation Reasoning Text Truncation (In Progress)

- [x] Reproduce the reasoning text cutoff path from streamed interrogation payload parsing.
- [x] Update Codex adapter structured-payload selection to avoid early fragment selection.
- [x] Add unit coverage for multi-candidate streamed JSON where early payload is partial.
- [x] Run targeted interrogation adapter tests.
- [x] Record review notes and residual risk.

Review
- Root cause: `CodexAdapter::decodeBestEffortJson()` returned the first structured payload candidate in streamed JSON, which can be an early partial fragment.
- Fix: collect all structured payload candidates and select the most complete (with later-candidate tie-break), preventing partial reasoning text from winning.
- Verification: `php artisan test tests/Unit/InterrogationCodexAdapterCommandTest.php` (11 tests passed).
- Residual risk: if a later payload is semantically incorrect but structurally richer, it could still be selected; existing schema + command flow keep this risk low.

### Discord Active Runs Formatter Crash (Completed)

- [x] Reproduce Discord-side `List Active Runs Failed` error from formatter output contract.
- [x] Patch `ChatResponseFormatter` runs rendering to support both `job_name` and nested `job.name` payload shapes.
- [x] Extend intent/handler path for `list my active jobs` to map to jobs list with active filtering.
- [x] Add regression coverage for nested run payload formatting.
- [x] Run targeted messenger tests and confirm green.

Review
- Root cause: `ChatResponseFormatter::formatRunsList*()` directly accessed `$run['job_name']`, but `RunsListActiveHandler` returns runs shaped like `{ id, status, job: { name } }`.
- Fix: formatter now safely resolves job name from either `job_name` or `job.name`, with defensive fallbacks for run fields.
- Verification: `php artisan test tests/Feature/Messenger/ChatOrchestrationTest.php tests/Feature/Messenger/ChatActionExecutorTest.php tests/Feature/Messenger/AccountLinkTest.php`.
- Residual risk: other action formatter contracts still have loose schema coupling; dedicated DTO alignment remains a future hardening task.

### Global Notification Drawer + Header Bell (In Progress)

- [x] Add backend notification persistence and API endpoints for list/mark-read/clear-all.
- [x] Share notification summary in Inertia props for global header usage.
- [x] Add header notification bell beside theme switcher with unread count badge.
- [x] Implement right-side notification drawer/slideover with system-wide notification list.
- [x] Implement per-notification "Mark as read" action.
- [x] Implement "Clear all" delete action.
- [x] Verify with targeted feature tests and frontend build.
- [x] Record review notes and residual risks.

Review:
- Added `notifications` table migration for Laravel database notifications.
- Added notification API endpoints: list, mark-one-read, mark-all-read, and clear-all.
- Added global notification presenter + shared Inertia payload for immediate header hydration.
- Added desktop/mobile bell trigger in app header and a right-side drawer with refresh, mark-read, mark-all-read, clear-all, and action links.
- Verification: `php artisan test --filter=NotificationApiTest`, `php artisan test --filter=OutboundMessageFailedNotificationTest`, `npm run build`.
- Residual risk: approval/retry events not emitted as notifications yet in all modules; drawer is ready to surface them as soon as producers persist database notifications.

### Discovery Wizard Full Session Validation (Natural Language Scheduling)

- [ ] Create a brand-new discovery session using `docs/discovery/natural-language-scheduling.md` as the feature brief on `https://agent.test`.
- [ ] Drive the session end-to-end (setup → tech stack → discovery → interrogation → summary → planning → rules → tasks → build execution).
- [ ] Capture UI parity findings at every stage/control surface against Figma reference.
- [ ] Patch wizard subcomponents that still use legacy styling and do not match the Figma visual language.
- [ ] Re-run the same session flow checks in browser after patching.
- [ ] Record verification results and any residual gaps.

### Interrogation Loop — Codex Duplicate Question Fix (In Progress)

- [x] Compare Codex vs Claude interrogation round flow to isolate loop vector.
- [x] Add runtime duplicate-intent guard for already-answered questions in interrogation.
- [x] Add repair retry path that forces a materially different question and resets Codex resume state when needed.
- [x] Add regression tests for duplicate-question loop prevention.
- [x] Run targeted interrogation unit tests and capture outcomes.

Review
- Root cause: `ExecuteInterrogationRoundJob` accepted schema-valid questions without checking semantic duplication against already-answered questions, allowing Codex to loop across near-identical visibility/versioning prompts with new IDs.
- Added duplicate-intent detection in round execution using answered-question history, text similarity, option similarity, selected-answer overlap, and topic overlap.
- Added parity continuity prompting for interrogation rounds so both runners receive resolved-decision context in every turn (not only implicit CLI resume state).
- Added automatic repair flow: request a materially different unresolved question; if duplicate persists on resumed Codex thread, retry with `cli_session_id` cleared and a context-recovery prompt.
- Replaced hard failure on persistent duplicate output with non-terminal auto-resolution: duplicate question is auto-answered from prior confirmed answer and interrogation auto-advances to next unresolved question.
- Added bounded auto-recovery depth (`agent.interrogation.duplicate_recovery_max_depth`, default `4`) to prevent endless self-recursion.
- Added unit coverage in `tests/Unit/ExecuteInterrogationRoundJobTest.php` for duplicate repair, resume-reset recovery, and persistent-duplicate auto-resolution behavior.
- Verification:
  - `php artisan test tests/Unit/ExecuteInterrogationRoundJobTest.php`
  - `php artisan test tests/Unit/QuestionPayloadGuardTest.php tests/Unit/InterrogationCodexAdapterCommandTest.php`

### Discord `/jobs list` Slash Command Failure (Completed)

- [x] Reproduce and confirm why Discord slash `/jobs list` resolves to fallback intent.
- [x] Normalize Discord slash payloads (`jobs`/`runs`) into parser-compatible command text.
- [x] Acknowledge `INTERACTION_CREATE` in Discord Gateway mode to prevent "application did not respond".
- [x] Add regression tests for slash-content normalization and gateway interaction ACK.
- [x] Run targeted messenger test slices and capture outcomes.

Review:
- Root causes:
  - Slash payload text normalization produced `jobs list` (and dropped nested option values), which missed existing parser patterns expecting phrases like `list my jobs`.
  - Discord Gateway `INTERACTION_CREATE` events were dispatched to async processing without first acknowledging interaction callbacks, causing Discord timeout banners.
  - Discord Gateway payload parsing treated `INTERACTION_CREATE` as `MESSAGE_CREATE`, yielding empty inbound content (`""`) and causing fallback "I couldn't understand that command."
- Fixes:
  - Updated `DiscordAdapter::buildInteractionContent()` to map known slash command structures (`jobs list`, `jobs run job_id`, `runs active`, `runs stop run_id`) into parser-friendly canonical text and to recursively flatten nested options in fallback mode.
  - Updated `DiscordAdapter::parseGatewayEvent()` to route `INTERACTION_CREATE` events through interaction parsing instead of message parsing.
  - Updated `DiscordGatewayWorker` to immediately acknowledge interactions through Discord callback API (`/interactions/{id}/{token}/callback`), returning type `5` for normal interactions and type `8` for autocomplete.
  - Added regression tests for slash normalization and gateway ACK behavior.
- Verification:
  - `php artisan test tests/Unit/Messenger/Adapters/DiscordAdapterTest.php tests/Unit/Messenger/Gateway/Workers/DiscordGatewayWorkerTest.php` (32 passed)
  - `php artisan test tests/Feature/Messenger/Webhooks/DiscordWebhookTest.php` (19 passed)

### Agent Org Layer — AI Workforce (Next Feature)

- [ ] Complete discovery session (currently in Setup).
- [ ] Drive through interrogation, summary, planning, and build phases.
- [ ] Implement Phase A: Foundation (org_agent_profiles, org_reporting_edges, org_cost_ledgers).
- [ ] Implement Phase B: Ritual Runtime (org_ritual_templates, org_ritual_runs).
- [ ] Implement Phase C: Council/QA Gates (org_council_templates, org_artifact_reviews, org_escalations).
- [ ] Implement Phase D: Governance (cost ledger thresholds, budget enforcement).
- [ ] Implement Phase E: Hardening (CI compatibility, multi-provider testing).

### Agent Org Layer — Run 3 Amendments (Completed)

- [x] Add top padding to Org Layer empty states so cards are visually aligned with other surfaces.
- [x] Replace placeholder Org Agent create/edit pages with API-backed forms (delegatee + capability bindings).
- [x] Replace placeholder Ritual create/show pages with API-backed forms and details.
- [x] Make Org Agents and Rituals index pages data-driven (list existing records + actionable empty states).
- [x] Make Councils, Escalations, and Costs pages data-driven enough to confirm implementation state.
- [x] Verify org UI routes + org API tests + frontend build, and record review notes.

Review:
- Implemented API-backed org UI flows for agents, rituals, councils, escalations, and costs to replace static placeholders.
- Added real create forms for org agents, rituals, and councils with validation/error handling and redirect-to-index on success.
- Fixed UUID route parameter typing in org web routes (`agents/{id}/edit`, `rituals/{id}`) to prevent type errors with UUID IDs.
- Standardized top spacing on empty-state cards using `pt-16 pb-12` across org surfaces.
- Verification:
  - `php artisan test tests/Feature/OrgUiRoutesTest.php tests/Feature/OrgFeatureGateTest.php tests/Feature/Http/Controllers/Api/V1/Org` (41 passed)
  - `npm run build` (client + SSR build succeeded)

### Agent Org Layer — Migration Sync Hotfix (Completed)

- [x] Investigate `500` failures from Org API (`/org/rituals`, `/org/agents`, `/org/costs`) reported in browser console.
- [x] Confirm root cause via Laravel logs and migration status.
- [x] Apply pending Org migrations in runtime database.
- [x] Re-run Org API feature test slice and confirm all green.

Review:
- Root cause: Org Layer schema migrations were pending in local runtime DB, causing `SQLSTATE[42P01]` undefined-table failures (`org_ritual_templates`, `org_agent_profiles`, `org_cost_ledgers`, etc.).
- Fix: executed `php artisan migrate` and applied all Org migrations in batch 10.
- Verification:
  - `php artisan migrate:status` (Org migrations now `Ran`)
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org` (34 passed)

### Agent Org Layer — API Resilience Hardening (Completed)

- [x] Add defensive table-existence guards for Org index/summary endpoints used by page load.
- [x] Ensure fallback responses preserve frontend payload shapes (`data: []`, cost summary defaults).
- [x] Re-run Org route/feature/API test slices.

Review:
- Added schema guards to prevent `500` on Org page loads when runtime schema is incomplete or mismatched:
  - `OrgAgentController@index` (requires `org_agent_profiles`, `delegatee_profiles`)
  - `OrgRitualController@index` (requires `org_ritual_templates`)
  - `OrgCouncilController@index` (requires `org_council_templates`)
  - `OrgEscalationController@index` (requires `org_escalations`, `org_ritual_runs`)
  - `OrgCostController@summary` (requires `org_cost_ledgers`; returns zeroed summary otherwise)
- Added fail-safe `try/catch` handling on Org first-load list/summary endpoints so unexpected runtime exceptions degrade to stable empty/default responses instead of returning `500`.
- Moved cost-summary date parsing inside guarded path to prevent pre-fallback exceptions from invalid date inputs.
- Verification:
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org tests/Feature/OrgFeatureGateTest.php tests/Feature/OrgUiRoutesTest.php` (41 passed)
  - `curl /agent/api/v1/org/costs/summary` (HTTP 200)
  - `curl /agent/api/v1/org/costs/summary?start_date=abc&end_date=def` (HTTP 200 fallback payload)

### AgentKeeper-Inspired Cognitive Continuity Integration Discovery (In Progress)

- [x] Audit current memory/delegation implementation and identify exact integration seams for provider-switch continuity.
- [x] Analyze AgentKeeper implementation patterns and extract reusable concepts only.
- [x] Write discovery brief with architecture fit, phased rollout, API/data-model changes, risks, and acceptance criteria.
- [x] Record review notes and residual risk.

Review:
- Added `docs/discovery/agentkeeper-cognitive-continuity-integration-brief.md` with a concrete integration design anchored to existing memory/delegation hooks (`MemoryContextBuilder`, `MemoryFormationPipeline`, `ExecuteAgentRunJob`, `AttemptSpawner`).
- Recommendation is to adopt AgentKeeper patterns (critical fact continuity + token-budget reconstruction) as a native additive layer, not as a vendored dependency.
- Residual risk: continuity fact auto-promotion quality and scope leakage require careful thresholding + classification/scope guards during implementation.

### Discovery/Interrogation/Planning UX + Codex Parity Fix Set (Completed)

- [x] Investigate and patch planning-phase transient empty-state flicker (`Generating plan...` + robust sync/refetch while planning).
- [x] Fix setup/tech stack layout to remove dead right column or provide meaningful contextual content.
- [x] Improve Codex discovery/interrogation ongoing activity visibility to match Claude parity.
- [x] Harden duplicate re-ask prevention parity for Codex interrogation flow end-to-end.
- [x] Redesign active run AI log rendering for readability (structured timeline, grouped lifecycle, heartbeat collapse, command cards, severity styling, truncation/expand).
- [x] Surface clear operator signal when queue worker is not running and discovery/planning cannot progress.
- [x] Detect/classify Codex MCP connection-refused failures and surface a deduplicated actionable issue in Active Run UI and progress state.
- [x] Add/update tests for touched backend transitions and duplicate-question regression protection.
- [x] Run verification commands and capture exact before/after behavior + residual risks.

Review:
- Added deterministic planning hydration behavior in the wizard: planning-without-plan now renders as generating, with stronger auto-refetch (`include_events=1`) and websocket-triggered immediate re-sync for plan events.
- Removed setup/tech stack/discovery dead-column layout by conditionally hiding the right stats rail before interrogation and expanding content to full width.
- Upgraded runner visibility during discovery/interrogation with live activity timelines (including Codex issue/error/status messages) instead of static placeholders.
- Added backend operator signals for queue-stall scenarios (`QUEUE_WORKER_UNAVAILABLE`) and surfaced them in the wizard.
- Added MCP connection-refused classification at both runtime ingest and frontend presentation layers, with deduplicated issue cards and actionable remediation text.
- Replaced raw active-run `<pre>` log block with structured timeline rendering: command cards (status/exit/duration/output expand), lifecycle grouping, heartbeat collapse/summary, stdout/stderr severity styling, and expandable truncation for long outputs.
- Verification:
  - `php artisan test tests/Feature/InterrogationApiWorkflowTest.php --filter="show_keeps_planning_session_with_missing_plan_in_generation_state|show_includes_operator_signal_when_discovery_queue_appears_stalled|show_includes_operator_signal_when_plan_generation_appears_stalled|show_marks_operational_status_plan_as_not_meaningful"` (4 passed)
  - `php artisan test tests/Unit/ExecuteInterrogationRoundJobTest.php --filter="repairs_semantic_duplicate_when_prior_answer_selected_multiple_options|repairs_duplicate_question_against_answered_history|retries_duplicate_repair_without_resume_for_codex_session|does_not_fail_when_duplicate_question_persists_after_repairs"` (4 passed)
  - `php artisan test tests/Feature/AgentRunnerLifecycleTest.php --filter="mcp_connection_refused_output_sets_deduplicated_mcp_issue_metadata"` (1 passed)
  - `npm run build` (client + SSR build succeeded)

---

## Completed — Session Log

### 2026-03-01 — Atom-of-Thought (AoT) Reasoning Integration Discovery Brief

- [x] Review current planning, build-task generation, and run execution architecture for integration points.
- [x] Define AoT legitimacy/risk positioning and integration recommendation specific to Agent.
- [x] Produce implementation-ready discovery brief with rollout phases, goals, constraints, and acceptance criteria.

Review:
- Added discovery brief at `docs/discovery/atom-of-thought-reasoning-integration.md`.
- Recommendation is AoT-lite: planning + task generation first, conditional execution usage for compound tasks, summary unchanged.
- Proposal keeps STAR runtime primitives in place and treats AoT as additive, config-flagged, and A/B measurable.

### 2026-03-01 — Agent Memory (Session 14)

- [x] Complete Agent Memory v3 discovery brief with gap analysis.
- [x] Resolve all 14 architectural decisions (Neo4j required, Docker Compose, fixed 1536d, etc.).
- [x] Add feature flags: `memory.enabled`, `memory.api_enabled`.
- [x] Fix FeatureFlagManager integration across MemoryEnabled middleware, MemoryCapabilityResolver, MemoryServiceProvider.
- [x] Fix settings validation (add rate limit keys to whitelist).
- [x] Fix settings routes (move outside MemoryEnabled middleware gate).
- [x] Fix Vue numeric coercion (`.toFixed()` crash on string values).
- [x] Fix broadcasting auth 403 (add `Broadcast::routes()`, fix `window.userId` → `usePage().props.auth.user.id`).
- [x] Neo4j setup via Docker Compose verified.

### 2026-02-28 — Agent Gaps (Session 13)

- [x] Resolve 11 production gaps across messenger, delegation, and compliance.
- [x] Consolidate stub handlers from parallel implementations.

### 2026-02-28 — Messenger Control Plane Gaps (Session 13)

- [x] Processor attachment handling fixes.
- [x] Socket worker graceful drain.

### 2026-02-28 — Messenger CP Local-First & Provider Parity (Session 11)

- [x] ReactPHP/Amp gateway supervisor with reconnection strategy.
- [x] All 4 providers with threading, signature verification, attachment handling.

### 2026-02-28 — Stabilize PHPUnit Suite

- [x] Resolve 33 failing tests (webhook aliases, delegation naming, DB safety).
- [x] Resolve 13 remaining warnings/errors (deprecated metadata, migration state).
- [x] Final result: 1651 passed, 7 skipped, 0 failed.

### 2026-02-28 — Discord + WhatsApp Connector Activation

- [x] Register Discord and WhatsApp adapters in `config/messenger.php`.
- [x] Replace webhook stubs with real interaction/message handling.
- [x] Fix middleware timestamp verification for Discord headers.
- [x] Add feature tests for webhook dispatch and schema exposure.

### 2026-02-27 — Natural Language Scheduling (Session 5) ✅

- [x] Hybrid parser: rule-based + LLM fallback at <75% confidence.
- [x] Active-hours scheduling with ISO-8601 day indexing.
- [x] Parse attempt tracking with 90-day retention.
- [x] Rate limits: 10/min, 60/hour on LLM path.

### 2026-02-27 — Workflow Orchestration Instruction (Session 5) ✅

- [x] Phase 1: Shared Policy Layer — ComplexityClassifier, OrchestrationPolicyService, ComplianceFlagResolver.
- [x] Phase 2: Plan Mode Default — pre-execution policy evaluation, mandatory planning for non-trivial work.
- [x] Phase 3: Subagent Strategy — build-task generation prompt requirements, event evidence parsing.
- [x] Phase 4: Self-Improvement Loop — LessonsManager with file-based storage, trigger signals, context injection.
- [x] Phase 5: Verification Before Done — VerificationEvidenceEvaluator, evidence by task category.
- [x] Phase 6: Demand Elegance + Autonomous Bug Fixing — elegance checkpoint, root-cause evidence gate.
- [x] Phase 7: Task Management Workflow Enforcement — task-file policy checks.
- [x] Phase 8: API/UI/Observability — compliance summary fields, metrics dimensions.
- [x] Phase 9: Testing + Rollout — unit + feature tests, progressive rollout.
- [x] Feature flags: `compliance.enabled`, `compliance.enforcement_mode`, and 4 gate flags.

### 2026-02-27 — Figma Make UI Parity (Session 6) ✅

- [x] Class-based dark mode with system preference detection.
- [x] Design tokens with RGB-channel CSS variables and alpha support.
- [x] 20+ base UI components, DM Sans + JetBrains Mono typography.
- [x] Lucide Vue Next icons, 1440px content max-width.
- [x] Discovery wizard and build step parity alignment.
- [x] Dark mode form field regression fixes.
- [x] Phase stepper and monitor metrics polish.

### 2026-02-27 — STAR Reasoning & Delegation Integration (Session 10) ✅

- [x] STAR Preamble Generator (Situation/Task/Action/Result).
- [x] A/B testing infrastructure with configurable percentage split.
- [x] Targeted retry service with trust calibration.

### 2026-02-26 — Adversarial Reviewer (Session 4) ✅

- [x] AdversarialReviewerService via Claude CLI subprocess.
- [x] Verdict types: pass, revise, needs_clarification.
- [x] Shadow mode for safe rollout.

### 2026-02-26 — Feature Flags Management ✅

- [x] DB-backed FeatureFlagManager with config/env fallbacks.
- [x] Authenticated API endpoints for read/update.
- [x] Tools settings page UI.
- [x] Runtime gates rewired (delegation, reviewer, memory, compliance).

### 2026-02-25 — Consolidated Implementation / Delegation v1 (Session 2) ✅

- [x] 9 models, 17 services, 3 controllers, 2 policies, 7 Vue pages.
- [x] DAG execution with verification pipeline (Automated, AI Critic, Human Approval).
- [x] Trust scoring, contract enforcement, recovery chain.

### 2026-02-21 — Messenger Control Plane (Session 9) ✅

- [x] 4 provider integrations (Slack, Telegram, Discord, WhatsApp).
- [x] 7 chat-driven user flows.
- [x] Circuit breaker, rate limiting, replay deduplication, dead-letter queue.

---

## Historical Fix Log

### 2026-02-28 — False Positive Detection Hardening

- [x] ASCII arrow snippet guard (`N->`, `N=>`).
- [x] Escaped newline snippet guard (`\n`, `\\n`).
- [x] Double-escaped snippet guard (`\\\\n`).
- [x] Inline source-snippet heuristic for Vue/JS template payloads.
- [x] Structured output handling guards for non-runtime events.
- [x] Final lifecycle test count: 23 passed.

### 2026-02-25 — Build Execution Fixes

- [x] Queue worker management for `interrogation` supervisor.
- [x] Reconcile `process_not_found` runs into build progression.
- [x] DB safety guardrail (pgsql_testing enforcement).
- [x] Unified build AI log with duplicate suppression.
- [x] Monitor log formatting for fragmented JSON envelopes.

### 2026-02-27 — UI Polish

- [x] Dark mode form field regression (discovery wizard).
- [x] Build step (phase 9) parity alignment.
- [x] Phase stepper active circle clipping.
- [x] Monitor metrics card spacing.
- [x] Planning panel top spacing.

### 2026-03-02 — Codex Build False-Completion Guard

- [x] Added explicit non-interactive runner-mode instructions to interrogation build task markdown to prevent “plan then stop” behavior.
- [x] Added Codex-only execution-evidence gate in build finalization: successful run must show actionable mutation/verification command evidence.
- [x] Added explicit `BUILD_TASK_NO_EXECUTION_EVIDENCE` error path for successful-but-no-op runs.
- [x] Added unit coverage for no-evidence Codex success path and runner-mode markdown contract.
- [x] Verification: `php artisan test tests/Unit/BuildTaskRunFactoryTest.php tests/Unit/ExecuteInterrogationBuildJobTest.php` (12 passed).

### 2026-03-02 — Codex Build Evidence Tightening

- [x] Tightened Codex build evidence gate to require implementation-path mutation evidence plus verification command evidence.
- [x] Added regression coverage for checklist-only/meta-file mutation runs (must fail) and implementation+verification runs (must pass).
- [x] Verification: `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php tests/Unit/BuildTaskRunFactoryTest.php tests/Unit/InterrogationCodexAdapterCommandTest.php` (26 passed).

### 2026-03-02 — Session 15 Task 6: Authenticated Read-Only Docs Contracts

- [x] Write failing feature tests in `tests/Feature/Documentation/DocsAuthorizationTest.php` for:
- [x] Authenticated access to docs read routes (`/docs`, `/docs/{slug}`, docs API reads).
- [x] Unauthenticated denial behavior (web redirect + API 401).
- [x] No docs write route surface (no POST/PUT/PATCH/DELETE routes under docs paths).
- [x] Unauthorized role is forbidden from docs coverage endpoint.
- [x] Unknown `slug` / `uiKey` and malformed search query validation paths.
- [x] Run `php artisan test --filter=DocsAuthorizationTest` and capture expected failures.
- [x] Wire read-only routes in `routes/web.php` and `routes/api.php` with auth middleware.
- [x] Implement docs web/API controllers and docs search request validation.
- [x] Add coverage authorization ability wiring and enforce it on coverage endpoint.
- [x] Re-run `php artisan test --filter=DocsAuthorizationTest` until green.
- [x] Verify route contracts with `php artisan route:list --path=docs`.
- [x] Verify there are no docs write routes via route inspection command.
- [x] Add review notes with verification evidence and known limitations.

Review (to complete after implementation):
- [x] Evidence summary: `php artisan test --filter=DocsAuthorizationTest` passed (9 tests, 18 assertions); `php artisan route:list --path=docs` shows 5 `GET|HEAD` docs routes; `php artisan route:list | rg "docs" | rg "POST|PUT|PATCH|DELETE"` returned no matches.
- [x] Conditions for correctness: existing auth middleware (`auth:sanctum` + verified web group) remains active; role-based gate checks continue to use `User::hasRole(['admin','analytics'])`; docs catalog provides at least one valid slug (`overview`) and fragment key (`docs.overview`).
- [x] Explicit non-goals / limitations: this task only adds read-contract wiring and minimal in-memory catalog data; no persistence/search-index integration, no authoring/write endpoints, and no dynamic coverage computation from full docs ingestion pipeline.

### 2026-03-02 — Session 15 Task 2: Define Repo Content Contract and Validators

Pre-Execution Goal Articulation (STAR):
- Situation: Runtime docs tables/models and read routes already exist, but there is no canonical repo contract config, no strict parser/validator for markdown front matter or tooltip YAML fragments, and no `docs:validate` command. Current docs root contains many files outside the new contract layout, so validation must scope to explicit configured directories only.
- Task: Introduce strict contract enforcement for repo-based docs content under `docs/product/**`, `docs/api/**`, and `docs/tooltips/**`, with deterministic validation errors for malformed front matter and tooltip schema violations, and with test-backed behavior.
- Action: Add failing unit tests first; add `config/documentation.php` and `docs/README.md`; implement parser/validator classes under `app/Support/Documentation/Ingestion` and `app/Support/Documentation/Schemas`; add `app/Console/Commands/DocsValidateCommand.php`; add minimal conforming fixture content under contract directories; run required tests and command.
- Result: `php artisan test --filter=DocsContractValidationTest` and `php artisan docs:validate` pass with no validation errors on in-repo contract files; tests prove strict rejection of malformed/invalid cases.

Assumptions and Scope Boundaries:
- [x] English-only locale enforcement is phase-1 strict (`en` only).
- [x] Validation is file-contract-only; no runtime upsert/model persistence changes in this task.
- [x] Existing non-contract docs outside configured directories are out of scope for this validator.
- [x] Contract directories are the only source scanned by validator.

Failure Modes to Defend:
- [x] Malicious-caller: malformed YAML front matter, invalid types (e.g. scalar where array required), unknown severity, unsupported locale/domain, invalid/unsafe links, non-http(s) URLs.
- [x] Tired-maintainer: missing required metadata (`owner`, review fields), accidental typo in keys, linkage arrays provided as strings, overly long tooltip short text.

Implementation Checklist:
- [x] Add unit test file `tests/Unit/Documentation/DocsContractValidationTest.php` with failing cases first.
- [x] Run `php artisan test --filter=DocsContractValidationTest` and capture failing output.
- [x] Add `config/documentation.php` (paths, required metadata, allowed domains/severity, locale defaults, allowed link hosts).
- [x] Add canonical contract documentation in `docs/README.md`.
- [x] Implement markdown front matter parser + validation under `app/Support/Documentation/Ingestion/` and `app/Support/Documentation/Schemas/`.
- [x] Implement tooltip YAML parser + schema validation under `app/Support/Documentation/Ingestion/` and `app/Support/Documentation/Schemas/`.
- [x] Add `app/Console/Commands/DocsValidateCommand.php`.
- [x] Add minimal conforming files under `docs/product/**`, `docs/api/**`, `docs/tooltips/**` so command can pass in this repo.
- [x] Re-run `php artisan test --filter=DocsContractValidationTest` until green.
- [x] Run `php artisan docs:validate` and confirm success output.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - `php artisan test --filter=DocsContractValidationTest` initially failed with missing class (`DocsContractValidator`), then passed after implementation (`6 passed, 14 assertions`).
  - `php artisan docs:validate` passed with summary: `Validated markdown files: 2, tooltip files: 1, tooltip fragments: 1`.
  - `php artisan test --filter=Documentation` passed (`27 passed, 78 assertions`) to confirm no regressions in existing docs feature/schema tests.
- Conditions where this works:
  - Contract source files live only under configured directories: `docs/product/**`, `docs/api/**`, `docs/tooltips/**`.
  - Markdown files start with valid YAML front matter and include all required fields.
  - Tooltip YAML fragments follow the required shape with approved severities/domains and `metadata.locale=en`.
- Explicit non-goals / limitations:
  - No runtime upsert/sync into `DocumentationEntry`/`DocumentationFragment` models in this task.
  - No localization support beyond strict phase-1 `en`.
  - Validator does not scan legacy docs outside configured contract directories.

### 2026-03-02 — Codex Build Evidence False Negative (Task 2 run #2099)

- [x] Reproduce and document the evidence mismatch using run #2099 event shape (`file_change` + verification commands).
- [x] Update Codex evidence collector to treat `item.type=file_change` paths in implementation roots as valid implementation mutations.
- [x] Keep strict gate requiring both implementation evidence and verification command evidence.
- [x] Add regression test for `file_change`-driven edits + `php artisan test` verification to ensure task completes.
- [x] Run targeted tests and capture exact pass output.

Review (to complete after implementation):
- Root cause: `ExecuteInterrogationBuildJob::collectCodexExecutionEvidence()` only counted mutation *commands* and ignored `item.type=file_change` events. Run `#2099` performed real edits via `file_change` plus verification commands, so implementation mutation count stayed zero and the task was falsely failed.
- What changed: Added `file_change` path extraction and implementation-path classification to evidence collection. `has_actionable_execution` now requires `(implementation mutation commands + implementation file changes) > 0` plus verification commands. Added regression test `test_codex_success_with_file_change_events_and_verification_evidence_completes_task`.
- Verification commands/results: `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php` passed (`12 passed, 81 assertions`), including the new file-change regression.
- Conditions for correctness: Run events must include either command mutation evidence or `file_change` entries touching implementation roots (`app`, `database`, `config`, `routes`, `resources`, `tests`, `scripts`, `docs`) and at least one recognized verification command (`php artisan test`, `composer test`, `phpunit`, `pest`, `npm test`, `vitest`, `jest`, `pytest`).
- Explicit non-goals: This fix does not loosen evidence policy for planning-only runs and does not infer verification from narrative text alone.

### 2026-03-02 — Codex Build Revalidation False Negative (Task 2 run #2102)

- [x] Reproduce the rerun edge case where task implementation already exists and run performs verification-only checks.
- [x] Extend evidence policy with strict revalidation path: no new mutations + successful verification commands + explicit already-implemented signal.
- [x] Persist execution evidence on successful codex runs for observability/debugging parity with failure paths.
- [x] Add regression tests for both positive revalidation and negative verification-only-without-signal behavior.
- [x] Run targeted unit suite and capture output.

Review:
- Root cause: Evidence gate required per-run implementation mutations, so reruns that correctly verified already-implemented work were incorrectly failed.
- What changed:
  - `ExecuteInterrogationBuildJob` now accepts a revalidation path when:
    - implementation mutation count is zero,
    - verification evidence is present and successful (with fallback when completion telemetry is absent),
    - agent output explicitly signals already-implemented/existing state.
  - Execution evidence is now persisted on both success and failure outcomes.
- Verification:
  - `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php` => `14 passed (87 assertions)`.
- Conditions for correctness:
  - Still requires verification commands for codex build success.
  - Planning-only runs with no implementation/revalidation evidence continue to fail.
- Explicit non-goals:
  - No retroactive reclassification of previously failed tasks was implemented here.

### 2026-03-02 — Session 15 Task 2 Revalidation (Task run #2103)

- [x] Re-audit docs contract implementation against task scope (contract config, parser/schema validators, docs command, contract docs).
- [x] Re-run required verification commands from task instructions.

Review:
- Verification:
  - `php artisan test --filter=DocsContractValidationTest` => passed (`6 passed, 14 assertions`).
  - `php artisan docs:validate` => passed (`Validated markdown files: 2, tooltip files: 1, tooltip fragments: 1`).
- Scope confirmation:
  - Validation enforcement remains limited to `docs/product/**`, `docs/api/**`, and `docs/tooltips/**`.
  - Runtime model upsert/sync remains intentionally out of scope for this task.

### 2026-03-02 — Session 15 Task 2 Follow-up: Commit-Time Code-Derived Docs Generation

- [x] Fix docs generation regression where `source_path` used scoped values (`product/...`, `api/...`) and runtime snapshot injection skipped real files.
- [x] Add `docs:generate` command test coverage for snapshot block refresh + generated artifact writes.
- [x] Wire generation output paths through config for testability and deterministic overrides.
- [x] Run docs generation/validation/coverage/sync verification end-to-end.
- [x] Update docs contract README with generation + hook activation guidance.

Review:
- Root cause:
  - Generation used `File::exists($sourcePath)` directly against scoped ingestion paths (`product/...`, `api/...`), so runtime snapshot updates silently no-op’d.
- What changed:
  - Added `DocsGenerationService::resolveEntrySourcePath()` to map scoped source paths to configured docs roots (`documentation.paths.product`, `documentation.paths.api`).
  - Added generation output-path config keys in `config/documentation.php` (`documentation.generation.output_paths.*`).
  - Added `tests/Feature/Documentation/DocsGenerateCommandTest.php` to lock snapshot-update behavior and unsupported-source failure behavior.
  - Updated `scripts/docs/sync.sh` to run `docs:generate` before `docs:validate`, `docs:coverage`, and `docs:sync`.
  - Kept commit mode non-blocking by default (`DOCS_SYNC_STRICT_COMMIT=0`) and documented strict override (`DOCS_SYNC_STRICT_COMMIT=1`).
  - Updated surface-link selection scoring to avoid broad index pages overriding specific surface docs in generated coverage tables.
- Verification:
  - `php artisan test tests/Feature/Documentation/DocsGenerateCommandTest.php tests/Integration/Documentation/DocsAutomationFlowTest.php` => passed (`6 passed, 39 assertions`).
  - `php artisan test --filter=Documentation` => passed (`65 passed, 439 assertions`).
  - `php artisan docs:generate --source=repo` => passed (`Files written: 3 | Snapshot files updated: 3 | API routes indexed: 158`).
  - `php artisan docs:validate` => passed (`Validated markdown files: 17, tooltip files: 2, tooltip fragments: 14`).
  - `php artisan docs:coverage --fail-on-missing` => passed (`Coverage: 100.00% (12/12 surfaces)`).
  - `php artisan docs:sync --mode=commit --source=repo` => passed (`Entries: 17 | Fragments: 14 | Links: 109`).
  - `npm run build` => passed (client + SSR).
- Conditions for correctness:
  - `git config core.hooksPath .githooks` must be set for plain `git commit` to run the shared pre-commit hook.
  - Docs source-of-truth remains `docs/product/**`, `docs/api/**`, `docs/tooltips/**`; generation derives structured updates from code/routes/front matter, not free-form narrative prose.
- Explicit non-goals:
  - No LLM-based natural-language authoring of new long-form prose was introduced.
  - No external search backend/bootstrap (Typesense Docker provisioning) was changed in this follow-up.

### 2026-03-02 — Session 15 Task 7: Ship Docs Center Pages and Navigation Discoverability

Pre-Execution Goal Articulation (STAR):
- Situation: Docs web routes (`/docs`, `/docs/{slug}`), `DocsPageController`, docs Inertia pages (`Docs/Index`, `Docs/Show`), and authenticated nav links in `AppLayout.vue` already exist in the repository. A dedicated feature test file (`DocsNavigationTest`) also exists and currently passes. Remaining risk is proving discoverability/reachability with explicit in-app link target assertions and producing fresh verification evidence for this task run.
- Task: Ensure authenticated users can discover and reach docs in-app without direct URL, `/docs/{slug}` resolves from a docs index link target, and edge paths (empty dataset, unknown slug, guest access) remain correct, with command-level verification evidence.
- Action:
  1. Update `tests/Feature/Documentation/DocsNavigationTest.php` first to explicitly prove in-app link target reachability and empty dataset behavior.
  2. Run `php artisan test --filter=DocsNavigationTest` to verify expected behavior for the updated suite.
  3. Confirm docs UI shell + nav wiring remains correct in `DocsPageController`, `resources/js/Pages/Docs/{Index,Show}.vue`, and `resources/js/Layouts/AppLayout.vue`.
  4. Run `npm run build` for frontend validation.
  5. Record evidence, conditions for correctness, and explicit non-goals.
- Result: Task is complete when updated docs navigation tests pass, frontend build passes, nav discoverability is explicitly covered by test assertions, and review notes capture exact command outputs and boundaries.

Assumptions and Scope Boundaries:
- [x] Existing authenticated layout (`AppLayout.vue`) is the shared shell for docs-visible users.
- [x] Task scope is limited to docs center UI shell and authenticated nav discoverability; no tooltip integration.
- [x] Docs catalog may return empty results and index page must still render successfully.

Failure Modes to Defend:
- [x] Malicious-caller: unauthenticated access to docs routes (must redirect to login).
- [x] Tired-maintainer: accidental nav link removal or mismatch between in-app link target and docs show route.
- [x] Runtime edge: unknown slug should return 404, and empty dataset should not crash docs index.

Implementation Checklist:
- [x] Update `tests/Feature/Documentation/DocsNavigationTest.php` first with explicit link-target reachability and empty dataset coverage.
- [x] Run `php artisan test --filter=DocsNavigationTest` and capture result.
- [x] Validate docs controller/page/layout wiring still matches task scope.
- [x] Run `npm run build` and capture result.
- [x] Add review section with verification evidence, conditions where this works, and explicit non-goals.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - `php artisan test --filter=DocsNavigationTest` => passed (`6 passed, 47 assertions`), including new coverage for empty docs dataset and docs show link-target contract in `Docs/Index.vue`.
  - `npm run build` => passed for both client and SSR builds; only non-blocking bundle-size/import warnings were emitted.
- Conditions where this works:
  - Authenticated users render the shared `AppLayout.vue` navigation shell (desktop + responsive) where `route('docs.index')` is present.
  - Docs index/show routes remain named `docs.index` and `docs.show`, and `DocsPageController` continues to render Inertia `Docs/Index` + `Docs/Show`.
  - Docs catalog entries expose valid `slug` values for index-to-show navigation.
- Explicit non-goals / limitations:
  - No tooltip component/runtime integration was added in this task.
  - This task does not introduce docs authoring, sync orchestration, or search UX changes beyond existing behavior.
  - Frontend verification is build-level; no browser E2E/visual verification was added in this run.

### 2026-03-02 — Session 15 Task 8: Implement reusable HelpHint component and tooltip registry resolution

Pre-Execution Goal Articulation (STAR):
- Situation: Docs fragment read API route exists at `GET /agent/api/v1/docs/fragments/{uiKey}` but currently resolves from static `DocsCatalog` data and has no reusable lookup service, no structured telemetry for missing tooltip keys, and no shared `HelpHint` component or frontend component-test harness.
- Task: Deliver a reusable `HelpHint` component plus backend `TooltipRegistryService` resolution path that supports API fallback, silent miss behavior for users, feature-flag-aware fragment availability, and structured telemetry for missing keys.
- Action:
  1. Add/adjust backend feature tests for fragment API + registry miss/disabled paths and telemetry assertions.
  2. Add frontend component tests (keyboard focus/dismiss, ARIA labels, mobile tap fallback, timeout/missing-key silent behavior) using a JS unit-test harness.
  3. Implement `TooltipRegistryService`, telemetry service hook, and controller wiring.
  4. Implement `resources/js/Components/HelpHint.vue` with short/long text, severity variants (`info|warning|risk`), optional learn-more link, and graceful empty render behavior.
  5. Run targeted PHP tests, JS tests, and `npm run build`; capture exact results.
- Result: Task is complete when tests prove accessible interactions and silent fallback paths, API lookup behavior is service-based with telemetry on misses, and verification commands pass.

Assumptions and Scope Boundaries:
- [x] Stable `ui_key` values are passed to the component.
- [x] Fragment API endpoint remains authenticated and route shape remains `/agent/api/v1/docs/fragments/{uiKey}`.
- [x] Scope is limited to shared component + backend lookup service + telemetry hook; no broad page rollout.

Failure Modes to Defend:
- [x] Malicious-caller: malformed or unknown `ui_key` probes should not surface internal errors.
- [x] Tired-maintainer: missing tooltip key should not break UI and should still emit structured telemetry for follow-up.
- [x] Runtime edge: feature-flag-disabled fragments and API timeouts must degrade silently (no user-visible crash/error message).
- [x] Accessibility edge: keyboard-only users must be able to focus/open and dismiss tooltip content.

Implementation Checklist:
- [x] Add failing backend tests in `tests/Feature/Documentation/TooltipFragmentApiTest.php`.
- [x] Add failing frontend tests in `resources/js/Components/__tests__/HelpHint.spec.ts`.
- [x] Run tests and confirm initial failures for intended reasons.
- [x] Implement `app/Support/Documentation/TooltipRegistryService.php` + telemetry hook and wire controller.
- [x] Implement `resources/js/Components/HelpHint.vue`.
- [x] Re-run relevant PHP and JS tests, then run `npm run build`.
- [x] Document review with exact evidence, conditions for correctness, non-goals, and limitations.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - Red phase:
    - `php artisan test --filter=TooltipFragmentApiTest` initially failed (`Expected 200 got 404` for DB fragment lookup and missing telemetry mock expectations unmet), proving the old controller/static catalog path did not satisfy requirements.
    - `npm run test:unit -- HelpHint` initially failed because `HelpHint.vue` did not exist.
  - Green phase:
    - `php artisan test --filter=TooltipFragmentApiTest` => passed (`3 passed, 13 assertions`).
    - `php artisan test --filter=DocsAuthorizationTest` => passed (`9 passed, 18 assertions`), confirming docs web/API auth and unknown-key contract remain valid.
    - `php artisan test --filter=DocsSearchApiTest` => passed (`4 passed, 15 assertions`), confirming no docs search regression.
    - `npm run test:unit -- HelpHint` => passed (`5 passed`).
    - `npm run build` => passed for client + SSR builds.
- Conditions where this works:
  - Tooltip fragments exist in `documentation_fragments` with `status='published'` and a non-empty `short_text`, or are available in the fallback `DocsCatalog`.
  - If a fragment defines `feature_flag`, resolution depends on `FeatureFlagManager::enabled(...)`; disabled flags are treated as silent misses.
  - `HelpHint` receives either inline content (`shortText`) or a stable `uiKey` where `/agent/api/v1/docs/fragments/{uiKey}` is reachable for authenticated users.
  - Silent miss behavior applies to missing keys, disabled fragments, API non-OK responses, and network/timeout errors.
- Explicit non-goals / limitations:
  - No mass rollout across pages was performed in this task; only the reusable component and backend lookup path were implemented.
  - API miss contract remains `404 NOT_FOUND`; silent behavior is enforced in component rendering and telemetry, not by changing HTTP semantics.
  - No end-to-end browser flow or visual regression suite was added; verification is feature tests + component unit tests + production build.

### 2026-03-02 — Session 15 Task 9: Integrate HelpHint across required product surfaces

Pre-Execution Goal Articulation (Required):
- SITUATION: `HelpHint` component + fragment API exist, but required product surfaces do not yet consistently expose a discoverable docs entry point. There is no dedicated feature test enforcing route-by-route discoverability coverage for the required surfaces list.
- TASK: Every mandatory surface route in scope (Dashboard, Jobs, Monitor, Messenger, Discovery, Backups, Feature Flags, Memory, Delegation, Org, Profile/Security/Account, API/token/integration flows) must render at least one discoverable docs entry point (`HelpHint`, helper/docs link), and this must be enforced by a feature test.
- ACTION:
  1. Add `tests/Feature/Documentation/HelpHintSurfaceCoverageTest.php` first with route/component coverage assertions and docs-entry-point checks.
  2. Run the test and confirm failures for missing surfaces.
  3. Add `HelpHint` to required page headers/sections in `resources/js/Pages/**` with stable `ui_key` values and learn-more links.
  4. Remove duplicated inline helper strings only where directly replaced by HelpHint.
  5. Re-run the coverage test and `npm run build`.
- RESULT: Task is complete when coverage assertions pass for all required surfaces and frontend build succeeds, with explicit evidence captured below.

Assumptions and Scope Boundaries:
- [x] Scope is integration coverage for required surfaces only; no new docs ingestion architecture changes.
- [x] Route coverage includes feature-flagged surfaces by enabling required flags in test setup.
- [x] Discoverability assertion accepts `HelpHint` or explicit docs link/helper marker in each mapped page.

Failure Modes to Defend:
- [x] Stale route names causing false coverage confidence.
- [x] Feature-flag-gated surfaces hidden unexpectedly during tests.
- [x] Missing tooltip fragment content causing HelpHint to disappear (mitigated via inline short text + ui_key binding).
- [x] Header helper text causing layout overflow on narrow/mobile viewports.

Implementation Checklist:
- [x] Add failing coverage test in `tests/Feature/Documentation/HelpHintSurfaceCoverageTest.php`.
- [x] Run `php artisan test --filter=HelpHintSurfaceCoverageTest` and confirm intended failures.
- [x] Integrate `HelpHint` into required Vue pages with stable `ui_key` and docs learn-more links.
- [x] Re-run `php artisan test --filter=HelpHintSurfaceCoverageTest` and confirm pass.
- [x] Run `npm run build` and confirm pass.
- [x] Add review evidence, correctness conditions, and explicit non-goals.

Review:
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.
- Evidence summary:
  - Red phase: `php artisan test --filter=HelpHintSurfaceCoverageTest` failed (`1 failed`) with missing discoverable docs entry point on `Dashboard.vue`.
  - Green phase: `php artisan test --filter=HelpHintSurfaceCoverageTest` passed (`1 passed, 156 assertions`).
  - Frontend verification: `npm run build` passed for client + SSR builds (non-blocking bundle-size warnings only).
- Conditions where this works:
  - Required route map in `HelpHintSurfaceCoverageTest` remains aligned with actual named routes and page components.
  - Feature-gated surfaces are enabled in test context (`delegation.ui_enabled=true`, `agent.org.enabled=true`).
  - Each covered page continues to include either `<HelpHint` or a docs-link marker and avoids removing those entry points during refactors.
  - Inline `short-text` + `ui-key` bindings are kept so discoverability does not depend on fragment API availability.
- Explicit non-goals and known limitations:
  - This task does not add full tooltip fragment YAML coverage for every new `ui_key`; hints currently rely on inline short text with docs links.
  - Coverage is route/page-level for required surfaces; it does not assert every nested subview/form field within each area.
  - No end-to-end mobile visual regression suite was added; layout safety is validated by build + component placement patterns.

### Session 15 Task 11 — Implement docs telemetry, auditing, and diagnostics endpoint (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs telemetry currently only logs tooltip misses directly via `DocsTelemetryService::recordTooltipMiss` and does not emit domain events.
- Search-unavailable paths return a stable API error contract but do not emit dedicated telemetry or counters.
- Sync command reports console output but does not record success/failure counters for operator diagnostics.
- No docs diagnostics endpoint exists for operators to inspect telemetry counters/recent failure signals.
- No dedicated `docs` logging channel exists yet in `config/logging.php`.

TASK
- Add structured docs observability for tooltip missing-key events, search-unavailable events, and sync outcomes with counters.
- Add event/listener/job wiring under documentation namespaces for non-blocking telemetry persistence.
- Add docs diagnostics endpoint with operator-only authorization restrictions.
- Add docs logging channel and docs audit writes aligned with current `AgentAuditLog` conventions.

ACTION
- [x] Add failing tests first:
  - `tests/Unit/Documentation/DocsTelemetryServiceTest.php`
  - `tests/Feature/Documentation/DocsDiagnosticsTest.php`
- [x] Verify red state via targeted PHPUnit filter runs.
- [x] Implement telemetry infrastructure:
  - extend `DocsTelemetryService` for missing-key/search-unavailable/sync-outcome instrumentation and counters,
  - add event classes under `app/Events/Documentation/`,
  - add listener(s) under `app/Listeners/Documentation/`,
  - add telemetry persistence job(s) under `app/Jobs/Documentation/`.
- [x] Add diagnostics endpoint controller at `app/Http/Controllers/Docs/DiagnosticsController.php` and register API route with authorization gate.
- [x] Add docs logging channel in `config/logging.php`.
- [x] Wire audit writes for telemetry events using existing audit conventions.
- [x] Run verification commands:
  - `php artisan test --filter=DocsTelemetryServiceTest`
  - `php artisan test --filter=DocsDiagnosticsTest`
  - `php artisan test --filter=Documentation`
- [x] Confirm structured log output for simulated missing-key and search-outage scenarios.

RESULT
- Completion is proven by fail-then-pass evidence for new tests, passing documentation test slice, diagnostics auth enforcement, and structured docs telemetry logs with corresponding counters/audit records.

Assumptions and scope boundaries
- Assumption: existing docs auth policy (`admin` / `analytics`) remains the operator role baseline for diagnostics.
- Assumption: telemetry persistence may rely on queue workers in runtime; request-path instrumentation must remain resilient if queue/persistence is degraded.
- Scope boundary: telemetry + diagnostics only; no changes to docs sync semantics beyond telemetry emission.

Failure modes to guard
- Malicious-caller mode: unauthorized access to diagnostics endpoint, high-volume repeated missing-key spam.
- Tired-maintainer mode: telemetry backend/storage failure causing request crashes, queue listener failures silently dropping observability.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state:
    - `php artisan test --filter=DocsTelemetryServiceTest` failed with missing event dispatches and undefined telemetry methods (`recordSearchUnavailable`, `recordSyncOutcome`).
    - `php artisan test --filter=DocsDiagnosticsTest` failed with missing route (`404`) and undefined telemetry methods.
  - Green state:
    - `php artisan test --filter=DocsTelemetryServiceTest` passed (`3 passed, 7 assertions`).
    - `php artisan test --filter=DocsDiagnosticsTest` passed (`3 passed, 11 assertions`).
    - `php artisan test --filter=Documentation` passed (`58 passed, 370 assertions`).
  - Structured log verification:
    - `storage/logs/docs-2026-03-02.log` contains structured `documentation.telemetry` warning entries for:
      - `documentation.tooltip.miss` (missing key),
      - `documentation.search.unavailable` (search outage),
      - `documentation.sync.outcome` (success/failure outcomes).
- Conditions where this works:
  - API diagnostics endpoint requires authenticated user plus `admin` or `analytics` role via `view-docs-diagnostics` gate.
  - Telemetry counters/recent failures persist through `AgentSystemState` and diagnostics snapshot reads from that store.
  - Event listeners dispatch persistence jobs on `agent` queue; request-path counters still record even if queued persistence is degraded.
  - Docs logging channel (`docs`) is enabled and writable in target environment.
- Explicit non-goals / limitations:
  - No changes were made to docs sync semantics beyond telemetry emission.
  - No additional retry/backoff orchestration was added for queue execution beyond existing queue behavior.
  - Existing framework-level duplicate event listener registration in this app is mitigated for docs telemetry via listener-level dedupe rather than global event-system reconfiguration.

### 2026-03-02 — Docs UI Runtime Source Fix

- [x] Diagnose why `/docs` only showed two static cards after docs sync.
- [x] Switch docs web pages to prefer runtime DB entries (`documentation_entries`) with fallback to static catalog only when DB is empty.
- [x] Render `entry.body_html` in docs show page for actual document content visibility.
- [x] Sync docs to runtime DB and verify counts.
- [x] Verify docs navigation/auth tests after controller changes.

Review:
- Root cause: `DocsPageController` was hardwired to `DocsCatalog` (2 static entries), so UI never reflected synced runtime docs records.
- Verification:
  - `php artisan docs:sync --mode=commit --source=repo` => `Entries: 12 | Fragments: 14 | Links: 61`
  - Runtime counts: `entries=12, fragments=14, links=61`
  - `php artisan test tests/Feature/Documentation/DocsNavigationTest.php tests/Feature/Documentation/DocsAuthorizationTest.php` => `16 passed`.
- Note: `php artisan docs:openapi:ingest` still fails until OpenAPI operations include `x-linked-doc-slugs` metadata for each operation.

### 2026-03-02 — Docs UX and Coverage Remediation (User Correction)

Pre-Execution Goal Articulation (STAR):
- Situation: Docs runtime contract existed, but user-facing docs experience remained poor (minimal content, no practical search/sidebar workflow, broken Learn More behavior in helper tooltips).
- Task: Deliver a usable internal docs center with rich product/API docs coverage, sidebar navigation + right-hand markdown reader, working search/filter flow, and reliable Learn More navigation.
- Action:
  1. Upgrade docs pages (`Docs/Index`, `Docs/Show`) to sidebar/search/filter + full markdown reading pane.
  2. Fix `HelpHint` Learn More interaction and focus/blur behavior.
  3. Add runtime docs bootstrap to auto-sync docs when DB docs/fragments are missing.
  4. Expand product/API docs content and add comprehensive API route inventory/reference docs.
  5. Re-run docs validation, sync, coverage, docs feature/unit tests, component tests, and build.
- Result: Docs center now renders rich markdown content from runtime docs, supports search/filter workflows, covers major product surfaces + API inventory, and tooltip Learn More links resolve reliably.

Implementation Checklist:
- [x] Add runtime bootstrap service for missing docs datasets.
- [x] Integrate bootstrap into docs page controller and tooltip registry resolution.
- [x] Replace docs index/show UI with sidebar + right-pane markdown view + search/filter controls.
- [x] Harden `HelpHint` interaction for Learn More navigation.
- [x] Add missing jobs/product docs and expanded app/API references.
- [x] Validate and sync docs (`docs:validate`, `docs:sync`, `docs:coverage`).
- [x] Re-run docs feature/unit tests + HelpHint component test + production build.

Review:
- Evidence summary:
  - `php artisan docs:validate` => passed (`Validated markdown files: 17, tooltip files: 2, tooltip fragments: 14`).
  - `php artisan docs:sync --mode=commit --source=repo` => passed (`Entries: 17 | Fragments: 14 | Links: 109`).
  - `php artisan docs:coverage --fail-on-missing` => passed (`Coverage: 100.00% (12/12 surfaces)`).
  - `php artisan test tests/Feature/Documentation tests/Unit/Documentation` => passed (`59 passed, 398 assertions`).
  - `npm run test:unit -- resources/js/Components/__tests__/HelpHint.spec.ts` => passed (`5 passed`).
  - `npm run build` => passed (client + SSR).
- Conditions where this works:
  - Docs markdown and tooltip YAML remain contract-compliant and synced to runtime (`docs:sync`).
  - Runtime docs tables are writable so first-load bootstrap can hydrate missing datasets.
  - Authenticated users can access `/docs` and `/docs/{slug}` with Inertia rendering.
- Explicit non-goals / limitations:
  - This pass does not add WYSIWYG authoring workflows.
  - Search in docs UI currently uses docs page query/filter flow rather than a dedicated live-search component.
  - API route inventory is generated from current route registration snapshot and should be regenerated when API surface changes.

### 2026-03-02 — Session 16 Task 3: Implement Atomic Session Transition Service

Pre-Execution Goal Articulation (STAR)

SITUATION
- `RepoAnalysis` models and schema exist, but no lifecycle transition service exists under `app/Support/RepoAnalysis/`.
- `repo_analysis_sessions` stores lifecycle state as `phase` (int) + `status` (string), and writes can race without guarded conditional updates.
- Constraints: scope is transition service + focused unit tests only; no controller/UI wiring.

TASK
- Add a single source of truth transition service that enforces deterministic allowed phase/status transitions with atomic conditional updates.
- Add unit tests proving allowed transitions, disallowed transitions, terminal-state protection, and race-safe atomic behavior.

ACTION
- [x] Create `tests/Unit/Support/RepoAnalysis/SessionStateTransitionServiceTest.php` with matrix coverage and edge/failure paths.
- [x] Run `php artisan test --filter=SessionStateTransitionServiceTest` and capture failing red-state output.
- [x] Implement `app/Support/RepoAnalysis/SessionStateTransitionService.php` with allowed-from matrix + atomic `UPDATE ... WHERE` transition semantics.
- [x] Re-run `php artisan test --filter=SessionStateTransitionServiceTest` and confirm all tests pass.
- [x] Update this plan entry with verification evidence, correctness conditions, and non-goals.

RESULT
- Completion is verified by fail-then-pass evidence from the targeted test filter and by assertions that duplicate start, invalid phase jumps, invalid resume/retry, terminal-state mutation, and competing writes are rejected.

Assumptions and scope boundaries
- Assumption: code analysis lifecycle uses phases `0..6` and status values `setup|snapshotting|planning|executing|validating|reporting|completed|paused|failed`.
- Assumption: terminal protection is enforced for `phase=6,status=completed`.
- Scope boundary: this task only introduces the transition service + unit tests.

Failure modes to guard
- Malicious-caller mode: invalid lifecycle jumps (e.g., setup -> execute), unauthorized resume/retry semantics via direct API calls.
- Tired-maintainer mode: duplicate start/retry calls or concurrent workers causing non-deterministic session state.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state:
    - `php artisan test --filter=SessionStateTransitionServiceTest` failed with `Class \"App\\Support\\RepoAnalysis\\SessionStateTransitionService\" not found`.
  - Green state:
    - `php artisan test --filter=SessionStateTransitionServiceTest` passed (`9 passed, 34 assertions`).
- Conditions where this works:
  - Lifecycle transitions are guarded by a deterministic allowed-from matrix keyed by `phase:status`.
  - Atomic transition safety depends on conditional `UPDATE` semantics (`WHERE id AND allowed-from-state`) and returns success only when exactly one row changes.
  - `resume()` only succeeds from `paused` status and maps back to the active status for the same phase.
  - `retry()` only succeeds from `failed` status and maps back to the active status for the same phase.
  - Terminal state (`6:completed`) cannot be mutated because no matrix path allows transitions out of it.
- Explicit non-goals / limitations:
  - No controller, API endpoint, policy, event, or UI wiring was added in this task.
  - No cross-phase pause/retry semantics were introduced beyond same-phase resume/retry restoration.
  - No additional lifecycle statuses were introduced beyond the matrix covered by this service.

### 2026-03-02 — Session 16 Task 5: Build Deterministic Snapshot Builder

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis session/task/event foundations exist, but `app/Support/RepoAnalysis/SnapshotBuilder.php` does not exist yet.
- Deterministic requirements are strict: canonical path traversal, deterministic manifest JSON, and snapshot hash stability when file contents are unchanged but mtimes differ.
- Constraints: scope is limited to `SnapshotBuilder` and fixture-based tests; no queue/controller/UI wiring in this task.

TASK
- Implement a deterministic snapshot builder that produces a canonical manifest and snapshot hash from repository files.
- Snapshot hash must remain identical across runs when file contents are unchanged, even if mtimes change.
- Manifest must retain mtime metadata while excluding mtime from hash input.

ACTION
- [x] Add `tests/Unit/Support/RepoAnalysis/SnapshotBuilderTest.php` using fixture repo trees for canonical ordering, filtering, deterministic serialization, and hash behavior.
- [x] Cover edge/failure paths in tests: symlink handling, unreadable files, oversize file skip metadata, and path escaping attempts.
- [x] Run `php artisan test --filter=SnapshotBuilderTest` and capture initial failing red state.
- [x] Implement `app/Support/RepoAnalysis/SnapshotBuilder.php` with deterministic traversal, excludes, hash pipeline, and size/path guards.
- [x] Re-run `php artisan test --filter=SnapshotBuilderTest` twice and confirm matching snapshot hashes across both runs.

RESULT
- Completion is proven by fail-then-pass test evidence and by deterministic assertions on:
  - sorted canonical paths,
  - include/exclude behavior,
  - `mtime` persisted in manifest but excluded from hash,
  - stable canonical JSON serialization and stable snapshot hash across repeated test execution.

Assumptions and scope boundaries
- Assumption: default excludes include `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, and `.git/` from `config/repo_analysis.php`.
- Assumption: symlink targets are not followed to avoid non-deterministic and unsafe traversal.
- Scope boundary: this task does not add queue jobs, API endpoints, DB schema changes, or UI updates.

Failure modes to guard
- Malicious-caller mode: include/exclude/path inputs attempting traversal outside root (`../` path escape patterns).
- Tired-maintainer mode: flaky hash outputs caused by path ordering drift or mtime-only file changes.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state:
    - `php artisan test --filter=SnapshotBuilderTest` failed with `Class "App\Support\RepoAnalysis\SnapshotBuilder" not found` (`4 failed`).
  - Green state:
    - `php artisan test --filter=SnapshotBuilderTest` passed (`4 passed, 19 assertions`).
    - `php artisan test --filter=SnapshotBuilderTest` (second run) passed (`4 passed, 19 assertions`).
  - Determinism evidence:
    - `php artisan tinker --execute='$builder = new App\\Support\\RepoAnalysis\\SnapshotBuilder; ...'` emitted identical hashes twice:
      - `154c89a1c7ba6fa5b14398382489fb111d8fb6e394574e805505703a2d36404f`
      - `154c89a1c7ba6fa5b14398382489fb111d8fb6e394574e805505703a2d36404f`
- Conditions where this works:
  - Snapshot inputs are files under a real, local project directory and traversal can read metadata/content for readable files.
  - Canonical ordering is lexicographic on normalized relative paths; deterministic JSON key ordering is applied before encoding.
  - `mtime` is stored in manifest file entries, while snapshot hash input strips `mtime` from included file records.
  - Default excludes from `repo_analysis.scan.exclude_paths` are always merged with task-specific excludes.
  - Path guard behavior marks invalid include/exclude rules containing `..` as `path_escape_attempts` and rejects symlink traversal.
- Explicit non-goals / limitations:
  - This task does not yet persist snapshot outputs to code-analysis tables or wire jobs/controllers/UI.
  - Unreadable-file behavior relies on filesystem permission semantics of the runtime OS.
  - SnapshotBuilder currently returns arrays (manifest + JSON + hash) and does not yet expose a dedicated value object/DTO.

### 2026-03-02 — Session 16 Task 6: Implement Task Graph Builder, Analyzer Registry, and Core Analyzer Contracts

Pre-Execution Goal Articulation (STAR)

SITUATION
- `RepoAnalysis` currently has transition/event/snapshot services but no deterministic task graph planner and no analyzer contract/registry layer.
- There are no analyzer implementations under `app/Support/RepoAnalysis/Analyzers/*`, so profile-based task planning and versioned analyzer execution contracts are not yet available.
- Constraints: scope is limited to `TaskGraphBuilder`, analyzer interface/registry, and initial analyzer set. Queue jobs, controllers, and UI are out of scope for this task.

TASK
- Add deterministic DAG planning and analyzer contracts so the same profile + snapshot yields the same ordered task plan and stable `task_key` values.
- Add initial analyzer implementations with normalized output hashing and explicit handling for missing manifests/lockfiles, parser errors, empty test suites, and mixed-stack repositories.
- Prove correctness with unit tests covering deterministic ordering, dependency correctness, stable task keys, normalized output hashes, and unsupported profile skip behavior.

ACTION
- [x] Add unit tests for `TaskGraphBuilder` deterministic ordering/task keys/dependencies/profile-skip behavior.
- [x] Add analyzer contract tests for normalized output hashing and required edge/failure paths.
- [x] Run `php artisan test --filter=TaskGraphBuilderTest` as the first verification step (current branch state was already green).
- [x] Implement `AnalyzerInterface`, `AnalyzerRegistry`, and initial analyzers:
  - `FilesystemManifestAnalyzer`
  - `DependencyManifestAnalyzer`
  - `LaravelRoutesAnalyzer`
  - `LaravelModelsMigrationsAnalyzer`
  - `QueueJobsEventsAnalyzer`
  - `FrontendModuleGraphAnalyzer`
  - `TestCoverageMapAnalyzer`
  - `RiskHotspotAnalyzer`
- [x] Implement `TaskGraphBuilder` with deterministic task ordering, stable keys, and dependency validation.
- [x] Re-run focused tests for graph + analyzers and verify deterministic order/hash behavior across repeated runs.

RESULT
- Completion is verified by fail-then-pass evidence from targeted tests and repeated deterministic assertions showing identical task order and output hashes for identical inputs.

Assumptions and scope boundaries
- Assumption: analyzer ordering is deterministic by configured profile order, with deterministic tie-breaking by analyzer key.
- Assumption: `task_key` is deterministic for identical `(profile, snapshot_hash, analyzer_key, analyzer_version)` input.
- Scope boundary: no queue orchestration changes, no API route/controller changes, and no UI changes in this task.

Failure modes to guard
- Malicious-caller mode: unknown analyzer profile or malformed snapshot input attempting to break deterministic graph generation.
- Tired-maintainer mode: parser/manifest absence and unstable array ordering causing flaky output hashes between runs.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - `php artisan test --filter=TaskGraphBuilderTest` passed (`4 passed, 15 assertions`).
  - `php artisan test --filter=AnalyzerContractsTest` passed (`4 passed, 8 assertions`).
  - `php artisan test --filter='TaskGraphBuilderTest|AnalyzerContractsTest'` passed (`8 passed, 23 assertions`) on repeat run, confirming deterministic assertions stay stable across reruns.
  - `php artisan test --filter='RepoAnalysis|TaskGraphBuilderTest|AnalyzerContractsTest'` passed (`38 passed, 176 assertions`), confirming no regression across current RepoAnalysis unit/feature coverage.
- Conditions where this works:
  - For identical `(profile, snapshot_hash, analyzer set + versions)`, `TaskGraphBuilder` produces stable sequence order and `task_key` values.
  - Dependency edges are enforced by analyzer key contracts; tasks with missing dependencies are explicitly skipped with reason metadata.
  - `AbstractAnalyzer` hash normalization sorts list/object structures before hashing, so equivalent payloads with different input order produce identical `output_hash`.
  - `AnalyzerRegistry::forProfile()` deterministically applies profile order, deduplicates keys, and emits explicit skips for unsupported profiles/stacks.
  - Edge handling is covered for missing manifests/lockfiles context, JSON parser errors, empty test suite warning artifact path, and mixed Laravel+frontend stack selection.
- Explicit non-goals / limitations:
  - This task does not add queue job wiring, API controllers/routes, persistence of analyzer outputs, or UI orchestration.
  - Analyzer implementations are intentionally lightweight deterministic detectors; they do not execute heavyweight parsers or static-analysis engines yet.
  - Missing-profile behavior returns skipped metadata and no tasks, but does not auto-fallback to another profile.

### Session 16 Task 7 — Implement Queue Jobs for Snapshot->Plan->Execute with Retry/Drift Semantics (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis foundations exist (schema/models, transition service, event writer, snapshot builder, task graph/analyzers), but pipeline jobs for deterministic execution phases are not yet implemented.
- There is no integration coverage for queue-driven phase progression, retry-once semantics, pause behavior, resume reuse gating, or snapshot drift pause controls.
- Constraints: implement only queue jobs in `app/Jobs/RepoAnalysis/*` and orchestration service wiring; no UI/report export screen work in this task.

TASK
- Implement deterministic queue jobs for `Snapshot (1) -> Plan (2) -> Execute (3) -> Validate (4) -> Report (5) -> Complete (6)` with policy-driven retry behavior.
- Ensure retryable execute failures auto-retry once then pause; non-retryable execute failures pause immediately without auto-retry.
- Ensure completed-task reuse on resume only happens when both `input_hash` and `analyzer_version` match current plan inputs.
- Ensure snapshot drift detection pauses session and requires explicit operator decision before continuation.

ACTION
- [x] Add tests first in `tests/Integration/RepoAnalysis/RepoAnalysisExecutionPipelineTest.php` covering:
  - [x] phase progression through snapshot/plan/execute/validate/report completion,
  - [x] retryable failure auto-retry once then pause on second failure,
  - [x] non-retryable failure short-circuit without retry,
  - [x] resume reuse only when `input_hash` + `analyzer_version` match,
  - [x] drift detection pause with operator decision required,
  - [x] replay/idempotency, stale task state handling, queue misrouting guard.
- [x] Run `php artisan test --filter=RepoAnalysisExecutionPipelineTest` and confirm failing red state.
- [x] Implement orchestration service wiring and jobs:
  - [x] `GenerateRepoSnapshotJob`
  - [x] `PlanRepoAnalysisTasksJob`
  - [x] `ExecuteRepoAnalysisTaskJob`
  - [x] `ValidateRepoAnalysisCoverageJob`
  - [x] `GenerateRepoAnalysisReportJob`
- [x] Re-run `php artisan test --filter=RepoAnalysisExecutionPipelineTest` and confirm green.
- [x] Record review with assumptions, correctness conditions, non-goals, and limitations.

RESULT
- Completion is verified by fail-then-pass evidence from the integration test file plus persisted state checks for paused/resumed/failed/completed outcomes and emitted diagnostics.

Assumptions and scope boundaries
- Assumption: code-analysis jobs must always route to `code-analysis` queue on Redis; queue retries remain disabled (`tries=1`) and retry policy is implemented in execute-task logic.
- Assumption: deterministic analyzers and snapshot/task graph builders are already available and remain unchanged unless minimal wiring requires it.
- Scope boundary: no API controller/UI/export UX implementation in this task.

Failure modes to guard
- Malicious-caller mode: dispatching jobs on wrong queue or forcing invalid/stale task state to bypass deterministic phase controls.
- Tired-maintainer mode: replayed jobs duplicating artifacts/tasks/reports, stale running task never recovering, or retry policy accidentally delegated to queue-level retries.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state (`php artisan test --filter=RepoAnalysisExecutionPipelineTest`) failed with `Class "App\Jobs\RepoAnalysis\GenerateRepoSnapshotJob" not found` and `6 failed`.
  - Implemented:
    - `app/Support/RepoAnalysis/RepoAnalysisExecutionOrchestrator.php`
    - `app/Support/RepoAnalysis/Exceptions/QueueMisroutingException.php`
    - `app/Jobs/RepoAnalysis/GenerateRepoSnapshotJob.php`
    - `app/Jobs/RepoAnalysis/PlanRepoAnalysisTasksJob.php`
    - `app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php`
    - `app/Jobs/RepoAnalysis/ValidateRepoAnalysisCoverageJob.php`
    - `app/Jobs/RepoAnalysis/GenerateRepoAnalysisReportJob.php`
    - `tests/Integration/RepoAnalysis/RepoAnalysisExecutionPipelineTest.php`
  - Green state (`php artisan test --filter=RepoAnalysisExecutionPipelineTest`) passed:
    - `6 passed (35 assertions)`.
- Conditions where this works:
  - Jobs are routed on the configured `code-analysis` queue; misrouted execution throws `QueueMisroutingException`.
  - Snapshot payload is present in session metadata and `snapshot_hash` exists before planning/execution.
  - Retry policy is enforced in task logic (`tries=1` at queue layer): retryable failures auto-retry once, second retryable failure pauses; non-retryable failures pause immediately.
  - Resume reuse preserves completed task outputs only when both `input_hash` and `analyzer_version` match current deterministic plan input.
  - Drift detection compares current snapshot hash vs stored snapshot hash at execute-phase boundary; mismatch pauses session and sets `operator_action_required=drift_decision_required`.
  - Replay-safe updates use `updateOrCreate` for snapshot artifact and report rows to avoid duplication.
- Explicit non-goals / limitations:
  - This task does not add API lifecycle endpoints/UI operator controls for pause/resume/restart decisions.
  - Drift decision handling currently only supports `continue_old_snapshot` as an in-metadata decision path; restart orchestration is not implemented in this task.
  - Coverage validation is intentionally minimal and deterministic; it does not yet enforce full stack-specific artifact-class requirements.
### Session 16 Task 8 — Implement Coverage Gates, Deterministic Report Composition, Export, and Retention Cleanup (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis pipeline jobs exist and currently inline simplified coverage and report logic inside `ValidateRepoAnalysisCoverageJob` and `GenerateRepoAnalysisReportJob`.
- Dedicated services for coverage gates, deterministic report composition, and versioned exports are not yet implemented for Code Analysis.
- Retention cleanup for task-level artifacts is not implemented or scheduled.
- Constraints from this task: implement only `CoverageGateService`, `ReportComposer`, `ExportService`, retention cleanup command/job + scheduler registration, and add the three specified test files.

TASK
- Completion must be blocked when coverage gates fail (missing required artifact classes or critical task failures), while allowing completion with warning when test mapping is empty.
- Report composition must be deterministic with report hash derived from ordered artifact hashes (immutable deterministic inputs), without requiring narrative.
- Report export must write versioned files under `docs/discovery/code-analysis/{slug}.md|json`, suffixing on collisions (`-v2`, `-v3`, ...), and enforce export path policy.
- Retention cleanup must delete task-level artifacts older than 30 days while preserving report records and exported files.

ACTION
- [x] Add tests first:
  - [x] `tests/Unit/Support/RepoAnalysis/CoverageGateServiceTest.php`
  - [x] `tests/Unit/Support/RepoAnalysis/ReportComposerTest.php`
  - [x] `tests/Feature/RepoAnalysis/RepoAnalysisExportAndRetentionTest.php`
- [x] Run targeted tests to capture red state.
- [x] Implement minimal services and wiring:
  - [x] `app/Support/RepoAnalysis/CoverageGateService.php`
  - [x] `app/Support/RepoAnalysis/ReportComposer.php`
  - [x] `app/Support/RepoAnalysis/ExportService.php`
  - [x] retention cleanup command/job (artifact deletion only) + schedule registration.
  - [x] config updates for required artifact classes and export path policy.
- [x] Update report/coverage jobs to use the new services and enforce gate blocking semantics.
- [x] Re-run targeted tests and ensure pass.
- [x] Verify export paths match `docs/discovery/code-analysis/{slug}.md|json` pattern.

RESULT
- Evidence of correctness is fail-then-pass test output for the three new test classes and assertions proving:
  - gate failure blocks completion,
  - no-tests warning passes,
  - deterministic report hash independent of artifact insertion order,
  - export collision suffixing works,
  - retention cleanup deletes only old task artifacts and preserves reports/exports.

Assumptions and scope boundaries
- Assumption: deterministic artifact hashes/content are immutable inputs to report hashing; generated timestamps are excluded from hash inputs.
- Assumption: required artifact class policy is config-driven and defaults can be expressed in `config/repo_analysis.php`.
- Scope boundary: no API controller/UI additions in this task.

Failure modes to guard
- Malicious-caller mode: bypassing export directory policy via crafted slug/path traversal, or forcing coverage pass despite critical failed task.
- Tired-maintainer mode: non-deterministic report hash due to unordered artifact traversal, accidental deletion of report artifacts during retention cleanup, and unhandled export file collisions.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state (`php artisan test tests/Unit/Support/RepoAnalysis/CoverageGateServiceTest.php tests/Unit/Support/RepoAnalysis/ReportComposerTest.php tests/Feature/RepoAnalysis/RepoAnalysisExportAndRetentionTest.php`) failed with:
    - `Target class [App\Support\RepoAnalysis\CoverageGateService] does not exist.`
    - `Target class [App\Support\RepoAnalysis\ReportComposer] does not exist.`
    - `Target class [App\Support\RepoAnalysis\ExportService] does not exist.`
    - `The command "code-analysis:prune-artifacts" does not exist.`
  - Green state:
    - `php artisan test tests/Unit/Support/RepoAnalysis/CoverageGateServiceTest.php tests/Unit/Support/RepoAnalysis/ReportComposerTest.php tests/Feature/RepoAnalysis/RepoAnalysisExportAndRetentionTest.php` passed (`8 passed, 26 assertions`).
    - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` passed (`6 passed, 35 assertions`) after job wiring changes.
  - Export path pattern verification:
    - `RepoAnalysisExportAndRetentionTest` asserts generated markdown/json paths match `/docs/discovery/code-analysis/{slug}.md|json` and collision suffixes `-v2`, `-v3`.
- Conditions where this works:
  - Coverage gate passes only when snapshot hash exists, required artifact classes are present, and no critical failed tasks exist.
  - No-tests repositories still pass coverage when `test_coverage_map` emits `empty_test_suite` warning.
  - Report hash determinism holds for identical ordered `(artifact_key, content_hash)` sets regardless of insertion order.
  - Export collision handling reserves paired markdown/json paths with deterministic suffixing and blocks path traversal via policy validation.
  - Retention cleanup prunes only task-linked artifacts older than configured TTL (`repo_analysis.retention.task_artifacts_ttl_days`) and leaves reports/exports intact.
- Explicit non-goals / limitations:
  - Retention cleanup currently prunes by artifact `created_at` and does not archive before delete.
  - Export service enforces relative export directory policy but does not currently integrate `PathPolicy` helper methods.
  - Partial cleanup failures are counted and tolerated; failed IDs are not yet persisted in a dedicated audit artifact.

### Session 16 Task 9 — Implement Code Analysis API, Requests, Authorization, Limits, and Audit Logging (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis core data model, deterministic services, queue jobs, and coverage/report services exist in the working tree.
- `/agent/api/v1/code-analysis/*` endpoints, code-analysis request classes, code-analysis policy mapping, and lifecycle mutation audit logs are not yet wired.
- App policy registration is handled in `AppServiceProvider` (not `AuthServiceProvider`) and admin checks use `User::hasRole('admin')`.
- Constraints: owner-only by default with admin override, dedicated Code Analysis active-session cap fixed at `2`, test-first workflow, and non-destructive DB operations only.

TASK
- Code Analysis API lifecycle/read surface is fully exposed and validated.
- Owner/admin authorization, active-session cap, invalid transition handling, invalid task-id handling, and event pagination parity (`since_sequence`) are enforced.
- Lifecycle mutations are audit-logged.
- `RepoAnalysisApiLifecycleTest` covers red/green behavior and passes with route registration verification.

ACTION
- [x] Add feature tests first:
  - [x] `tests/Feature/Api/V1/RepoAnalysis/RepoAnalysisApiLifecycleTest.php`
  - [x] cover CRUD/lifecycle/read endpoints, validation failures, invalid transitions, owner/admin authz, cap enforcement, retry/restart edge cases, paused-only resume, events `since_sequence`, and lifecycle audit log writes.
- [x] Run `php artisan test --filter=RepoAnalysisApiLifecycleTest` and capture failing output.
- [x] Implement minimal backend changes in scope:
  - [x] Register all `/agent/api/v1/code-analysis/*` routes in `routes/api.php`.
  - [x] Create `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php` with scoped lifecycle/read handlers.
  - [x] Add request classes in `app/Http/Requests/Agent/RepoAnalysis/*`.
  - [x] Add `app/Policies/RepoAnalysisSessionPolicy.php` and map it in `AppServiceProvider`.
  - [x] Enforce active-session cap (`2`) at create/start path.
  - [x] Add lifecycle mutation audit log writes via `AuditLogger`.
- [x] Re-run `php artisan test --filter=RepoAnalysisApiLifecycleTest` and confirm pass.
- [x] Run `php artisan route:list --path=agent/api/v1/code-analysis` and confirm endpoint registration.
- [x] Add review notes with assumptions, conditions for correctness, handled/not-handled paths, and limitations.

RESULT
- Completion evidence is:
  - red-to-green transition for `RepoAnalysisApiLifecycleTest`,
  - route-list output confirming code-analysis API endpoints,
  - audit-log assertions for lifecycle mutation actions.

Failure modes to guard
- Malicious-caller mode: unauthorized user calling lifecycle mutations on another owner’s session; forged task IDs in retry endpoint; repeated create/start calls to bypass active-session cap.
- Tired-maintainer mode: route/controller mismatch, missing policy registration, inconsistent invalid-transition response codes, and unaudited lifecycle mutations.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=RepoAnalysisApiLifecycleTest` failed with endpoint 404s before API wiring (`Expected response status code [422] but received 404` and related route misses).
  - Green state: `php artisan test --filter=RepoAnalysisApiLifecycleTest` passed (`7 passed, 63 assertions`).
  - Route registration: `php artisan route:list --path=agent/api/v1/code-analysis` shows 20 code-analysis endpoints including lifecycle (`start-snapshot`, `retry-task`, `restart-from-beginning`) and read endpoints (`events`, `tasks`, `artifacts`, `reports`).
  - Implemented files:
    - `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
    - `app/Http/Requests/Agent/RepoAnalysis/StoreRepoAnalysisSessionRequest.php`
    - `app/Http/Requests/Agent/RepoAnalysis/UpdateRepoAnalysisSessionRequest.php`
    - `app/Http/Requests/Agent/RepoAnalysis/RetryRepoAnalysisTaskRequest.php`
    - `app/Http/Requests/Agent/RepoAnalysis/RepoAnalysisEventsRequest.php`
    - `app/Policies/RepoAnalysisSessionPolicy.php`
    - route/policy wiring in `routes/api.php` and `app/Providers/AppServiceProvider.php`
    - test coverage in `tests/Feature/Api/V1/RepoAnalysis/RepoAnalysisApiLifecycleTest.php`
- Conditions where this works:
  - Session ownership is enforced by policy with admin override via `User::hasRole('admin')`.
  - Create/start mutations enforce dedicated code-analysis active-session cap from `repo_analysis.user.max_active_sessions_per_user` (default `2`).
  - Lifecycle mutation endpoints return deterministic transition conflicts (`RUN_TRANSITION_CONFLICT`) for invalid states.
  - Retry-task endpoint rejects missing/foreign `task_id` and only requeues failed tasks.
  - Events endpoint preserves `since_sequence` parity via ordered sequence reads and latest-sequence metadata.
  - Lifecycle mutation endpoints write immutable `agent_audit_logs` entries via `AuditLogger`.
- Explicit non-goals / limitations:
  - This task does not implement websocket broadcasting route contracts for code-analysis events; it delivers polling read parity only.
  - Restart currently hard-resets session tasks/events/artifacts/reports before requeueing snapshot; no partial restart strategy is implemented.
  - Mutation endpoints are exposed regardless of `repo_analysis.enabled` flag; flag-based route hiding was not added in this task.

## 2026-03-02 Code Analysis Report Clarity Follow-up
- [x] Investigate why code-analysis output is unclear and verify websocket/polling behavior.
- [x] Upgrade report composition/export to produce a detailed human-readable repository report.
- [x] Clarify Code Analysis UI semantics for Task Graph, Coverage Gate, and Artifacts.
- [x] Add/adjust automated tests for new report and UI behavior.
- [x] Run focused code-analysis tests and verify pass.
- [x] Commit and push.

### Review
- Upgraded ReportComposer + ExportService so exported markdown now includes repository profile sections (overview, dependencies, structure, backend, frontend, testing, risk hotspots, coverage gate, glossary, limitations, deterministic parsing warnings).
- Updated Code Analysis wizard panels: Task Graph now shows DAG dependency context, Coverage Gate now binds to real `coverage_validated` events and displays pass/blocker summary, Artifacts now include type descriptions and payload summaries.
- Added regression checks in `ReportComposerTest` and export markdown assertions in `RepoAnalysisExportAndRetentionTest`.
- Verification: `php artisan test --filter=RepoAnalysis --stop-on-failure` (64 tests, all passing).

## 2026-03-02 Code Analysis UX Follow-up
- [x] Add full report rendering in Code Analysis UI (not metadata-only cards).
- [x] Auto-start analysis for newly created sessions without requiring manual "Run Next Step".
- [x] Verify with focused tests and push.

### Review
- ReportViewer now shows full report content directly in-app (overview, dependency summary, backend/frontend surfaces, testing, risk hotspots, coverage gate, glossary) plus raw payload JSON.
- Create flow now routes to wizard with one-time `autostart=1`; wizard consumes that flag and immediately starts snapshot when session is in setup phase.
- Verification: `php artisan test --filter=RepoAnalysis --stop-on-failure` and `npm run build` both pass.

## 2026-03-02 Code Analysis AI-Driven Report Completion
- [x] Wire AI artifacts into report payload so final report is primarily AI-authored markdown sections.
- [x] Update markdown export to include full AI report (with deterministic appendix) instead of only compact profile bullets.
- [x] Update ReportViewer to render full markdown report in UI and expose deterministic appendix cleanly.
- [x] Ensure wizard auto-starts analysis when opening a fresh setup session (no manual "Run Next Step" needed).
- [x] Surface AI enablement as a managed app feature flag in Feature Settings UI config.
- [x] Add/adjust tests for report composition, API/session runner persistence, and feature-flag exposure.
- [x] Run focused code-analysis tests + frontend build verification.
- [ ] Commit and push.

### Review
- Code Analysis report composition now unwraps analyzer payload artifacts, includes AI section outputs, and stores `full_report_markdown` for direct UI/export rendering.
- Markdown export now prints a full narrative report first (AI final synthesis when present), then deterministic appendix + artifact ledger.
- Wizard auto-start now triggers for fresh setup sessions via metadata/query/inferred setup state, avoiding manual first-step clicks.
- Feature Settings now includes a managed toggle for `repo_analysis.ai.enabled` and planning uses this flag to enable/disable AI task graph nodes.
- Verification executed:
  - `php artisan test --filter=RepoAnalysis --stop-on-failure`
  - `php artisan test --filter=FeatureSettingsApiTest --stop-on-failure`
  - `php artisan test --filter=FeatureFlagManagerTest --stop-on-failure`
  - `npm run build`

### Session 16 Task 11 — Make Deterministic Code Analysis Fully Stack-Agnostic (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Deterministic analyzer keys were partially generic, but analyzer class naming still reflected Laravel-specific intent.
- Dependency detection was root-file-biased and could miss monorepo/nested manifests in non-PHP repositories.
- Repository profile dependency reporting skewed toward PHP/Node parsing and left non-PHP/Node repos with weak deterministic context.

TASK
- Ensure deterministic analysis is framework-agnostic and usable across arbitrary repository types.
- Remove Laravel-specific analyzer identity from deterministic analyzer implementation names.
- Expand deterministic dependency/ecosystem detection and report synthesis to represent diverse stacks.

ACTION
- [x] Rename analyzer classes/files to generic names and update registry wiring:
  - [x] `RoutingSurfaceAnalyzer`
  - [x] `DataModelSurfaceAnalyzer`
  - [x] `AsyncWorkflowsSurfaceAnalyzer`
  - [x] `FrontendSurfaceAnalyzer`
- [x] Expand dependency manifest analyzer to support nested/monorepo manifest discovery and broader ecosystem detection.
- [x] Genericize repository profile dependency/stack synthesis to be ecosystem-first with optional framework hints.
- [x] Expand language breakdown mapping for non-PHP ecosystems.
- [x] Update/extend unit tests for cross-stack ecosystem detection.

RESULT
- Deterministic analysis now identifies manifests/lockfiles/effective ecosystems across mixed and nested stacks and emits generic artifact keys/outputs.
- Analyzer naming and deterministic registry composition are now framework-neutral.
- Repository profile output now carries ecosystem/manifests/lockfiles metadata even when composer/package parsing is absent.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Implemented generic analyzer class rename and registry wiring updates in `app/Support/RepoAnalysis/Analyzers/*`.
  - Extended dependency ecosystem detection and nested manifest support in `app/Support/RepoAnalysis/Analyzers/DependencyManifestAnalyzer.php`.
  - Updated repository profile stack/dependency synthesis and language mapping in `app/Support/RepoAnalysis/ReportComposer.php`.
  - Added/updated analyzer contract test for nested mixed-stack ecosystem detection in `tests/Unit/Support/RepoAnalysis/Analyzers/AnalyzerContractsTest.php`.
- Conditions where this works:
  - Snapshot manifest contains repository file paths (root and nested).
  - Dependency manifests and/or lockfiles are present in recognized formats.
  - Report generation runs after deterministic artifacts are persisted.
- Explicit non-goals / limitations:
  - Deterministic phase still relies on heuristics and does not execute project builds/tests/toolchains.
  - Framework-specific hints are additive only and do not constrain analysis to any single stack.

### Session 16 Task 12 — Remove Code Analysis Wizard Polling Jitter; Reverb/Echo Event-Driven Updates (Completed)

Pre-Execution Goal Articulation (STAR)

SITUATION
- Code Analysis wizard refreshed session/events/tasks/artifacts/reports every 3s via polling.
- Poll-driven collection reload toggled loading state repeatedly, causing Task Graph table to flash "Loading tasks…" and jump during active runs.

TASK
- Remove polling behavior from Code Analysis wizard.
- Use Reverb/Echo broadcast events as the primary update mechanism.
- Eliminate table jitter by keeping existing rows visible during realtime refreshes.

ACTION
- [x] Removed polling timer loop (`schedulePoll`) from Code Analysis wizard lifecycle.
- [x] Kept realtime subscription on `private-code-analysis.{sessionId}` and merged incoming events directly.
- [x] Added debounced event-driven refresh queues for session and related collections.
- [x] Scoped collection refreshes to relevant event types only (task/report/coverage/snapshot plan events).
- [x] Updated collection loading mode to support silent refreshes so existing rows remain visible.
- [x] Updated Task Graph loading row to render only when no tasks exist.

RESULT
- Task Graph no longer flashes every few seconds from polling.
- Realtime updates are now driven by Reverb/Echo events with targeted, silent follow-up fetches.
- UI remains stable while still reflecting task/report/artifact changes during execution.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - `resources/js/Pages/Tools/RepoAnalysis/Wizard.vue`:
    - removed polling timer/setup teardown.
    - added event-type based debounced `queueSessionRefresh` + `queueCollectionsRefresh`.
    - changed `loadCollections` to support `silent` mode.
  - `resources/js/Components/RepoAnalysis/TaskGraphPanel.vue`:
    - loading row now only appears when `loading && tasks.length === 0`.
- Conditions where this works:
  - Echo/Reverb channel is connected and session channel authorization succeeds.
  - Backend emits `RepoAnalysisSessionUpdated` events through `EventWriter`.
- Explicit non-goals / limitations:
  - No fallback polling remains in this wizard; if websocket is unavailable, data only updates on manual/user-triggered refresh actions.

### Session 16 Task 13 — Code Analysis Task Graph UX: Spinners, Status Badges, Overall Progress (Completed)

ACTION PLAN
- [x] Add status badges for task rows with visual severity/state mapping.
- [x] Add inline loading spinners for active task statuses.
- [x] Add overall progress summary and progress bar for task completion.
- [x] Verify with web test + frontend build.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Updated `resources/js/Components/RepoAnalysis/TaskGraphPanel.vue`:
    - added row-level status badges with semantic colors,
    - added inline spinner for active statuses (`running|starting|in_progress|retrying`),
    - added aggregate task counters and an overall completion progress bar,
    - changed loading row to include spinner and only appear for initial empty-load state.
  - Verification:
    - `php artisan test tests/Feature/Web/Tools/RepoAnalysisNavigationTest.php` => `4 passed (122 assertions)`.
    - `npm run build` => client and SSR builds completed successfully.
- Conditions where this works:
  - Task list API returns statuses for each row.
  - Wizard receives task list updates (now event-driven via Reverb/Echo).
- Explicit non-goals / limitations:
  - Progress bar reflects task terminal-state completion only; it does not estimate per-task internal progress percentage.

### Session 16 Task 14 — Add Design Pattern + Coding Standards/Quality Analysis to Repo Analyzer (Completed)

ACTION PLAN
- [x] Add deterministic analyzer `architecture_patterns` to extract pattern signals/evidence generically across stacks.
- [x] Add deterministic analyzer `code_quality_standards` to extract lint/format/static-analysis/CI quality signals and quality commands.
- [x] Wire both analyzers into default task graph profile and coverage requirements.
- [x] Add explicit AI sections for design patterns and coding standards/code quality, and include them in final report dependencies.
- [x] Surface these new outputs in report composition and in-app report viewer deterministic appendix.
- [x] Update tests and run code-analysis verification suite + frontend build.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Added analyzers:
    - `app/Support/RepoAnalysis/Analyzers/ArchitecturePatternsAnalyzer.php`
    - `app/Support/RepoAnalysis/Analyzers/CodeQualityStandardsAnalyzer.php`
  - Registry/graph wiring:
    - `app/Support/RepoAnalysis/Analyzers/AnalyzerRegistry.php`
    - `config/repo_analysis.php`
  - AI task expansion and report synthesis:
    - `app/Jobs/RepoAnalysis/PlanRepoAnalysisTasksJob.php`
    - `app/Support/RepoAnalysis/AiTaskRunner.php`
    - `app/Support/RepoAnalysis/ReportComposer.php`
  - UI surfacing:
    - `resources/js/Components/RepoAnalysis/ReportViewer.vue`
  - Test/build verification:
    - `php artisan test tests/Unit/Support/RepoAnalysis tests/Integration/RepoAnalysis tests/Unit/Config/RepoAnalysisConfigTest.php --stop-on-failure`
      - Result: `49 passed (213 assertions)`.
    - `npm run build`
      - Result: client + SSR builds completed successfully.
- Conditions where this works:
  - Snapshot manifest includes path/content entries for files selected by snapshot policy.
  - Analyzer profile is `default` (or includes the new analyzer keys).
  - AI synthesis feature flag is enabled for the new AI sections to execute.
- Explicit non-goals / limitations:
  - Deterministic pattern/quality extraction remains heuristic and evidence-based; it does not execute linters/tests/type-checkers.
  - Precision/recall of pattern detection varies by naming conventions and file-content availability in snapshots.

## 2026-03-03 Code Analysis Naming Consolidation
- [x] Replace remaining legacy repository-centric naming with "Code Analysis"/`code-analysis` in project docs/history and route mentions.
- [x] Rename discovery/plan spec filenames from prior repository-centric names to `code-analysis-tool*.md`.
- [x] Re-scan the repository for stale user-facing naming.
- [x] Run targeted verification (PHP tests + frontend build).
- [ ] Commit changes.

### Review
- `rg` scan confirms zero remaining legacy repository-centric naming strings in tracked source/docs.
- Renamed docs specs:
  - `docs/discovery/code-analysis-tool.md`
  - `docs/plans/code-analysis-tool.md`
  - `docs/plans/code-analysis-tool-v2.md`
- Verification:
  - `php artisan test --filter=RepoAnalysisNavigationTest` (pass)
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest` (pass)
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest` (pass)
  - `npm run build` (pass)
- Notes:
  - Initial parallel test run failed due shared Postgres test DB migration races; sequential reruns were green.

## 2026-03-03 Code Analysis Timeout Failure Recovery
- [x] Add `ExecuteRepoAnalysisTaskJob::failed(Throwable)` to reconcile timeout/job-failure state.
- [x] Mark in-flight running task as failed with error details and finished timestamp.
- [x] Transition session out of `executing` into terminal failed state on queue failure and persist error code/summary.
- [x] Emit `task_failed` event envelope so websocket UI refreshes status and unlocks retry controls.
- [x] Surface failure reasons in Code Analysis wizard/task graph UI.
- [x] Align default code-analysis queue timeout with AI task timeout defaults.
- [x] Add regression test for timeout failure path.
- [x] Run verification commands.

### Review
- Verified commands:
  - `php artisan test --filter=RepoAnalysisExecutionPipelineTest`
  - `php artisan test --filter=RepoAnalysisApiLifecycleTest`
  - `php artisan test --filter=RepoAnalysisConfigTest`
  - `npm run build`
- Result: all pass.
