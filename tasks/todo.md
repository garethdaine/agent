# Workflow Orchestration Instruction Rollout Plan

## 2026-02-27 - Discovery Wizard Full Session Validation (Natural Language Scheduling)

- [ ] Create a brand-new discovery session using `docs/discovery/natural-language-scheduling.md` as the feature brief on `https://agent.test`.
- [ ] Drive the session end-to-end (setup → tech stack → discovery → interrogation → summary → planning → rules → tasks → build execution).
- [ ] Capture UI parity findings at every stage/control surface against Figma reference (`https://pause-kindle-31517753.figma.site/tools/discovery/6` + wizard design system).
- [ ] Patch wizard subcomponents that still use legacy styling and do not match the Figma visual language.
- [ ] Re-run the same session flow checks in browser after patching.
- [ ] Record verification results and any residual gaps.

## 2026-02-27 - Figma UI Parity Hotfix (Discovery + App Shell)

- [x] Compare current Laravel UI shell and Discovery Wizard against Figma reference (`https://pause-kindle-31517753.figma.site/`).
- [x] Fix global theme architecture:
  - [x] class-based dark mode in Tailwind
  - [x] system preference detection on first load
  - [x] persistent user override (light/dark) and visible switch control in app shell.
- [x] Fix design-token opacity compatibility so classes like `bg-primary/10`, `bg-muted/50`, `border-border/60` render correctly.
- [x] Align AppLayout and Discovery Wizard spacing/controls to Figma shell baseline (top-nav controls, max-width consistency, button/icon treatment).
- [x] Run build/verification checks and capture results.
- [x] Add review notes with what changed, what was verified, and any remaining gaps.

### Review (2026-02-27)

- Implemented class-based theme switching with system detection and persistent local preference (`light`/`dark`/`system`) applied at document boot and at app runtime.
- Updated design tokens to RGB-channel CSS variables and Tailwind color mapping with alpha support (`rgb(var(--token) / <alpha-value>)`), restoring missing opacity utilities used across the redesigned UI.
- Added visible theme controls to app shell (desktop icon toggle, mobile controls, and profile-menu appearance actions).
- Aligned discovery wizard chrome closer to Figma shell (iconized controls and removed nested max-width/padding layer that constrained layout).
- Verification:
  - `npm run build` passed (client + SSR bundles)
  - built CSS now includes expected token-opacity classes, including `.bg-primary/10`, `.bg-muted/50`, and `.bg-card/95`.
- Remaining gap:
  - Full page-by-page pixel-parity sweep against every Figma route is still outstanding; this pass focused on shell/theme/token issues that were causing major visual drift.

## Context

Integrate the instruction set into both orchestration surfaces:

- Scheduled and manual Agent Jobs (`AgentJob` + `ExecuteAgentRunJob` path)
- Interrogation Builds (`GenerateInterrogationBuildTasksJob` + `ExecuteInterrogationBuildJob` path)

The rollout must preserve existing run safety, queue stability, and auditability.

## Goals

- Enforce consistent planning, execution, and verification behavior across jobs and builds.
- Make instruction compliance measurable (not prompt-only best effort).
- Keep changes incremental, feature-flagged, and reversible.

## Phase 1 - Shared Policy Layer (Foundation)

- Add `WorkflowInstructionPolicy` service to define normalized rules from the instruction set.
- Add `TaskComplexityClassifier` service (`simple` vs `non_trivial`) with deterministic heuristics.
- Add config section `agent.workflow_orchestration` for rule toggles and thresholds.
- Add feature flags:
  - `agent.workflow_orchestration.enabled`
  - `agent.workflow_orchestration.enforce_verification`
  - `agent.workflow_orchestration.enforce_lessons`
- Add run/build metadata contract for compliance state:
  - `plan_required`, `plan_completed`
  - `verification_required`, `verification_evidence`
  - `elegance_review_required`, `elegance_review_completed`
  - `lessons_required`, `lessons_updated`

## Phase 2 - Plan Mode Default

- Implement pre-execution policy evaluation hook for both paths:
  - Job runs: evaluate before command launch in `ExecuteAgentRunJob`.
  - Build tasks: evaluate before task run creation in `BuildTaskRunFactory` / `ExecuteInterrogationBuildJob`.
