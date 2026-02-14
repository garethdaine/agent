# Requirements Discovery Feature — Implementation Plan

> **Discovery spec:** [`docs/discovery/requirements-discovery-feature.md`](../discovery/requirements-discovery-feature.md)

## Implementation Status (2026-02-13)
- Phase 1: complete (Claude baseline validated)
- Phase 2: complete (state transitions, event writer, adapters, resolver, reconstructor, export service)
- Phase 3: complete (discovery/round/summary/plan jobs + Horizon interrogation supervisor config)
- Phase 4: complete (Reverb broadcast events + private channel auth)
- Phase 5: complete (requests/controllers/routes/rate-limited mutation paths)
- Phase 6: complete (Inertia routes + Tools navigation entry)
- Phase 7: complete (Tools/Discovery pages and interrogation components with Echo subscription)
- Phase 8: initial coverage complete (integration + config + event contract tests); further polish remains for CLI edge-case retries and richer UX refinements.

## Context

The Agent app (Laravel 12 + Vue 3/Inertia) currently manages scheduled CLI agent jobs. The user wants a GUI wizard that mirrors the CLI interrogation workflow (`docs/interrogate.md`) — an interactive, AI-driven requirements gathering tool that asks structured questions, builds a specification, and transitions into implementation planning. The full confirmed spec lives at `docs/discovery/requirements-discovery-feature.md`.

This is the first feature to activate the existing Reverb WebSocket infrastructure.

---

## Phase 1: Database Schema, Models, Policies

**Status: COMPLETE** — migrations, models, policy, and 29 passing tests already built.

### Migrations

**New:** `database/migrations/2026_02_13_100000_create_interrogation_sessions_table.php`
- Pattern: `database/migrations/2026_02_12_020511_create_agent_jobs_table.php`
- Columns: `id`, `user_id` (FK cascade), `name` (string 255, nullable), `runner_type` (string 16), `project_directory` (string 1024), `interrogation_type` (string 16: feature/general), `feature_brief` (text, nullable), `status` (string 24), `phase` (unsignedTinyInteger default 0), `cli_session_id` (string 255, nullable), `summary_json` (json, nullable), `plan_json` (json, nullable), `annotations_json` (json, nullable), `metadata_json` (json, nullable), `error_code` (string 100, nullable), `error_summary` (text, nullable), `started_at` (timestampTz, nullable), `finished_at` (timestampTz, nullable), `softDeletes`, `timestamps`
- Indexes: `(user_id, status, deleted_at)`, `(user_id, created_at)`

**New:** `database/migrations/2026_02_13_100001_create_interrogation_events_table.php`
- Pattern: `database/migrations/2026_02_12_020513_create_agent_run_events_table.php`
- Columns: `id`, `interrogation_session_id` (FK cascade), `event_type` (string 32), `sequence` (unsignedBigInteger), `payload` (json), `event_ts` (timestampTz(3)), `timestamps`
- Indexes: unique `(session_id, sequence)`, index `(session_id, event_type)`

**New:** `database/migrations/2026_02_13_100002_create_interrogation_settings_table.php`
- Columns: `id`, `user_id` (FK cascade), `key` (string 120), `value` (json), `timestamps`
- Index: unique `(user_id, key)`

### Models

**New:** `app/Models/InterrogationSession.php`
- Pattern: `app/Models/AgentJob.php` + `app/Models/AgentJobRun.php`
- Traits: `SoftDeletes`, `$guarded = []`
- Status constants: `SETUP`, `DISCOVERING`, `INTERROGATING`, `SUMMARIZING`, `PLANNING`, `COMPLETED`, `FAILED`, `PAUSED`
- Status groups: `ACTIVE_STATUSES`, `TERMINAL_STATUSES`, `RESUMABLE_STATUSES`
- Phase constants: `PHASE_SETUP` (0) through `PHASE_PLANNING` (4)
- Type constants: `TYPE_FEATURE`, `TYPE_GENERAL`
- Casts: json columns as `array`, timestamps as `datetime`
- Relationships: `user()` BelongsTo, `events()` HasMany
- Scopes: `active()`, `forUser()`, `latest()`

