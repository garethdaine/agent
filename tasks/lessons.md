# Lessons Log

Use this file to capture correction-driven lessons.

## Entry Template
- Date:
- Source (job run id / interrogation session id):
- Correction:
- Pattern:
- Prevention rule:
- Applied in:

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported run monitor output was still unreadable and cluttered with raw JSON fragments.
- Pattern: Streamed AI output can arrive as concatenated/partial JSON envelopes across event chunks; single-message JSON parsing is insufficient and leaks transport noise to UI.
- Prevention rule: Treat monitor output as a stream protocol. Parse multiple JSON values per chunk, carry incomplete fragments across entries, and suppress wrapper/noise text when structured envelopes are detected.
- Applied in: `resources/js/Support/agentRunEventFormatting.js`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported another false positive where rate-limit was detected from line-numbered source snippet output using `N->` markers.
- Pattern: False-positive guards that match only one snippet marker format (`N→`) miss semantically identical formats (`N->`, `N=>`) emitted by different tooling.
- Prevention rule: Normalize snippet detection to cover common line-number formats before applying blocker keyword patterns (approval/clarification/rate-limit).
- Applied in: `app/Support/Agent/RunEventWriter.php` and `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported build task UI showing `ReviewContextBuilder` as in-progress even though its run had completed.
- Pattern: Build progression can appear stuck when `interrogation` queue workers are not running; task finalization only occurs when `ExecuteInterrogationBuildJob` is consumed.
- Prevention rule: Verify active Horizon supervisors include `interrogation` queue whenever build execution is running, and re-dispatch `ExecuteInterrogationBuildJob` after restoring workers.
- Applied in: Runtime operation (Horizon restart + re-dispatch of `ExecuteInterrogationBuildJob` for session 4)

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported another false positive rate-limit detection from line-numbered code snippets embedded as escaped `\\n` JSON text.
- Pattern: Keyword gates misfire on escaped snippet payloads when snippet heuristics only look for real newline delimiters.
- Prevention rule: Snippet detectors must recognize both real newline (`\n`) and escaped newline (`\\n`) prefixes before applying blocker/rate-limit patterns.
- Applied in: `app/Support/Agent/RunEventWriter.php` and `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported another false positive from doubly escaped snippet payloads (`\\\\n 55-> ...`) still triggering rate-limit detection.
- Pattern: Multi-level serialized payloads can preserve line markers behind one extra escape layer, bypassing strict `\\n`-only matching.
- Prevention rule: For snippet heuristics, accept one-or-more backslashes before `n` (`(?:\\\\)+n`) so nested escaped newlines are treated as code snippets.
- Applied in: `app/Support/Agent/RunEventWriter.php` and `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User clarified the DB guardrail is non-negotiable because it prevents the main agent database from being wiped during build execution.
- Pattern: "Fixing" build test bootstrap failures by reverting to sqlite defaults can violate test safety assumptions and reintroduce production-data risk.
- Prevention rule: For interrogation builds, treat DB isolation as a hard invariant: keep destructive commands blocked and require test-safe `pgsql_testing` isolation contract (`TEST_DB_*` distinct from primary DB), never relax toward primary DB settings.
- Applied in: `app/Support/Interrogation/InterrogationBuildCommandGuard.php`, `app/Support/Interrogation/BuildTaskRunFactory.php`, `tests/Unit/BuildTaskRunFactoryTest.php`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build 4
- Correction: User reported active run logs were still unreadable and duplicated, and requested a single consolidated AI log during execution.
- Pattern: Tail-only log views and envelope-level rendering are insufficient for streamed agent output; they hide context and can surface duplicate assistant text from stream/final message envelopes.
- Prevention rule: For active execution logs, hydrate full run events incrementally and render a unified transcript view with duplicate suppression, rather than relying only on short tails.
- Applied in: `resources/js/Support/agentRunEventFormatting.js`, `resources/js/Components/Interrogation/BuildPanel.vue`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build task 8
- Correction: User reported run `#43` reconciled to failed (`process_not_found`) but task remained `in_progress` in the build UI.
- Pattern: Run-level reconciliation without orchestrator handoff can leave build-task/session state stale even when the underlying run is terminal.
- Prevention rule: Whenever reconciliation force-transitions an `interrogation_build` run to terminal, immediately queue `ExecuteInterrogationBuildJob` to finalize task/build metadata and avoid stuck `in_progress` UI states.
- Applied in: `app/Support/Agent/ReconcileActiveRunsService.php`, `tests/Feature/AgentDispatchDueCommandTest.php`