- For non-trivial work, inject a mandatory planning checklist into task markdown/system prompt.
- Add re-plan trigger rule when failure/blocker patterns are detected ("stop and re-plan").
- Persist plan evidence in metadata (plan steps count, timestamp, source evidence).

## Phase 3 - Subagent Strategy

- Extend build-task generation prompt to require subagent usage for research/exploration on non-trivial tasks.
- Add optional per-run policy hints for subagent behavior in task markdown:
  - one task per subagent
  - parallel exploration allowed
- Parse execution events for subagent activity signals and store compliance evidence.
- Add fallback path for runners without subagent support (log advisory, do not hard-fail).

## Phase 4 - Self-Improvement Loop (Lessons)

- Standardize lessons file path to `tasks/lessons.md`.
- Add `LessonsManager` service:
  - detect user correction signals
  - append structured lesson entry (pattern, prevention rule, date, run/session id)
  - read relevant lessons at session/build start and inject into context
- Build flow integration:
  - include lesson-context block in generated task markdown when applicable
- Job flow integration:
  - include lesson-context block in run preamble when applicable

## Phase 5 - Verification Before Done

- Add `VerificationEvidenceEvaluator` service to validate evidence from run output/events.
- Define minimum evidence policy by task type:
  - code change tasks require test or lint command evidence
  - behavior changes require command output or diff proof
- Jobs: block terminal `succeeded` without required evidence when enforcement flag is on.
- Builds: block task completion and pause build with actionable remediation when evidence is missing.
- Add explicit event payloads for verification pass/fail reasons.

## Phase 6 - Demand Elegance + Autonomous Bug Fixing

- Add non-trivial checkpoint requiring "elegance pass" before completion.
- Add heuristics for hacky fix detection (temporary markers, TODO debt phrases, missing root-cause notes).
- Bug-fix mode rules:
  - on failure/bug tasks, require logs/errors/failing-test evidence before final success
  - require explicit root-cause summary in completion payload
- If elegance/root-cause checks fail, transition to blocked/failed with precise remediation guidance.

## Phase 7 - Task Management Workflow Enforcement

- Add task-file policy checks for:
  - `tasks/todo.md` includes checklist and progress updates for non-trivial work
  - review section exists in `tasks/todo.md`
- Build mode:
  - generated tasks include required task-management steps
- Jobs mode:
  - injected preamble includes task-management expectations where repository has `tasks/`

## Phase 8 - API/UI/Observability

- Extend session/job responses with compliance summary fields.
- Add event and metrics dimensions:
  - compliance gate failures by gate type
  - verification pass rate
  - re-plan frequency
  - lessons update rate after corrections
- Expose compliance state in UI:
  - Jobs monitor
  - Build panel and task details

## Phase 9 - Testing + Rollout

- Unit tests:
  - complexity classification
  - policy evaluation
  - verification evidence evaluator
  - lessons manager
- Feature tests:
  - job run blocked when required verification is missing
  - build task pauses on missing plan/verification evidence
  - lessons file updated after correction event
- Progressive rollout:
  - deploy with advisory-only mode
  - enable enforcement for builds first
  - enable enforcement for jobs after stability window

## Mapping Matrix (Instruction -> Platform)

- Plan Mode Default
  - Jobs: pre-run complexity gate + mandatory plan evidence
  - Builds: task-level plan gate + re-plan transition
- Subagent Strategy
  - Jobs: policy preamble + event evidence
  - Builds: generation prompt requirement + event evidence
- Self-Improvement Loop
  - Jobs: lesson injection + update on corrections
  - Builds: lesson injection + update on corrections
- Verification Before Done
  - Jobs: success gate tied to evidence evaluator
  - Builds: task completion gate tied to evidence evaluator
- Demand Elegance
  - Jobs: elegance checkpoint metadata + blocker on failure
  - Builds: elegance checklist in task output contract
- Autonomous Bug Fixing
  - Jobs: root-cause and failing-signal evidence gate
  - Builds: same gate in task finalization path

## Delivery Order Recommendation