**New:** `app/Models/InterrogationEvent.php`
- Pattern: `app/Models/AgentRunEvent.php`
- Event type constants: `DISCOVERY_ACTIVITY`, `QUESTION`, `ANSWER`, `PHASE_TRANSITION`, `SUMMARY`, `PLAN`, `ERROR`, `ANNOTATION`, `SYSTEM`
- Casts: `payload` as `array`, `sequence` as `integer`, `event_ts` as `datetime`
- Relationship: `session()` BelongsTo

**New:** `app/Models/InterrogationSetting.php`
- `$guarded = []`, cast `value` as `array`
- Static helpers: `getForUser(userId, key, default)`, `setForUser(userId, key, value)`

**Modify:** `app/Models/User.php` — add `interrogationSessions()` and `interrogationSettings()` HasMany relationships

### Policy

**New:** `app/Policies/InterrogationSessionPolicy.php`
- Pattern: `app/Policies/AgentJobPolicy.php`
- Ownership-based: `user_id === $user->id`
- `create()`: also checks active session count < 3

**Modify:** `app/Providers/AppServiceProvider.php` — register policy via `Gate::policy()`, add 120/min `interrogation` rate limiter

### Tests
- `tests/Unit/InterrogationSessionModelTest.php`
- `tests/Unit/InterrogationEventModelTest.php`
- `tests/Feature/InterrogationSessionPolicyTest.php`

---

## Phase 2: Backend Services

### State Transition Service

**New:** `app/Support/Interrogation/SessionStateTransitionService.php`
- Pattern: `app/Support/Agent/RunStateTransitionService.php`
- Atomic WHERE-IN-STATUS UPDATE pattern
- `transition(sessionId, fromStatuses[], toStatus, attributes[])`: bool
- `transitionPhase(sessionId, fromPhase, toPhase, toStatus, attributes[])`: bool

### Event Writer

**New:** `app/Support/Interrogation/InterrogationEventWriter.php`
- Pattern: `app/Support/Agent/RunEventWriter.php`
- Auto-incrementing sequence, redaction for discovery output
- Methods: `appendDiscoveryActivity()`, `appendQuestion()`, `appendAnswer()`, `appendPhaseTransition()`, `appendSummary()`, `appendPlan()`, `appendError()`, `appendAnnotation()`
- Each method creates an `InterrogationEvent` and broadcasts via `InterrogationSessionUpdated`

### Runner Adapter

**New:** `app/Support/Interrogation/Contracts/InterrogationRunnerAdapter.php` (interface)
- `buildDiscoveryCommand(session, systemPrompt)`: array — stream-json output for discovery
- `buildQuestionCommand(session, userMessage, systemPrompt)`: array — print mode with resume + JSON schema
- `buildPlanCommand(session, userMessage, systemPrompt)`: array — resume with elevated tools
- `buildReconstructCommand(session, conversationHistory, systemPrompt)`: array — fallback from DB
- `parseStreamEvent(line)`: ?array — normalize stream-json to unified format
- `parseQuestionResponse(output)`: ?array — parse structured JSON question
- `buildEnvironment(session)`: array — env vars for subprocess

**New:** `app/Support/Interrogation/Adapters/ClaudeAdapter.php`
- Discovery: `claude -p --output-format stream-json --system-prompt <prompt> --tools "Read,Glob,Grep" <discovery_prompt>`
- Q&A: `claude -p --resume <cli_session_id> --output-format json --json-schema <schema> --tools "Read,Glob,Grep" <user_answer>`
- Plan: `claude -p --resume <cli_session_id> --tools "Read,Glob,Grep,Write,Edit" <planning_prompt>`
- Parses Claude's stream-json event format into unified events

**New:** `app/Support/Interrogation/Adapters/CodexAdapter.php`
- Discovery: `codex exec --json <discovery_prompt>`
- Q&A: `codex resume <cli_session_id> --json --output-schema <schema> <user_answer>`
- Plan: `codex resume <cli_session_id> <planning_prompt>`
- Parses Codex's newline-delimited JSON event format into unified events

**New:** `app/Support/Interrogation/AdapterFactory.php`
- `make(runnerType)`: returns `ClaudeAdapter` or `CodexAdapter`

### Supporting Services

**New:** `app/Support/Interrogation/SystemPromptResolver.php`
- Loads from DB setting (per-user), falls back to `docs/interrogate.md`
- Injects feature brief context when type=feature
- Adds phase-specific instructions (tool restrictions, output format expectations)

