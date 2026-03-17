### Workflow Interrogator — Working Folder Scope and Session Attachments (In Progress)
Assumptions and Scope Boundary
- Scope is the standalone Workflow Interrogator only.
- The feature should interrogate from the user brief first, then optional supporting context from the selected working folder and uploaded files/images.
- This does not turn Workflow Interrogator into codebase discovery; repository inspection must not be the default framing.
STAR
- SITUATION: Workflow Interrogator currently frames the selected directory as repo/codebase context and has no way to attach supporting files or images for the session.
- TASK: Re-scope the feature around brief-first workflow interrogation using the selected working folder as optional context, and add uploaded session attachments that are persisted and used during prompt generation.
- ACTION:
  - [ ] Add bounded session-attachment persistence and storage.
  - [ ] Accept multipart file/image uploads when creating a Workflow Interrogator session.
  - [ ] Expose attachment metadata/download URLs in the presenter and render them in the create/wizard UI.
  - [ ] Update prompt generation so brief + uploaded context are primary and working-folder inspection is optional, not default.
  - [ ] Add tests and run targeted verification.
- RESULT: Workflow Interrogator sessions can include uploaded files/images as context, and the runner prompt aligns with the intended product scope.
### Discovery Wizard Full Session Validation (Natural Language Scheduling)
- [ ] Create a brand-new discovery session using `docs/discovery/natural-language-scheduling.md` as the feature brief on `https://agent.test`.
- [ ] Drive the session end-to-end (setup → tech stack → discovery → interrogation → summary → planning → rules → tasks → build execution).
- [ ] Capture UI parity findings at every stage/control surface against Figma reference.
- [ ] Patch wizard subcomponents that still use legacy styling and do not match the Figma visual language.
- [ ] Re-run the same session flow checks in browser after patching.
- [ ] Record verification results and any residual gaps.
### Agent Org Layer — AI Workforce (Next Feature)
- [ ] Complete discovery session (currently in Setup).
- [ ] Drive through interrogation, summary, planning, and build phases.
- [ ] Implement Phase A: Foundation (org_agent_profiles, org_reporting_edges, org_cost_ledgers).
- [ ] Implement Phase B: Ritual Runtime (org_ritual_templates, org_ritual_runs).
- [ ] Implement Phase C: Council/QA Gates (org_council_templates, org_artifact_reviews, org_escalations).
- [ ] Implement Phase D: Governance (cost ledger thresholds, budget enforcement).
- [ ] Implement Phase E: Hardening (CI compatibility, multi-provider testing).
## 2026-03-02 Code Analysis AI-Driven Report Completion
- [ ] Commit and push.
## 2026-03-03 Code Analysis Naming Consolidation
- [ ] Commit changes.
### Session 17 Build Task 13 — Fix failed migration `2026_03_04_160000_create_reliability_classification_and_gate_transition_tables` (In Progress)
Assumptions and Scope Boundary
- The failure is caused by this migration or ordering/compatibility assumptions around dependent tables.
- Scope is limited to fixing migration reliability and preserving intended schema behavior.
- Existing successful migrations must remain unaffected.
STAR
- SITUATION: The migration `2026_03_04_160000_create_reliability_classification_and_gate_transition_tables` fails during execution.
- TASK: Make the migration run successfully in supported environments while keeping schema intent intact.
- ACTION:
  - [ ] Reproduce the failure and capture the exact DB error.
  - [ ] Inspect migration dependencies/order and identify root cause.
  - [ ] Patch migration(s) with minimal, robust fix.
  - [ ] Re-run migration (and rollback path if needed) to verify.
  - [ ] Document review results and verification evidence.
- RESULT: Migration applies cleanly without regressions in neighboring migrations.
Mandatory Failure Modes Before Coding
- Malicious-caller mode: migration rerun paths (partial state) leave schema inconsistent.
- Tired-maintainer mode: environment-specific SQL differences (SQLite/Postgres/MySQL) cause hidden breakage.
Plan Check
- Pending: reproduce exact error and confirm root cause before editing.
### Session 17 Build Task 15 — Wire Operator Surfaces and Navigation Discoverability (In Progress)
Assumptions and Scope Boundary
- Inertia + auth middleware remain unchanged and are already active for Agent pages.
- Scope is limited to web routes, page/controller wiring, top-level/deep-link discoverability, and role-gated control visibility.
- Non-goals: changing core reliability/cost/escalation business rules, adding new API contracts, or replay engine logic changes.
STAR
- SITUATION: Agent APIs for reliability/cost/escalation/replay exist, but operator-facing web surfaces and navigation discoverability are incomplete for deployments, system overview, escalations, budgets, and replay builds.
- TASK: Authorized users must reach each operator surface from in-app navigation, see required reliability/cost/escalation/build signals, and only see governance controls allowed by role.
- ACTION:
  - [ ] Add tests first:
  - [ ] `tests/Feature/AgentUi/OperatorRouteReachabilityTest.php`
  - [ ] `tests/Feature/AgentUi/NavigationDiscoverabilityTest.php`
  - [ ] `tests/Feature/AgentUi/GovernanceVisibilityTest.php`
  - [ ] Run `php artisan test --filter=AgentUi` and capture red-state failures.
  - [ ] Add web routes and operator controllers for deployments, detail, system overview, escalations, budgets, replay builds, replay build detail.
  - [ ] Add/adjust Inertia props for top-level nav discoverability and deep-link discoverability.
  - [ ] Implement pages:
  - [ ] `resources/js/Pages/Agent/Deployments/*`
  - [ ] `resources/js/Pages/Agent/SystemOverview/Show.vue`
  - [ ] `resources/js/Pages/Agent/Escalations/Index.vue`
  - [ ] `resources/js/Pages/Agent/Budgets/Index.vue`
  - [ ] `resources/js/Pages/Agent/ReplayBuilds/*`
  - [ ] Update `resources/js/Layouts/AppLayout.vue` navigation.
  - [ ] Re-run `php artisan test --filter=AgentUi` to green.