- Sprint 1: Phase 1 + Phase 2 (advisory only)
- Sprint 2: Phase 5 + Phase 9 tests for builds
- Sprint 3: Phase 4 + Phase 6
- Sprint 4: jobs enforcement + UI/metrics hardening

## Review

- Scope reviewed: jobs and builds orchestration paths only.
- Non-goals: messenger workflows, delegation graph subsystem, external CI providers.
- Success criteria: all non-trivial runs/build tasks are policy-evaluated, verification-gated, and auditable.

## 2026-02-25 - Monitor Log Formatting Cleanup

- Reproduce unreadable monitor tail output with chunked/concatenated JSON envelopes.
- Implement formatter support for fragmented envelope parsing with cross-entry carry-over.
- Suppress wrapper/noise shards once structured envelopes are detected.
- Verify output readability using fixture-style Node checks against noisy samples.
- Verify no frontend build regressions with `npm run build`.

### Review

- Root cause: payload chunks contained multiple JSON envelope objects plus partial fragments; single-object parsing fell back to raw payload rendering.
- Fix: added robust JSON window extraction + per-event-type carry buffer in `resources/js/Support/agentRunEventFormatting.js` and routed fragment parsing through existing envelope handlers.
- Verification evidence: local Node fixtures now output only reconstructed human-readable assistant text; `npm run build` passed.

## 2026-02-25 - False Positive Rate-Limit Detection (ASCII Line Snippets)

- Reproduce and identify why `12->...` code-snippet output still triggered rate-limit detection.
- Expand line-numbered snippet guard beyond unicode arrow (`→`) to include ASCII arrow markers.
- Add regression test covering `N->` snippet format with `Too many requests` text.
- Verify related lifecycle tests pass.

### Review

- Root cause: detector ignore pattern only matched `N→` snippets, while current output used `N->`.
- Fix: updated `LINE_NUMBERED_SNIPPET_PATTERN` in `app/Support/Agent/RunEventWriter.php` to match `→`, `->`, and `=>`.
- Verification evidence: `php artisan test tests/Feature/AgentRunnerLifecycleTest.php --filter=\"rate_limit_phrase_inside_(line_numbered_snippet|ascii_arrow_line_numbered_snippet)_does_not_trigger_detection\"` passed.

## 2026-02-25 - Build Execution Stuck on Completed Run

- Confirm stuck state mismatch (`task in_progress` while linked run already `succeeded`).
- Validate build metadata pointers (`active_task_id`) and task/run mapping.
- Restore Horizon workers for this repo with `interrogation` supervisor active.
- Re-dispatch `ExecuteInterrogationBuildJob` for session `4` to reconcile task state.
- Verify progression moved from task `30` to task `31`.

### Review

- Root cause: orchestration queue consumption gap; `interrogation` queue worker was not processing delayed `ExecuteInterrogationBuildJob` follow-up jobs.
- Remediation: started Horizon for `/Users/garethdaine/Code/agent` (including `supervisor-interrogation`) and dispatched one reconciliation build job.
- Verification evidence: task `30` now `completed`, `active_task_id=31`, build remains `running`.

## 2026-02-25 - False Positive Rate-Limit Detection (Escaped Newline Snippets)

- Reproduce false positive from tool-result/code-snippet payload containing escaped `\\n` and `N->` line markers.
- Extend line-numbered snippet guard to match escaped newline prefixes.
- Add regression test for escaped-newline snippet payload with `Too many requests` phrase.
- Restart project Horizon workers to load updated detection logic.
- Recover build state by resuming blocked task with refreshed metadata flags.

### Review

- Root cause: snippet-ignore pattern did not match `\\nN->` payloads embedded in JSON strings, so code excerpts triggered rate-limit keywords.
- Fix: updated `LINE_NUMBERED_SNIPPET_PATTERN` to `/(?:^|\\n|\\\\n)\\s*\\d+\\s*(?:→|->|=>)/u` and added regression coverage.
- Verification evidence: targeted lifecycle tests passed; build 4 resumed with `rate_limit_detected=false` and advanced to task `32` run `42`.

## 2026-02-25 - False Positive Rate-Limit Detection (Double Escaped Snippets)