**New:** `app/Support/Interrogation/ConversationReconstructor.php`
- Reads all InterrogationEvents for a session
- Rebuilds conversation as prompt input for `buildReconstructCommand()`

**New:** `app/Support/Interrogation/ExportService.php`
- `exportSummary(session)`: writes to `{project}/docs/discovery/{kebab-name}.md`, auto-creates dir, version suffix on conflict (-v2, -v3)
- `exportPlan(session)`: writes to `{project}/docs/plans/{kebab-name}.md`, includes header reference to discovery summary

### Tests
- `tests/Unit/SessionStateTransitionServiceTest.php`
- `tests/Unit/InterrogationEventWriterTest.php`
- `tests/Unit/ClaudeAdapterTest.php`
- `tests/Unit/CodexAdapterTest.php`
- `tests/Unit/AdapterFactoryTest.php`
- `tests/Unit/SystemPromptResolverTest.php`
- `tests/Unit/ConversationReconstructorTest.php`
- `tests/Unit/InterrogationExportServiceTest.php`

---

## Phase 3: Queue Jobs + Horizon Config

### Jobs

All follow the pattern from `app/Jobs/ExecuteAgentRunJob.php`: `ShouldQueue` + `Queueable`, Symfony Process, 250ms poll loop, signal handling, state transitions.

**New:** `app/Jobs/ExecuteInterrogationDiscoveryJob.php`
- `onQueue('interrogation')`, constructor takes `int $sessionId`
- Transitions session to `discovering`, builds discovery command via adapter
- Spawns Symfony Process, captures stream-json stdout in poll loop
- Parses each line via `adapter->parseStreamEvent()`, writes + broadcasts
- On completion: transitions to `interrogating`, dispatches first Q&A round

**New:** `app/Jobs/ExecuteInterrogationRoundJob.php`
- Constructor takes `int $sessionId`, `string $userMessage`
- Builds Q&A command via adapter with `--resume` and JSON schema
- Executes process (short-lived, ~30-60s)
- Parses structured JSON question, writes + broadcasts
- If progress_estimate=100 or AI signals done: transitions to `summarizing`
- If CLI resume fails: falls back to `ConversationReconstructor`
- Auto-retry with backoff on failure (3 attempts, 2s/4s/8s), then surface error

**New:** `app/Jobs/ExecuteInterrogationSummaryJob.php`
- Resumes CLI session with summary prompt
- Parses structured summary, writes to `summary_json` and events
- Broadcasts summary event

**New:** `app/Jobs/ExecuteInterrogationPlanJob.php`
- Resumes CLI session with **elevated tool permissions**
- Parses plan output, writes to `plan_json` and events
- Calls `ExportService` to auto-export both summary and plan
- Broadcasts plan event

### Horizon Config

**Modify:** `config/horizon.php`
- Add to `waits`: `'redis:interrogation' => 30`
- Add `supervisor-interrogation` to `defaults`: queue `['interrogation']`, maxProcesses from `HORIZON_INTERROGATION_MAX_PROCESSES` (default 2), timeout 900, memory 128, tries 1
- Add to `environments.production` and `environments.local`

### Tests
- `tests/Feature/InterrogationDiscoveryJobTest.php` — mock Process, verify events + transitions
- `tests/Feature/InterrogationRoundJobTest.php` — verify parsing, resume fallback
- `tests/Feature/InterrogationHorizonConfigTest.php` — verify supervisor exists

---

## Phase 4: WebSocket Broadcasting

First feature to activate the existing Reverb infrastructure (`resources/js/bootstrap.js` already has Echo configured, `config/broadcasting.php` has Reverb driver).

### Events

**New:** `app/Events/InterrogationSessionUpdated.php` — `ShouldBroadcast`
- Props: `sessionId`, `eventType`, `payload`, `sequence`
- Channel: `private-interrogation.{sessionId}`
- Broadcast as: `session.updated`

**New:** `app/Events/InterrogationPhaseChanged.php` — `ShouldBroadcast`
- Props: `sessionId`, `fromPhase`, `toPhase`, `status`
- Channel: `private-interrogation.{sessionId}`
- Broadcast as: `phase.changed`

### Channel Auth

**Modify:** `routes/channels.php` — add authorization for `interrogation.{sessionId}` (owner check)

### Env

`.env` must set `BROADCAST_CONNECTION=reverb` (currently `null`).