- RESULT: Operator surfaces are reachable in-app within two clicks, nav/deep links are discoverable by feature tests, governance controls are role-gated, and required signals/copy render including delayed vs unobservable reason-code split, stale active-build age indicator, and `not countable (incident open)`.
Mandatory Failure Modes to Cover Before Coding
- Malicious-caller mode: unauthorized users should not see governance action controls that imply privileged state mutation paths.
- Tired-maintainer mode: pages that are URL-accessible but not linked from navigation/deep links create hidden operational paths and failed discoverability.
Plan Check
- Internal check complete: this plan is test-first and includes explicit verification before completion.
Review
Verification Evidence
- Red (before implementation): `php artisan test --filter=AgentUi`
  - 8 failed
  - Missing operator routes (`/agent/deployments`, `/agent/escalations`, `/agent/budgets`, `/agent/replay-builds`), missing `operatorNavigation` props, and missing deployment detail deep links.
- Green (after implementation): `php artisan test --filter=AgentUi`
  - 8 passed (166 assertions)
Conditions for Correctness
- Authenticated users can reach:
  - `/agent/deployments`
  - `/agent/system-overview`
  - `/agent/escalations`
  - `/agent/budgets`
  - `/agent/replay-builds`
- Dashboard exposes discoverable top-level operator nav props for Deployments/System Overview.
- Deployments detail exposes required deep links:
  - health, reliability, cost, attempt lineage, gate transitions, escalation history, replay builds.
- Role-gated controls render by role:
  - admin: pause/resume + escalation + replay controls.
  - central on-call: pause/resume + escalation controls, no replay control.
  - non-privileged: no governance controls.
- UI renders required copy/signal surfaces:
  - `not countable (incident open)` when countability reason is `incident_open`.
  - delayed/unobservable telemetry split with reason codes on System Overview/Escalations.
  - `active_build_age_seconds` and stale-state indicator on System Overview and Replay Builds pages.
Known Limitations / Non-goals
- Governance buttons are visibility-only in these pages; this task does not implement new action handlers.
- System overview delayed/unobservable lists are derived from escalation incidents in active-build scope; no new telemetry aggregation API was introduced.
- Existing `/agent/monitor` route remains available and unchanged as a separate operational surface.
Implementation Progress
Review
- Root cause confirmed: migration `2026_03_04_160000_create_reliability_classification_and_gate_transition_tables` referenced Postgres enum types (`agent_telemetry_failure_class_enum`, `agent_telemetry_failure_reason_code_enum`) that were missing in the current DB state.
- Applied fix in `database/migrations/2026_03_04_160000_create_reliability_classification_and_gate_transition_tables.php`:
  - Added defensive creation of telemetry failure enums before creating `agent_projection.run_classifications`.
  - Kept gate-source enum creation explicit and idempotent.
  - Hardened enum creation with `duplicate_object` exception guards to avoid concurrent-run races.
- Verification evidence:
  - `php artisan migrate --database=pgsql --path=database/migrations/2026_03_04_160000_create_reliability_classification_and_gate_transition_tables.php --force` -> DONE.
  - `php artisan migrate --database=pgsql --force` -> remaining pending migrations (`170000`, `180000`) DONE.
  - `php artisan migrate:status --database=pgsql` shows `160000`, `170000`, `180000` all Ran.
  - Postgres type check via Laravel DB confirms presence of:
    - `public.agent_telemetry_failure_class_enum`
    - `public.agent_telemetry_failure_reason_code_enum`
    - `public.agent_gate_transition_source_enum`
  - Projection table check confirms expected tables exist in `agent_projection`, including `run_classifications` and `workflow_gate_transitions`.
Plan Check
- Complete: failure reproduced, fix implemented, migrations verified, and DB state validated.
Post-Fix Addendum
- Discovered additional DB drift during validation: `public.telemetry_event_ledger` had only 8 legacy columns while current runtime/services expect v1 contract columns.
- Added reconciliation migration:
  - `database/migrations/2026_03_04_190000_reconcile_telemetry_event_ledger_schema.php`
- Reconciliation actions performed:
  - Backfilled missing telemetry columns with safe defaults.
  - Ensured telemetry enum types exist.
  - Cast `failure_class`/`failure_reason_code` to enum types safely.
  - Added missing `run_id`/`run_attempt_id` indexes.
Additional Verification
- `php artisan migrate --database=pgsql --path=database/migrations/2026_03_04_190000_reconcile_telemetry_event_ledger_schema.php --force` -> DONE.
- `php artisan test tests/Feature/Telemetry/IngestionDedupeTest.php tests/Feature/Telemetry/TelemetryLedgerAppendOnlyTest.php` -> 8 passed.
- Schema checks confirm `telemetry_event_ledger` now has expected v1 columns and enum-typed failure columns.