- Reproduce nested escaped snippet form (`\\\\n 55-> ...`) reported in live build output.
- Generalize snippet prefix matcher from fixed `\\n` to one-or-more escaped slashes before `n`.
- Add regression test for doubly escaped snippet payload.
- Restart Horizon workers to load patched detection logic.
- Verify build resumed and progressed without rate-limit pause.

### Review

- Root cause: payload contained doubly escaped newline markers, which bypassed the prior escaped-newline guard and allowed `Too many requests` snippet text to trigger a pause.
- Fix: changed snippet regex to `/(?:^|\\n|(?:\\\\)+n)\\s*\\d+\\s*(?:→|->|=>)/u` and added `test_rate_limit_phrase_inside_double_escaped_newline_line_numbered_snippet_does_not_trigger_detection`.
- Verification evidence: targeted suite now passes with 4/4 snippet false-positive tests; build advanced to task `33` with `rate_limit_detected=false`.

## 2026-02-25 - Preserve DB Safety Guardrail While Unblocking Build Tests

- Reproduce build-time test failure caused by `DB_CONNECTION=sqlite` in interrogation build runs.
- Update interrogation build environment to use `DB_CONNECTION=pgsql_testing` plus isolated `TEST_DB_*` values.
- Keep command guardrail strict by allowing only `sqlite` or `pgsql_testing` and rejecting unsafe test DB names/collisions.
- Update unit tests to assert the new guardrailed build environment contract.
- Verify config test bootstrap succeeds under build-like env without weakening DB safety constraints.

### Review

- Root cause: build runs forced `DB_CONNECTION=sqlite`, while repo tests enforce `database.default=pgsql_testing` to prevent accidental primary DB mutation.
- Fix: `BuildTaskRunFactory` now emits `DB_CONNECTION=pgsql_testing` with explicit `TEST_DB_*` variables, and `InterrogationBuildCommandGuard` enforces safe connection modes and isolated test DB naming.
- Verification evidence:
  - `php artisan test tests/Unit/InterrogationBuildCommandGuardTest.php tests/Unit/BuildTaskRunFactoryTest.php` (11 passed)
  - `AGENT_JOB_SOURCE=interrogation_build APP_ENV=testing DB_CONNECTION=pgsql_testing DB_DATABASE=/tmp/interrogation-build-sentinel.sqlite TEST_DB_DATABASE=agent_test php artisan test tests/Unit/Config/AdversarialReviewerConfigTest.php` (8 passed)

## 2026-02-25 - Unified Build AI Log + Duplicate Suppression

- Reproduce duplicate/unreadable active build run log rendering from mixed stream envelopes.
- Add formatter-level duplicate suppression for repeated assistant/plain payloads in short windows.
- Add full active-run event hydration in build execution panel via `/agent/api/v1/runs/{id}/events`.
- Render a single unified active-run transcript block for readability while preserving sequence context.
- Verify frontend compiles and run-log behavior is readable without repeated duplicate lines.

### Review

- Root cause: build panel only rendered a short `log_tail` slice and mixed stream/final assistant envelopes could surface repeated human text blocks.
- Fix:
  - Added duplicate suppression window for repeated plain/markdown payloads in `resources/js/Support/agentRunEventFormatting.js`.
  - Added full active-run event hydration in `resources/js/Components/Interrogation/BuildPanel.vue` using `/agent/api/v1/runs/{id}/events`.
  - Replaced multi-card tail rendering with a single unified transcript block (`Active Run AI Log`) for current run readability.
- Verification evidence:
  - `npm run build` passed (client + SSR builds successful).
  - `node --input-type=module` smoke check confirms duplicate assistant payloads collapse to a single formatted entry.

## 2026-02-25 - Reconcile `process_not_found` Run Into Build Progression

- Reproduce case where run transitions to failed (`process_not_found`) but build task remains `in_progress`.
- Patch reconciliation flow to dispatch `ExecuteInterrogationBuildJob` for `interrogation_build` runs after terminal reconciliation.
- Prevent orchestration from starting a new pending task when an earlier task is already `failed` or `blocked`.
- Ensure dispatch failure is non-fatal and logged as a lifecycle system notice.
- Add regression test proving reconcile queues build orchestration for interrogation build metadata.
- Add regression test proving failed task state blocks further pending task starts.
- Re-run targeted build orchestration unit tests to ensure no regression.