### Tests
- `tests/Feature/InterrogationBroadcastTest.php` — channel auth, event payload structure

---

## Phase 5: API Controllers, Routes, Validation, Rate Limiting

### Form Requests

Pattern: `app/Http/Requests/Agent/StoreAgentJobRequest.php`

**New:** `app/Http/Requests/Interrogation/StoreInterrogationSessionRequest.php`
- Rules: runner_type (in: claude,codex), project_directory (required, max:1024), interrogation_type (in: feature,general), name (nullable, max:255), feature_brief (required_if type=feature, max:50000)
- withValidator: directory exists + readable, user < 3 active sessions

**New:** `app/Http/Requests/Interrogation/SubmitAnswerRequest.php`
- Rules: question_id, answer_type (choice/freetext/skip), answer_text, selected_option, skip_reason

**New:** `app/Http/Requests/Interrogation/UpdateAnnotationRequest.php`
**New:** `app/Http/Requests/Interrogation/RequestPlanRevisionRequest.php`
- action in: expand, simplify, add_examples, rewrite, split_into_steps, add_acceptance_criteria

**New:** `app/Http/Requests/Interrogation/UpdateSettingsRequest.php`

### Controllers

Pattern: `app/Http/Controllers/Api/V1/AgentJobController.php`

**New:** `app/Http/Controllers/Api/V1/InterrogationSessionController.php`
- `index` — paginated list with filters (status, type, runner, q), meta/links/filters/sort format
- `show` — session detail with optional event inclusion
- `store` — create session, dispatch discovery job, return 202
- `submitAnswer` — dispatch Q&A round job, return 202
- `editAnswer` — mark subsequent Q&A stale, dispatch recalculation
- `confirmSummary` — mark confirmed, enable planning
- `generatePlan` — dispatch plan job, return 202
- `requestRevision` — dispatch revision round
- `updateAnnotation` — update annotations_json
- `exportSummary` / `exportPlan` — trigger file export via ExportService
- `pause` / `resume` / `destroy` / `restore`
- `events` — paginated with `after_sequence` (pattern from `AgentRunController.events`)
- Full audit logging on every mutation via `AuditLogger::recordUserAction()`

**New:** `app/Http/Controllers/Api/V1/InterrogationSettingsController.php`
- `index`, `show`, `update` for per-user settings

### Routes

**Modify:** `routes/api.php` — add within existing `agent/api/v1` prefix, `auth:sanctum` group:
- `/interrogation/sessions` — CRUD + lifecycle actions
- `/interrogation/sessions/{id}/answer`, `/confirm-summary`, `/generate-plan`, `/revise-plan`, etc.
- `/interrogation/settings` — CRUD
- All mutations use `throttle:interrogation` middleware

### Tests
- `tests/Feature/InterrogationApiWorkflowTest.php` — full lifecycle
- `tests/Feature/InterrogationSessionValidationTest.php`
- `tests/Feature/InterrogationSettingsApiTest.php`
- `tests/Feature/InterrogationRateLimitTest.php`
- `tests/Feature/InterrogationAuditTest.php`

---

## Phase 6: Inertia Routes + Navigation

### Web Routes

**Modify:** `routes/web.php` — add within authenticated group:
- `GET /tools` → `Tools/Index` (name: `tools.index`)
- `GET /tools/discovery` → `Tools/Discovery/Index` (name: `tools.discovery.index`)
- `GET /tools/discovery/new` → `Tools/Discovery/Create` (name: `tools.discovery.create`)
- `GET /tools/discovery/{id}` → `Tools/Discovery/Wizard` (name: `tools.discovery.wizard`)
- `GET /tools/discovery/settings` → `Tools/Discovery/Settings` (name: `tools.discovery.settings`)

### Navigation

**Modify:** `resources/js/Layouts/AppLayout.vue` — add "Tools" NavLink in both desktop (~line 56) and responsive (~line 207) nav sections, with `active="route().current('tools.*')"`.

---

## Phase 7: Vue Pages + Components

### Pages

