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