### Review

- Root cause: `ReconcileActiveRunsService` finalized orphaned runs but did not hand control back to build orchestration, so build task/session metadata could remain stale until a manual nudge.
- Fix:
  - Added `dispatchInterrogationBuildFollowUp()` in `app/Support/Agent/ReconcileActiveRunsService.php` to queue `ExecuteInterrogationBuildJob` for reconciled `interrogation_build` runs, with a guarded error path (`build_reconcile_dispatch_failed`) on dispatch exceptions.
  - Added a preflight terminal-task guard in `ExecuteInterrogationBuildJob` so any existing `failed`/`blocked` task finalizes lifecycle before pending tasks can start.
- Verification evidence:
  - `php artisan test tests/Feature/AgentDispatchDueCommandTest.php --filter=interrogation_build`
  - `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php`

## 2026-02-26 - Feature Flags Managed Via Settings Screen

- Add DB-backed feature flag settings persistence and model/service layer with config/env fallback defaults.
- Add authenticated API endpoints to read/update managed feature flags.
- Add Tools settings page for feature flags and wire route/navigation entry points.
- Switch runtime gates (delegation API/UI, delegation commands, adversarial reviewer checks, Inertia shared props) to resolve via settings service.
- Update tests for new settings API and runtime flag-resolution behavior.
- Verify with targeted PHPUnit runs and document results.

### Review

- Root cause: feature toggles were hard-coded to config/env reads, so runtime enable/disable required file/env updates and worker restarts.
- Fix:
  - Added persisted feature settings table + model (`agent_feature_settings`) and centralized resolver (`FeatureFlagManager`) with config/env fallback defaults.
  - Added authenticated API endpoints to read/update managed feature flags (`/agent/api/v1/features/settings`).
  - Added Tools UI page (`/tools/features/settings`) and Tools index entry for operator-driven toggle management.
  - Rewired runtime gates to use resolver-backed flags for delegation API/UI middleware, delegation commands, adversarial reviewer gates, and shared Inertia delegation nav prop.
  - Made delegation subscribers always registered; handlers now self-gate on runtime feature state to support live toggle changes.
- Verification evidence:
  - `./vendor/bin/pint`
  - `npm run build`
  - `APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2-)" php artisan test tests/Feature/FeatureSettingsApiTest.php tests/Feature/DelegationUiAccessTest.php tests/Feature/AdversarialReviewerDisabledTest.php tests/Feature/Http/Controllers/Api/V1/DelegationGraphControllerTest.php tests/Unit/Support/Agent/FeatureFlagManagerTest.php tests/Unit/Listeners/DelegationBroadcastSubscriberTest.php`

## 2026-02-26 - Fix Reviewer Subprocess `--cwd` Option Failure

- [x] Confirm root cause and locate reviewer command construction.
- [x] Remove unsupported reviewer CLI argument while preserving working directory behavior.
- [x] Update unit coverage to match supported reviewer command contract.
- [x] Run targeted tests to verify reviewer command construction and parsing still pass.

### Review

- Root cause: `ClaudeAdapter::buildReviewerCommand()` passed `--cwd <project_directory>` to the reviewer CLI, but reviewer subprocess execution already sets CWD via `Process::setWorkingDirectory()`. The extra CLI flag is unsupported and caused `unknown option '--cwd'`.
- Fix: removed `--cwd` and project-directory argument from reviewer command construction while preserving subprocess working directory via `AdversarialReviewerService`.
- Verification evidence:
  - `php artisan test tests/Unit/ClaudeAdapterReviewerTest.php` (16 passed)
  - `php artisan test tests/Unit/AdversarialReviewerServiceTest.php` (4 passed)
  - `php artisan test tests/Feature/AdversarialReviewerShadowModeTest.php --filter=shadow_mode_catches_reviewer_exceptions` failed due pre-existing test DB teardown issue (`SQLSTATE[42P01] table "account_link_tokens" does not exist`), unrelated to reviewer command patch.

## 2026-02-27 - Fix False-Positive Blocker Detection From Escaped Code Snippets