**New:** `resources/js/Pages/Tools/Index.vue` — card grid with live status (pattern: `Dashboard.vue`)
**New:** `resources/js/Pages/Tools/Discovery/Index.vue` — session list with filters/pagination (pattern: `Agent/Jobs/Index.vue`)
**New:** `resources/js/Pages/Tools/Discovery/Create.vue` — setup form: runner, directory (native HTML picker), type, name, feature brief (markdown + file attachments)
**New:** `resources/js/Pages/Tools/Discovery/Wizard.vue` — three-panel wizard:
- On mount: load session + events from API, subscribe to `window.Echo.private('interrogation.{id}')` listening for `.session.updated` and `.phase.changed`
- Left: `QaHistoryPanel` (past Q&A, click to edit with stale warning)
- Center: phase-conditional rendering (StatusCard for discovery, QuestionRenderer+AnswerInput for interrogation, SummaryViewer for summary, PlanViewer for planning)
- Right: `StatsPanel` (question count, elapsed time, categories, progress bar)
- Top: `PhaseStepper`

**New:** `resources/js/Pages/Tools/Discovery/Settings.vue` — directories list, system prompt editor, runner paths, default runner

### Components

**New:** `resources/js/Components/Interrogation/PhaseStepper.vue` — 5 steps, active/completed/future states
**New:** `resources/js/Components/Interrogation/QuestionRenderer.vue` — renders question_text as markdown, shows reasoning (collapsible), category tag, progress bar
**New:** `resources/js/Components/Interrogation/AnswerInput.vue` — radio buttons for choice, textarea for freetext, skip button with reason dropdown
**New:** `resources/js/Components/Interrogation/StatsPanel.vue` — question count, elapsed time, categories, progress estimate
**New:** `resources/js/Components/Interrogation/QaHistoryPanel.vue` — scrollable Q&A pairs, click-to-edit with stale warning
**New:** `resources/js/Components/Interrogation/StatusCard.vue` — single card updating in real-time during discovery (parsed tool calls → friendly messages)
**New:** `resources/js/Components/Interrogation/SummaryViewer.vue` — collapsible sections, private notes, flag-for-revision toggle, confirm button
**New:** `resources/js/Components/Interrogation/PlanViewer.vue` — plan display, per-section revision dropdown (Expand/Simplify/Add Examples/Rewrite/Split Into Steps/Add Acceptance Criteria), export button
**New:** `resources/js/Components/Interrogation/SessionStatusBadge.vue` — color-coded status badge

---

## Phase 8: Integration Testing + Polish

Final round of end-to-end testing and cleanup:
- Full lifecycle integration test (create → discovery → Q&A → summary → plan → export)
- WebSocket integration test (event delivery)
- CLI subprocess integration test with mocked process
- Error handling: auto-retry with backoff, graceful fallback to manual retry
- Verify exports write correctly with version suffixes

---

## Files Summary

**New files: ~63** (3 migrations, 3 models, 1 policy, 9 services, 4 jobs, 2 events, 5 form requests, 2 controllers, 5 pages, 9 components, ~20 tests)

**Modified files: 7**
1. `app/Models/User.php` — add relationships
2. `app/Providers/AppServiceProvider.php` — policy registration + rate limiter
3. `config/horizon.php` — add interrogation supervisor
4. `routes/api.php` — add interrogation routes
5. `routes/web.php` — add Inertia page routes
6. `routes/channels.php` — add channel auth
7. `resources/js/Layouts/AppLayout.vue` — add Tools nav link

## Build Order

```
Phase 1 (Schema + Models + Policy)          ✅ COMPLETE
  → Phase 2 (Services)
    → Phase 3 (Queue Jobs + Horizon)
      → Phase 4 (WebSocket)
        → Phase 5 (API + Routes)
          → Phase 6 (Inertia Routes + Nav)
            → Phase 7 (Vue Pages + Components)
              → Phase 8 (Integration Tests + Polish)

Phase 2's ExportService can be built in parallel with Phase 7.
```

## Verification

1. **Run migrations:** `php artisan migrate`
2. **Run tests:** `composer test` (all unit + feature tests pass)
3. **Start services:** `php artisan horizon`, `php artisan reverb:start`, `npm run dev`
4. **Manual workflow test:**
   - Navigate to /tools → click Requirements Discovery
   - Create new session with a test project directory
   - Verify discovery status card updates in real-time via WebSocket
   - Answer questions through the wizard
   - Confirm summary, generate plan
   - Verify exports in target project's `docs/discovery/` and `docs/plans/`
5. **Code quality:** `./vendor/bin/pint` (formatting), PHPStan (static analysis)