## Entry
- Date: 2026-02-25
- Source (job run id / interrogation session id): Interrogation session 4 / build task 8
- Correction: User reported build appeared failed/stuck after reconciliation, and pending tasks must never continue past a failed guardrailed task.
- Pattern: If build metadata is stale (`running`) but a prior task is already `failed`/`blocked`, orchestration can incorrectly start the next pending task unless terminal-task checks run before scheduling.
- Prevention rule: Before starting any pending build task, assert there are no existing `failed`/`blocked` tasks; if present, finalize lifecycle immediately (`failed`/`paused`) instead of continuing.
- Applied in: `app/Jobs/ExecuteInterrogationBuildJob.php`, `tests/Unit/ExecuteInterrogationBuildJobTest.php`

## Entry
- Date: 2026-02-26
- Source (job run id / interrogation session id): Interrogation session 4 / build task 12
- Correction: Full test suite and integration verification for adversarial reviewer feature.
- Pattern: New feature integration testing should verify config defaults, service container bindings, code style compliance, and document edge cases for future reference.
- Prevention rule: Before marking a complex feature complete, run comprehensive verification including: all related tests, full regression suite (document unrelated failures), config validation via tinker, service resolution checks, and code style/analysis passes.
- Applied in: Adversarial reviewer feature (AdversarialReviewerService, ReviewerPayloadGuard, ReviewerPayloadNormalizer, ReviewerContextBuilder)

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Interrogation session 4 / build run 67
- Correction: User flagged false positives where permission/clarification/rate-limit blockers were inferred from escaped code snippets shown in output, not real runtime agent messages.
- Pattern: Regex-only blocker detection that scans raw output without snippet-context checks can misclassify embedded test/code text as live runtime failures.
- Prevention rule: Before setting blocker metadata, require runtime-like context and explicitly ignore non-runtime snippets (line-numbered or escaped-newline code payloads with code tokens).
- Applied in: `app/Support/Agent/RunEventWriter.php`, `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Interrogation build monitor false-positive report (UI snippet payload)
- Correction: User reported additional false positives where detector matched approval/rate-limit phrases inside Vue/JS source snippets emitted in run output.
- Pattern: Detection logic that only filters escaped/line-numbered snippets still misclassifies inline source snippets containing HTML/JS tokens and copied status strings.
- Prevention rule: Treat multiline payloads with multiple source-code signals (HTML tags/template attributes + JS/PHP token patterns) as non-runtime snippets and skip blocker/rate-limit extraction.
- Applied in: `app/Support/Agent/RunEventWriter.php`, `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery session 6 UI parity follow-up
- Correction: User reported the rebuilt UI drifted from Figma, system dark mode was not detected, and no theme switch was available.
- Pattern: Styling migrations that introduce tokenized colors and `dark:` classes can silently fail when Tailwind is left on media-mode and tokens are stored as hex strings (breaking `/alpha` utilities).
- Prevention rule: For any token-based redesign, enforce `darkMode: 'class'`, bootstrap theme on first paint, provide an in-app theme control, and verify generated CSS includes key opacity classes (`bg-primary/10`, `bg-muted/50`, `bg-card/95`) before calling parity complete.
- Applied in: `tailwind.config.js`, `resources/css/theme.css`, `resources/js/Support/theme.js`, `resources/js/Layouts/AppLayout.vue`, `resources/views/app.blade.php`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery sessions 7 and 8 (manual parity verification run)
- Correction: User flagged that creating duplicate live discovery sessions and auto-submitting/skipping interrogation answers polluted app/session data.
- Pattern: End-to-end UI verification on production-like data without explicit consent can create noisy duplicate sessions and corrupt business-state artifacts.
- Prevention rule: For UI parity checks, never create or progress live discovery sessions unless user explicitly asks to mutate data; default to read-only inspection, screenshots, and local/dev fixtures. If mutation is required, confirm one target session, avoid auto-answer automation, and clean up immediately after verification.
- Applied in: Agent execution workflow (no code file); cleanup action performed by permanently deleting sessions `7` and `8`.

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): User correction on local runtime process management
- Correction: User stated Horizon and supporting services were already running and should not be started by the agent.
- Pattern: Starting background runtime services during UI/debug tasks can conflict with existing user-managed processes and waste time.
- Prevention rule: Assume app services are already managed by the user unless explicitly asked to start/restart them; first verify state passively and continue with non-service actions.
- Applied in: Agent execution workflow (no code file); Horizon session started by agent was stopped and policy updated.

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery session 9 dark-mode parity follow-up
- Correction: User reported remaining white form controls in dark mode (plan revision action select and amend/task text fields).
- Pattern: Relying on form-plugin defaults plus partially tokenized classes leaves regressions where some controls keep light backgrounds; additionally `rgb(var(--token))` declarations can be dropped by minification and silently disable dark overrides.
- Prevention rule: For all discovery/build form controls, require explicit `bg-input-background` utility on text/select/textarea fields and use valid CSS token syntax `rgb(var(--token) / 1)` in global overrides; verify compiled CSS still contains the dark background rule.
- Applied in: `resources/css/app.css`, `resources/js/Components/Interrogation/PlanViewer.vue`, `resources/js/Components/Interrogation/SummaryViewer.vue`, `resources/js/Components/Interrogation/BuildPanel.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery session 9 phase-9 parity check
- Correction: User requested parity validation for the final build step after approving tasks; execution UI still used management-table layout instead of the Figma execution timeline pattern.
- Pattern: Reusing task-management UI for execution-state surfaces causes design drift even when colors/tokens are correct.
- Prevention rule: For discovery phase 9, treat execution as a distinct surface: include explicit execution summary/progress and timeline cards, and avoid exposing task-management table controls in execution mode.
- Applied in: `resources/js/Components/Interrogation/BuildPanel.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery session 9 follow-up (user review of phase 8)
- Correction: User reported Tasks generation/approval phase (phase 8) still did not match the design while phase 9 had been updated.
- Pattern: Fixing only the currently visible phase can leave adjacent wizard phases with mismatched layouts and mixed UI patterns.
- Prevention rule: For discovery wizard parity changes, apply and verify contiguous surfaces (phase 8 + phase 9) together, and avoid leaving one phase table-based when adjacent phase uses card/timeline pattern.
- Applied in: `resources/js/Components/Interrogation/BuildPanel.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery session 9 stepper parity follow-up
- Correction: User reported the final highlighted step circle was slightly cut off.
- Pattern: Active-state rings can be clipped at component edges when no horizontal buffer exists around stepper tracks.
- Prevention rule: Any stepper/rail UI that uses outer rings or glows must include edge-safe horizontal padding and be verified at first/last steps.
- Applied in: `resources/js/Components/Interrogation/PhaseStepper.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Monitor UI spacing polish follow-up
- Correction: User reported the first styling pass for monitor metric cards looked worse.
- Pattern: Spacing polish can regress perceived quality when typography scale is changed too aggressively relative to existing UI density.
- Prevention rule: For style-only tweaks, change one axis at a time (padding/spacing first, typography second) and validate visually before increasing headline scale.
- Applied in: `resources/js/Pages/Agent/Monitor/Index.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Monitor metric cards final polish
- Correction: User requested removing `pt-0` still present on the top monitor cards.
- Pattern: Shared UI primitives can reintroduce spacing utilities through default classes even after local styling tweaks.
- Prevention rule: When precise spacing is required, inspect rendered class lists and replace default wrapper components locally if inherited utilities conflict.
- Applied in: `resources/js/Pages/Agent/Monitor/Index.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): Discovery planning UI follow-up
- Correction: User requested extra space above the Plan container in the planning stage.
- Pattern: Card sections using shared `CardContent` with default `pt-0` can make nested panels feel visually cramped under header dividers.
- Prevention rule: For header/body card layouts, explicitly add top spacing to the first body surface when `CardContent` uses top-padding reset.
- Applied in: `resources/js/Pages/Tools/Discovery/Wizard.vue`