- [x] Reproduce and confirm false positives originate from output regex detection over escaped code payloads.
- [x] Add non-runtime snippet guard to detection path for approval, permission blocker, clarification, and rate-limit signals.
- [x] Add regression tests for escaped code snippets containing each signal phrase.
- [x] Run lifecycle regression tests and verify pass.

### Review

- Root cause: blocker/rate-limit/clarification/approval detectors matched phrases inside escaped code snippets (fixture/test code text), then terminal gating treated those metadata flags as runtime blockers.
- Fix:
  - Added `isLikelyNonRuntimeSnippet()` guard in `app/Support/Agent/RunEventWriter.php`.
  - Guard now skips detection when payload is line-numbered snippet or escaped-newline payload with code-like tokens.
  - Applied guard before all four detectors (approval, permission blocker, clarification, rate-limit).
  - Added feature regressions in `tests/Feature/AgentRunnerLifecycleTest.php` for escaped snippet false-positive cases.
- Verification evidence:
  - `php artisan test tests/Feature/AgentRunnerLifecycleTest.php` (19 passed)

### Review (Follow-up)

- Additional root cause: UI/template source text from active-run output (Vue/JS snippets) still triggered phrase detectors even after escaped-snippet handling.
- Follow-up fix:
  - Added inline source-snippet heuristic in `RunEventWriter` to skip detection for multiline payloads with strong code/template signals.
  - Added regressions for Vue-template approval phrase and JavaScript rate-limit phrase snippets.
- Verification evidence:
  - `php artisan test tests/Feature/AgentRunnerLifecycleTest.php` (21 passed)

## 2026-02-27 - Full Natural Language Scheduling Discovery Run (Session 9)

- [ ] Complete full browser walkthrough for `/tools/discovery/9` from setup to build phase completion without creating additional sessions.
- [ ] Answer every interrogation question (no skips), verify question flow advances correctly, and capture any UI/control-surface mismatches.
- [ ] Verify Summary stage content/surfaces and controls; revise only if required for completion fidelity.
- [ ] Verify Planning stage content/surfaces and controls; generate/approve/regenerate/revise as needed to complete the stage.
- [ ] Verify Build Rules + Build Tasks stages and controls; generate tasks, validate task list UI behavior, and approve tasks.
- [ ] Verify Build Execution stage controls/surfaces and capture final session state evidence.
- [ ] Patch any discovered UI parity mismatches against Figma design surfaces and rebuild frontend assets.
- [ ] Document review evidence and outcomes in this file.

## 2026-02-27 - Dark Mode Form Field Regression (Discovery Wizard)

- [x] Locate remaining white controls in discovery summary/plan/build surfaces.
- [x] Patch form control classes so text/select/textarea controls use `bg-input-background`.
- [x] Fix global dark form override to use minifier-safe token syntax (`rgb(var(--input-background) / 1)`).
- [x] Rebuild frontend assets to verify compiled CSS keeps dark input/select backgrounds.

### Review

- Root cause:
  - Several Build panel inputs/textareas still relied on form-plugin defaults without explicit tokenized background classes.
  - Base dark form override used `rgb(var(--input-background))`, which was stripped in minified output, so fallback dark background rule was not applied consistently.
- Fix:
  - Added `bg-input-background` to remaining Build panel editable fields and regeneration notes input surfaces.
  - Updated Plan revision action select to explicit tokenized field styling (full-width, border, background, focus ring).
  - Added explicit border utility on Summary amendment/interrogation focus textareas for consistent control rendering.
  - Updated global form control base styles in `resources/css/app.css` to use `rgb(var(--token) / 1)` and tokenized border/text colors.
- Verification evidence:
  - `npm run build` (client + SSR) passed.
  - Compiled CSS check confirms `.dark input...,.dark select,.dark textarea{background-color:rgb(var(--input-background) / 1);...}` is present in `public/build/assets/app-mfAROlMI.css`.

## 2026-02-27 - Build Step (Phase 9) Parity Alignment

- [x] Compare live build execution step against Figma reference (`pause-kindle-31517753.figma.site/tools/discovery/6`).
- [x] Refactor execution-mode surface from table-first layout to progress-header + timeline/accordion pattern.
- [x] Keep Tasks-phase editable table unchanged while isolating execution-phase presentation.
- [x] Rebuild frontend assets and verify running session (`/tools/discovery/9`) renders updated execution UI.
- [x] Monitor live execution metadata for blockers after parity patch.