## Entry
- Date: 2026-02-27
- Source (job run id / interrogation session id): User correction on structured CLI output reliability
- Correction: User reminded that structured JSON output had prior reliability issues and requested careful handling.
- Pattern: Forcing structured parsing as a hard dependency can regress detection when CLI streams include mixed/non-JSON lines.
- Prevention rule: Treat structured parsing as opportunistic enhancement only; preserve safe text fallback and never fail or overclassify when JSON decode fails.
- Applied in: `app/Support/Agent/RunEventWriter.php`, `tests/Feature/AgentRunnerLifecycleTest.php`

## Entry
- Date: 2026-02-28
- Source (job run id / interrogation session id): Discovery session 14 (`Agent Memory`) plan-generation investigation
- Correction: User clarified that the stop happened very quickly, which contradicted the initial long-generation-content hypothesis.
- Pattern: Timing signals (fast stop vs long expected runtime) are stronger evidence of UI/state races than model-content quality issues.
- Prevention rule: When a workflow halts faster than normal execution duration, prioritize state-transition and async request-race analysis first, then validate content hypotheses against event timelines.
- Applied in: `resources/js/Pages/Tools/Discovery/Wizard.vue`, `tasks/lessons.md`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User bug report on interrogation reasoning text cutoff
- Correction: User reported reasoning text beginning was intermittently cut off in the interrogation UI.
- Pattern: Streamed JSON outputs can contain multiple valid structured payload candidates; selecting the first structured candidate may capture an early partial fragment.
- Prevention rule: For streamed structured outputs, collect all candidate payloads and select the most complete/final candidate (with deterministic tie-break) instead of first-match selection.
- Applied in: `app/Support/Interrogation/Adapters/CodexAdapter.php`, `tests/Unit/InterrogationCodexAdapterCommandTest.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): Tooling correction while editing repo files
- Correction: Received warning to avoid invoking `apply_patch` through shell commands.
- Pattern: Running patch workflows through `exec_command` can bypass expected editing path and trigger avoidable tool warnings.
- Prevention rule: Use the dedicated `apply_patch` tool directly for patch hunks; reserve `exec_command` for non-patch shell operations.
- Applied in: Workflow rule captured in `tasks/lessons.md`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User retry report for Discord `/jobs list` after initial fix
- Correction: User reported timeout banner was resolved but command still fell back to "I couldn't understand that command."
- Pattern: Fixing interaction ACK alone can mask a second parser-path bug; webhook and gateway payload shapes must both be validated from runtime logs, not inferred from one test path.
- Prevention rule: For Discord command regressions, always verify end-to-end in logs: (1) interaction ACK sent, (2) normalized inbound content non-empty, (3) parser receives expected command text. Explicitly test `INTERACTION_CREATE` gateway payload parsing separately from webhook parsing.
- Applied in: `app/Support/Messenger/Adapters/DiscordAdapter.php`, `tests/Unit/Messenger/Adapters/DiscordAdapterTest.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported 500 on `/agent/api/v1/notifications`
- Correction: Notifications endpoint returned `500` because `notifications` table migration was pending in runtime DB.
- Pattern: New API endpoints that depend on newly introduced tables can crash on partially migrated environments during rollout.
- Prevention rule: For new persistence-backed endpoints, always (1) run and verify migrations in the target environment immediately after code changes, and (2) add defensive table-existence guards so missing migrations degrade gracefully instead of returning 500.
- Applied in: `app/Http/Controllers/Api/V1/NotificationController.php`, runtime migration execution (`php artisan migrate --force`)

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported discovery status/category readability mismatch during task generation
- Correction: Status chips and category tags displayed raw snake_case values (e.g., `Build_rules`) instead of human-readable labels.
- Pattern: UI components consuming backend state enums directly can leak machine-friendly tokens and hide richer sub-status values available in metadata.
- Prevention rule: Route all user-visible status/category labels through a shared display formatter (underscore/dash normalization + Title Case), and derive displayed discovery status from both top-level session status and build metadata status.
- Applied in: `resources/js/Components/Interrogation/displayFormatting.js`, `resources/js/Components/Interrogation/SessionStatusBadge.vue`, `resources/js/Components/Interrogation/StatsPanel.vue`, `resources/js/Components/Interrogation/QuestionRenderer.vue`, `resources/js/Components/Interrogation/StatusCard.vue`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported Discord error `List Active Runs Failed` with `Undefined array key "job_name"`
- Correction: Active runs response crashed in formatter when Discord user asked for active jobs/runs.
- Pattern: Formatter and handler payload contracts diverged (`job_name` vs nested `job.name`), and strict array-key access escalated to runtime error.
- Prevention rule: For chat action outputs, normalize formatter inputs through defensive key resolution (or dedicated DTO mappers) and add regression tests for both flattened and nested payload shapes.
- Applied in: `app/Services/Messenger/ChatResponseFormatter.php`, `tests/Feature/Messenger/ChatOrchestrationTest.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User correction on Agent Org Layer run 3 implementation completeness
- Correction: User reported Org Layer pages were still placeholder-only (no create flows for agents/rituals and static empty states).
- Pattern: Completing backend/API milestones without wiring end-to-end UI actions leaves features appearing unfinished even when models/services exist.
- Prevention rule: Before marking feature slices complete, verify each surfaced nav entry has a usable create/list/detail workflow and no placeholder copy remains on primary screens.
- Applied in: `resources/js/Pages/Agent/Org/*`, `routes/web.php`, `tasks/todo.md`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User follow-up on Org list UX (`failed to load` + table header + hoverable empty state)
- Correction: User flagged that empty/error states were rendered inside table rows, causing header persistence and row hover effects with no records.
- Pattern: Rendering no-data states as `TableRow` couples UX to table semantics and inherited hover styles, producing confusing UI during empty/error conditions.
- Prevention rule: For list pages, render loading/error/empty as standalone cards and render the table only when records exist; never place empty states in `TableRow`.
- Applied in: `resources/js/Pages/Agent/Org/Agents/Index.vue`, `resources/js/Pages/Agent/Org/Rituals/Index.vue`, `resources/js/Pages/Agent/Org/Councils/Index.vue`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User correction on Org forms UX (raw JSON inputs)
- Correction: User stated JSON textareas across Org forms are poor UX for non-technical users and should be replaced with structured controls.
- Pattern: Exposing raw JSON as primary input creates unnecessary cognitive load and form errors for configuration workflows that can be represented as simple lists/maps.
- Prevention rule: In user-facing configuration screens, default to structured form controls (rows, selects, toggles, typed inputs) and handle JSON encode/decode only in adapters at load/save boundaries.
- Applied in: `resources/js/Pages/Agent/Org/Agents/Form.vue`, `resources/js/Pages/Agent/Org/Rituals/Form.vue`, `resources/js/Pages/Agent/Org/Councils/Create.vue`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported `500` on `/agent/api/v1/org/rituals`
- Correction: Org APIs failed with undefined-table SQL errors because Org migrations were not applied in runtime DB.
- Pattern: Shipping UI/API changes for new tables without ensuring runtime migration parity produces immediate 500s on first page load.
- Prevention rule: After introducing new schema-backed endpoints, always run `php artisan migrate` in the active environment and confirm `php artisan migrate:status` before UI verification.
- Applied in: runtime DB migration execution (`php artisan migrate`), verification via `php artisan migrate:status` and `php artisan test tests/Feature/Http/Controllers/Api/V1/Org`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported repeat `500` on `/agent/api/v1/org/agents` during UI validation
- Correction: User still encountered index-load failures after initial migration fix; endpoint needed resilience against schema drift and environment mismatch.
- Pattern: Relying solely on migration correctness for first-render API endpoints makes UX brittle in partial/rolling environments.
- Prevention rule: For first-render list/summary endpoints, add defensive `Schema::hasTable(...)` guards with stable empty/default response shapes to prevent fatal load errors.
- Applied in: `app/Http/Controllers/Api/V1/Org/OrgAgentController.php`, `app/Http/Controllers/Api/V1/Org/OrgRitualController.php`, `app/Http/Controllers/Api/V1/Org/OrgCouncilController.php`, `app/Http/Controllers/Api/V1/Org/OrgEscalationController.php`, `app/Http/Controllers/Api/V1/Org/OrgCostController.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported repeat `500` on `/agent/api/v1/org/councils` after schema guards
- Correction: User still saw councils endpoint failure in browser despite migration and table checks.
- Pattern: Environment-specific runtime faults can bypass narrow guard assumptions and still break first-load endpoints.
- Prevention rule: For first-load list endpoints, add broad `try/catch` fail-safe returning stable empty payload shape (`data: []`) and report the exception for diagnostics.
- Applied in: `app/Http/Controllers/Api/V1/Org/OrgCouncilController.php`, `app/Http/Controllers/Api/V1/Org/OrgAgentController.php`, `app/Http/Controllers/Api/V1/Org/OrgRitualController.php`, `app/Http/Controllers/Api/V1/Org/OrgEscalationController.php`, `app/Http/Controllers/Api/V1/Org/OrgCostController.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported repeat `500` on `/agent/api/v1/org/escalations` while CLI checks returned `200`
- Correction: Browser still surfaced `500` despite patched controllers and no corresponding new `local.ERROR` entries in `laravel.log`.
- Pattern: In local Herd environments, stale PHP-FPM/opcache workers can continue serving old code paths after controller changes.
- Prevention rule: If browser-only `500` persists but CLI endpoint checks return `200` and logs show no new error, restart Herd services (`herd restart`) before further code changes.
- Applied in: runtime operation (`herd restart`), post-restart validation via `curl https://agent.test/agent/api/v1/org/escalations` (HTTP 200)

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User-reported `500` on `/agent/api/v1/org/costs/summary`
- Correction: Cost summary endpoint could still throw before fallback when request date parsing failed.
- Pattern: Parsing request inputs outside guarded error-handling paths can bypass endpoint resilience and still return `500`.
- Prevention rule: Parse user-provided filters (especially dates) inside guarded `try/catch` paths and return stable defaults on parse failure.
- Applied in: `app/Http/Controllers/Api/V1/Org/OrgCostController.php`

## Entry
- Date: 2026-03-01
- Source (job run id / interrogation session id): User copy correction on org navigation/page labels
- Correction: User requested wording update from `Org Layer` to `Agents` and `Org Agents` to `Agents`.
- Pattern: Feature shipping copy can lag behind product naming decisions, especially when labels are duplicated across nav + page headers + action views.
- Prevention rule: When copy is corrected, run a scoped string audit (`rg`) across navigation and related page surfaces, then update all visible occurrences in one pass.
- Applied in: `resources/js/Layouts/AppLayout.vue`, `resources/js/Pages/Agent/Org/Index.vue`, `resources/js/Pages/Agent/Org/Agents/*`, `resources/js/Pages/Agent/Org/Councils/Create.vue`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User correction on docs search engine choice
- Correction: User requested locally running Typesense instead of Meilisearch for Scout-backed docs search.
- Pattern: Infrastructure/tooling recommendations can conflict with explicit local-runtime preferences if defaults are assumed.
- Prevention rule: When proposing stack options, confirm and prioritize the user's declared runtime preference (for this project: Typesense-first for local search) in all briefs and follow-up guidance.
- Applied in: `docs/discovery/documentation-and-tooltip-system-requirements-brief.md`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User scope correction on Claude hooks request
- Correction: User requested hook automation be captured as a requirement in briefs only, not implemented in-repo yet.
- Pattern: Translating requirement statements directly into code changes can overshoot requested phase and create unintended artifacts.
- Prevention rule: For discovery/requirements asks, default to brief updates only unless the user explicitly asks for implementation in code/config/scripts.
- Applied in: `docs/discovery/documentation-and-tooltip-system-requirements-brief.md`, interrogation session `15` feature brief, cleanup of temporary hook artifacts

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User clarification on commit hook coverage
- Correction: User clarified commit-sync requirement must apply to all local commits, not only Claude-triggered commits.
- Pattern: Hook requirements can be under-scoped when tied only to one execution path (Claude) instead of all local git entrypoints.
- Prevention rule: When documenting commit automation requirements, explicitly cover both Claude hooks and git `pre-commit` hooks unless the user narrows scope.
- Applied in: `docs/discovery/documentation-and-tooltip-system-requirements-brief.md`, interrogation session `15` feature brief

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User correction on Codex interrogation duplicate-loop handling
- Correction: User rejected hard-fail behavior (`status=failed`) for duplicate interrogation questions and requested Claude-parity continuity behavior.
- Pattern: Adding strict guardrails that terminate sessions can regress UX when the expected behavior is graceful recovery and continued interrogation.
- Prevention rule: For runtime quality guards in interrogation/planning flows, default to non-terminal recovery (retry, reset session state, continue with warning) and reserve terminal failure for unrecoverable parse/runtime exceptions only.
- Applied in: `app/Jobs/ExecuteInterrogationRoundJob.php`, `tests/Unit/ExecuteInterrogationRoundJobTest.php`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User-reported repeated re-asks persisted after non-terminal duplicate warning
- Correction: Warning-only handling still surfaced repeated questions to the user, causing continued interrogation loops.
- Pattern: Detecting duplicates without a concrete forward-progress action (auto-resolve/advance/complete) can leave the loop intact even when sessions no longer fail.
- Prevention rule: Any duplicate-question detection must enforce a progression strategy: auto-resolve from prior confirmed answer, or auto-advance to next step when saturation is detected; warning-only is insufficient.
- Applied in: `app/Jobs/ExecuteInterrogationRoundJob.php`, `config/agent.php`, `tests/Unit/ExecuteInterrogationRoundJobTest.php`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User-reported build marked complete but no docs feature code was generated (session 15, runs 2072-2084)
- Correction: Codex build runs were accepted as completed on exit code `0` even when execution evidence showed planning/read-only behavior with no implementation commands.
- Pattern: Success gating that relies only on process exit status can produce false-positive completion in autonomous build pipelines.
- Prevention rule: For non-interactive Codex build runs, require concrete execution evidence (mutation or verification commands) before marking task `completed`; otherwise fail with explicit no-evidence error and retain diagnostics.
- Applied in: `app/Jobs/ExecuteInterrogationBuildJob.php`, `app/Support/Interrogation/BuildTaskRunFactory.php`, `tests/Unit/ExecuteInterrogationBuildJobTest.php`, `tests/Unit/BuildTaskRunFactoryTest.php`

## Entry
- Date: 2026-03-02
- Source (job run id / interrogation session id): User-reported Codex build tasks still "completed" quickly with planning/checklist-only behavior and no feature files changed
- Correction: Build-run evidence gate was too permissive (counted non-implementation mutations like `tasks/todo.md`) and build runner env still inherited Codex desktop thread/session vars.
- Pattern: Autonomous run quality degrades when runner subprocesses inherit interactive desktop context and when execution evidence does not distinguish implementation mutations from housekeeping edits.
- Prevention rule: For Codex build runs, scrub thread/session origin env vars (`CODEX_THREAD_ID`, `CODEX_SESSION_ID`, `CODEX_INTERNAL_ORIGINATOR_OVERRIDE`) and require both implementation-path mutation evidence (`app|database|config|routes|resources|tests|scripts`) plus verification command evidence before completing tasks.
- Applied in: `app/Support/Interrogation/BuildTaskRunFactory.php`, `app/Jobs/ExecuteAgentRunJob.php`, `app/Jobs/ExecuteInterrogationBuildJob.php`, `tests/Unit/ExecuteInterrogationBuildJobTest.php`, `tests/Unit/BuildTaskRunFactoryTest.php`