### Review

- Root cause:
  - Execution phase used the same dense table pattern as task management, which drifted from the Figma final-step design intent (execution overview card + progress + task timeline cards).
- Fix:
  - Updated `resources/js/Components/Interrogation/BuildPanel.vue` execution mode to:
    - render `Build Execution` summary card with action controls and progress meter,
    - render task timeline rows with status badges and expandable details,
    - remove execution-mode duplicate task table surface.
  - Restricted editable task table to Tasks phase only (`isTasksMode`).
- Verification evidence:
  - `npm run build` passed (client + SSR).
  - Live parity screenshot after patch: `tmp/build-step-live-after-parity.png`.
  - Reference screenshot used for comparison: `tmp/build-step-figma-reference.png`.
  - Runtime monitor after patch showed healthy progression without blockers:
    - `completed:2, in_progress:1, pending:9`
    - `active_task_id:88`, `active_run_id:1727`
    - `flags:null`, `requires_clarification:null`

### Review (Follow-up)

- Additional parity gap:
  - Tasks phase (phase 8) was still table-based and visually inconsistent with the updated execution-phase card/timeline pattern.
- Follow-up fix:
  - Refactored phase-8 tasks list from table to expandable card/timeline rows.
  - Added Tasks summary card with status chips and action group (`Generate`, `Approve`, `Start Build`) to align with design language.
  - Preserved edit/delete/regenerate workflows by moving controls into expanded task-card details.
  - Restricted phase-specific controls so rules/actions/execution views render with clear separation.
- Verification evidence:
  - `npm run build` passed after phase-8 refactor (client + SSR).

## 2026-02-27 - Phase Stepper Active Circle Clipping

- [x] Identify the stepper layout/style causing the active final-step circle to clip on the right edge.
- [x] Patch stepper container spacing so the active ring renders fully at step 9.
- [x] Rebuild frontend assets to verify no compile regressions.

### Review

- Root cause:
  - The active step uses an outer ring (`ring-4`), and the stepper row had no horizontal breathing room, so the final node could be clipped at the container edge.
- Fix:
  - Added horizontal padding to the stepper scroll container in `resources/js/Components/Interrogation/PhaseStepper.vue` (`overflow-x-auto px-1`).
- Verification evidence:
  - `npm run build` (client + SSR) passed.

## 2026-02-27 - Monitor Metrics Card Spacing Polish

- [x] Audit the top monitor metrics cards (`Scheduler Health`, `Queue Lag`, `Poll Status`) for spacing/typography inconsistency.
- [x] Refine card content padding, heading rhythm, and metric/body text spacing while preserving existing colors/states.
- [x] Verify the updated cards render correctly at `https://agent.test/agent/monitor` and run a frontend build check.

### Review

- Root cause:
  - The first styling pass increased metric typography too aggressively, which made card content feel heavier and less balanced than the original design.
- Final fix:
  - Kept improved card structure (`p-6`, consistent icon/label rhythm, consistent detail spacing).
  - Reverted metric typography to compact sizes (`text-[22px]` range) with tighter leading.
  - Added `min-h-[136px]` and `flex-col` card content layout for stable vertical spacing across all three cards.
  - Removed inherited `pt-0` on the top monitor cards by replacing `CardContent` with explicit padded wrappers for that row only.
- Verification evidence:
  - Browser-verified on `https://agent.test/agent/monitor`.
  - Screenshot: `tmp/monitor-cards-pt0-removed.png`.

### Review (Structured Output Hardening)

- User concern: prior issues existed with structured JSON streams from CLI output.
- Hardening approach:
  - Added structured-event-aware guards for non-runtime events (e.g., `type=user` with `tool_result`) to prevent false positives.
  - Added structured rate-limit error detection (`type=error|turn.failed|result` with rate-limit indicators).
  - Kept structured parsing opportunistic (`json_decode` best-effort); fallback heuristics still operate if parsing fails.
- Verification evidence:
  - `php artisan test tests/Feature/AgentRunnerLifecycleTest.php` (23 passed)
