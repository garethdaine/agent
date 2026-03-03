# Implementation Plan

Derived from discovery session 16.

# Code Analysis Tool Deterministic Implementation Plan

## Scope Boundary
- Implement a new, separate `Code Analysis` tool path without changing existing Requirements Discovery behavior, schemas, or queue workflows.
- Keep deterministic core stages (`Setup -> Snapshot -> Plan -> Execute -> Validate -> Report -> Complete`) fully independent of LLM output.
- Allow optional narrative synthesis only after deterministic gates pass; store as separate provenance-tagged artifact and never mutate deterministic artifacts.
- Restrict repository writes during analysis to system persistence records and explicit report exports only.
- Enforce dedicated Code Analysis user policy constraints:
  - owner-only default access with admin override
  - separate per-user active-session cap of `2`
  - artifact/report retention split (task artifacts TTL, final exports retained)

## Architecture Changes
- Add a new backend module namespace under `app/Support/CodeAnalysis/` and avoid coupling to interrogation internals beyond reusable patterns.
- Create deterministic orchestration components:
  - `SessionStateTransitionService` for atomic state/phase transitions with strict allowed-from matrix
  - `EventWriter` for sequenced append-only events + websocket broadcasting + payload redaction + UTF-8 normalization
  - `SnapshotBuilder` for canonical manifest and snapshot hash generation
  - `TaskGraphBuilder` for deterministic DAG creation with stable `task_key` and ordering
  - `AnalyzerRegistry` + `AnalyzerInterface` for ordered, versioned analyzer execution
  - `CoverageGateService` for completion-blocking checks
  - `ReportComposer` for deterministic report package assembly
  - `ExportService` for markdown/json versioned exports
  - `NarrativeSynthesisService` for optional post-gate narrative generation
- Add code-analysis queue pipeline on Redis via dedicated queue name and Horizon supervisor entry.
- Preserve job isolation from existing interrogation jobs by using separate job classes and queue routing.

Impacted components/files:
- `app/Support/CodeAnalysis/*`
- `app/Jobs/CodeAnalysis/*`
- `config/repo_analysis.php`
- `config/horizon.php`

## Data Model and Migrations
- Introduce dedicated tables and models:
  - `repo_analysis_sessions`
  - `repo_analysis_events`
  - `repo_analysis_tasks`
  - `repo_analysis_artifacts`
  - `repo_analysis_reports`
- Create models:
  - `app/Models/RepoAnalysisSession.php`
  - `app/Models/RepoAnalysisEvent.php`
  - `app/Models/RepoAnalysisTask.php`
  - `app/Models/RepoAnalysisArtifact.php`
  - `app/Models/RepoAnalysisReport.php`
- Migration requirements and constraints:
  - unique index: `(repo_analysis_session_id, sequence)` on events
  - unique index: `(repo_analysis_session_id, task_key)` on tasks
  - unique index: `(repo_analysis_session_id, artifact_key)` on artifacts
  - foreign keys with cascade delete from session to events/tasks/artifacts/reports
  - indexes for lifecycle/status queries (`status`, `phase`, `user_id`, `created_at`)
  - nullable error columns and JSON metadata columns with cast-safe defaults
- Session schema details:
  - include `snapshot_hash`, `manifest_stats`, `report_json`, `metadata_json`, error fields
  - support pause/retry/restart semantics with robust status values
- Task schema details:
  - include `input_hash`, `output_hash`, `attempt_count`, `depends_on`, `artifact_ids`, runtime metadata
- Artifact schema details:
  - include `artifact_type`, `artifact_key`, `content_hash`, `schema_version`, `analyzer_version`, metadata
- Report schema details:
  - include `report_version`, `report_hash`, `report_json`, export paths, metadata

Impacted components/files:
- `database/migrations/*create_repo_analysis_*_table.php`
- model files listed above

## API and Tool Contracts
- Add REST API namespace under `/agent/api/v1/code-analysis/*` in `routes/api.php`.
- Implement session CRUD endpoints and lifecycle mutation endpoints with explicit request validation classes.
- Implement read endpoints for events/tasks/artifacts/reports with pagination and sequence filters.
- Controller scaffolding:
  - `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
- Request scaffolding (minimum):
  - `StoreRepoAnalysisSessionRequest`
  - `UpdateRepoAnalysisSessionRequest`
  - `StartSnapshotRequest`
  - `PlanTasksRequest`
  - `RetryRepoAnalysisTaskRequest`
  - plus additional lifecycle request classes for pause/resume/retry/restart/export/generate
- API response contract decisions:
  - include `phase`, `status`, `latest_sequence`, `snapshot_hash`, gate status, and operator-action hints
  - standardize error response codes for invalid transitions, policy denials, drift-detected pause, gate failures
- Idempotency behavior:
  - lifecycle endpoints should reject invalid duplicate transitions with deterministic error codes
  - retry endpoints should be no-op safe when target already in terminal-compatible state

Impacted components/files:
- `routes/api.php`
- `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
- `app/Http/Requests/Agent/CodeAnalysis/*`
- optional `app/Http/Resources/Agent/CodeAnalysis/*`

## Event Contracts and Realtime Delivery
- Define event envelope contract shared by websocket and polling:
  - `session_id`, `sequence`, `event_type`, `event_ts`, `payload`, `phase`, `status`
- Enforce monotonic sequence generation per session inside transaction boundary.
- Event type payload schemas:
  - `phase_transition`: from/to phase+status, actor, reason
  - `snapshot_progress`: scanned counts, skipped counts, hash progress markers
  - `task_queued|started|completed|failed`: `task_key`, analyzer identity/version, hashes, attempt, error metadata
  - `coverage_gate`: gate id, pass/fail, blocking flag, details
  - `report_generated`: report hash/version, artifact set fingerprint
  - `error|system`: normalized code and operator guidance
- Redaction and normalization:
  - apply secret redaction policy before persistence and broadcast
  - enforce UTF-8 normalization before write
- Poll fallback endpoint behavior:
  - support `since_sequence` cursor for efficient incremental fetch
  - maintain strict ordering parity with websocket stream

Impacted components/files:
- `app/Support/CodeAnalysis/EventWriter.php`
- websocket broadcast events/channels in `app/Events/*` or equivalent
- API read endpoint for events in controller

## Authorization and Scope Enforcement
- Implement policy classes for session/task/artifact/report access with owner-only default and admin override:
  - `RepoAnalysisSessionPolicy` (view, update, delete, mutate lifecycle, view artifacts/reports)
- Register policy mappings in auth provider.
- Enforce dedicated active-session cap (`2`) for Code Analysis only:
  - apply during session create/start transitions
  - return explicit actionable error when cap reached
- Enforce `PathPolicy` for `project_directory` on create/update/start operations.
- Enforce default exclude set and prevent override from removing mandatory safety exclusions unless explicitly allowed by config.
- Enforce operator role checks for restart/retry across ownership boundaries.
- Audit-log all lifecycle mutations (start/pause/resume/retry/restart/export/settings changes).

Impacted components/files:
- `app/Policies/RepoAnalysisSessionPolicy.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Support/CodeAnalysis/*` (authorization and cap checks)
- `app/Models/AgentAuditLog.php` integration points

## Deterministic Snapshot, Planning, and Analyzer Contracts
- Snapshot determinism:
  - canonical sorted traversal by normalized relative path
  - canonical JSON serialization with stable key ordering
  - manifest may store `mtime` metadata but hash input excludes `mtime`
  - include deterministic excludes and profile-based include/exclude rules
- Task planning determinism:
  - stable analyzer selection/order from `AnalyzerRegistry`
  - stable `task_key` generation and explicit dependency encoding
  - deterministic sequence assignment for display and execution planning
- Analyzer output contract:
  - every result must include analyzer id/version, input hash, output hash, runtime metadata, artifact list
  - parser normalization rules to eliminate nondeterministic ordering/formatting
- Resume reuse contract:
  - reuse completed task outputs only when both `input_hash` and `analyzer_version` match current context
  - otherwise mark affected tasks for rerun and emit explicit reason event

Impacted components/files:
- `app/Support/CodeAnalysis/SnapshotBuilder.php`
- `app/Support/CodeAnalysis/TaskGraphBuilder.php`
- `app/Support/CodeAnalysis/Analyzers/AnalyzerInterface.php`
- `app/Support/CodeAnalysis/Analyzers/AnalyzerRegistry.php`
- analyzer implementations under `app/Support/CodeAnalysis/Analyzers/*`

## Failure, Retry, Resume, and Drift Behavior
- Task failure policy implementation:
  - classify retryable vs non-retryable failures
  - auto-retry once for retryable execute failures
  - if retry still fails, pause session and require operator action
- Drift detection behavior:
  - detect post-snapshot repository drift before execute/resume boundaries
  - pause session and persist operator decision requirement (`continue_old_snapshot` vs `restart`)
  - emit deterministic event and prevent silent continuation
- Lifecycle control endpoints:
  - `pause`, `resume`, `retry-task`, `retry-session`, `restart-from-beginning`
- Transition safety:
  - invalid transitions rejected atomically with structured errors
  - concurrency-safe transition guards to prevent double-start/double-complete races
- Operator diagnostics:
  - persist concise `error_code`, `error_summary`, failed task metadata, last successful sequence

Impacted components/files:
- `app/Support/CodeAnalysis/SessionStateTransitionService.php`
- `app/Jobs/CodeAnalysis/ExecuteRepoAnalysisTaskJob.php`
- lifecycle controller actions and requests

## Coverage Gates, Reporting, Export, and Retention
- Coverage gate enforcement (`CoverageGateService`):
  - snapshot present and hashed
  - required analyzers completed for detected stack
  - required artifact classes present
  - no unresolved critical failures
  - report hash computable from ordered artifact hashes
  - no-test repo behavior: pass with explicit warning persisted and rendered
- Report composition (`ReportComposer`):
  - build deterministic section ordering from locked artifacts
  - include gate status, warnings, analyzer/version matrix, hash provenance
- Report persistence (`repo_analysis_reports`):
  - store structured report JSON + report hash + version metadata
- Export behavior (`ExportService`):
  - write to `docs/discovery/code-analysis/{slug}.md` and `.json`
  - collision-safe suffixing (`-v2`, `-v3`, ...)
  - enforce export directory policy and path safety
- Retention controls:
  - scheduled cleanup for task-level artifacts older than configured TTL (30 days)
  - final report exports retained indefinitely

Impacted components/files:
- `app/Support/CodeAnalysis/CoverageGateService.php`
- `app/Support/CodeAnalysis/ReportComposer.php`
- `app/Support/CodeAnalysis/ExportService.php`
- cleanup command/job and schedule registration
- `config/repo_analysis.php`

## User and Operator Surface Exposure (Routes, Pages, Navigation, Discoverability)
- Add UI pages:
  - `resources/js/Pages/Tools/CodeAnalysis/Index.vue`
  - `resources/js/Pages/Tools/CodeAnalysis/Create.vue`
  - `resources/js/Pages/Tools/CodeAnalysis/Wizard.vue`
  - `resources/js/Pages/Tools/CodeAnalysis/Settings.vue`
- Add UI components:
  - `resources/js/Components/CodeAnalysis/TaskGraphPanel.vue`
  - `resources/js/Components/CodeAnalysis/CoveragePanel.vue`
  - `resources/js/Components/CodeAnalysis/ReportViewer.vue`
  - `resources/js/Components/CodeAnalysis/ArtifactInspector.vue`
- Add route/page exposure:
  - web routes and Inertia route bindings for index/create/wizard/settings
  - API route links surfaced in UI actions
- Add navigation discoverability:
  - Tools landing includes Code Analysis entry card with permission-aware visibility
  - direct navigation from Tools index to create flow and existing sessions
  - wizard includes clear action controls for pause/resume/retry/restart/export and operator decision prompts
- Realtime UX:
  - subscribe to private channel and append in strict sequence
  - polling fallback with cursor when websocket unavailable
  - conflict handling prompts when server state supersedes stale client state
- In-app discoverability acceptance checks:
  - authorized user can find Code Analysis from Tools index without manual URL entry
  - owner can open session detail/wizard directly from list
  - admin override visibility and actions are clearly labeled
  - blocked actions show policy or state reason and suggested next action

Impacted components/files:
- `resources/js/Pages/Tools/CodeAnalysis/*`
- `resources/js/Components/CodeAnalysis/*`
- shared tools navigation component(s) and route definitions

## Observability and Auditability
- Structured logging:
  - session lifecycle logs with session id, phase/status, actor id, transition reason
  - task execution logs with analyzer key/version, hashes, attempt, failure class
- Metrics and health:
  - queue depth, task success/failure counts, pause rate, retry rate, gate failure counts
  - determinism regression pass/fail signal in CI outputs
- Horizon tagging:
  - tag jobs by `repo_analysis_session_id` for operator tracing
- Audit trail:
  - log lifecycle mutations and sensitive setting changes in immutable audit log model
- Operator diagnostics surface:
  - include last error code, failing task key, retry eligibility, drift state, and required decision actions

Impacted components/files:
- logging in services/jobs/controllers
- `config/horizon.php`
- audit integration with existing audit log infrastructure

## Test Strategy (Unit, Feature, Integration, Determinism, UI Discoverability)
- Unit tests:
  - transition guard matrix and atomic enforcement
  - canonical manifest generation and snapshot hash mtime exclusion
  - DAG determinism (stable keys/order/dependencies)
  - analyzer output normalization and hash generation
  - coverage gate evaluator including no-test warning path
  - report hash determinism from ordered artifact hashes
- Feature tests:
  - all API endpoints for session/lifecycle/read operations
  - authorization rules (owner-only + admin override)
  - active-session cap enforcement (Code Analysis-specific)
  - invalid transition rejection and standardized error responses
  - event ordering and `since_sequence` pagination
  - retry/pause/resume/restart behavior and drift decision gates
  - export collision suffixing and path policy enforcement
- Integration tests:
  - end-to-end fixture repositories (Laravel-only, Laravel+Vue, failure fixture)
  - queue-driven execution path with artifacts + reports persisted
- Determinism regression tests:
  - two runs on identical fixture snapshot + analyzer versions assert same report hash excluding timestamp fields
- UI/in-app discoverability acceptance tests:
  - Tools navigation entry visibility by role
  - create flow reachable from Tools index
  - operator actions exposed/hidden correctly per state and authorization

Impacted components/files:
- `tests/Unit/Support/CodeAnalysis/*`
- `tests/Feature/Api/V1/CodeAnalysis/*`
- `tests/Integration/CodeAnalysis/*`
- frontend/inertia tests under existing JS test structure

## Backward Compatibility
- Keep existing Requirements Discovery routes, models, jobs, and websocket channels unchanged.
- Do not overload interrogation tables; all Code Analysis data is isolated in new tables.
- Isolate queue routing via `code-analysis` queue; no changes to existing queue names required for old flows.
- Guard new config with defaults that do not alter current runtime behavior when Code Analysis is unused.
- Preserve existing policy behavior for non-Code Analysis resources.

## Rollout and Rollback Controls
- Rollout controls:
  - gate UI entry and lifecycle endpoints behind `repo_analysis.enabled` config flag
  - register queue supervisor settings but keep bounded concurrency and explicit analyzer enable list
  - enable analyzers through config-controlled registry entries
- Rollback controls:
  - disable feature flag to remove user entry points and lifecycle execution while preserving stored data
  - stop `code-analysis` queue workers/supervisor safely without affecting other queues
  - keep migrations additive; avoid destructive schema changes in initial release
  - maintain export files and report records for audit continuity even if tool is disabled
- Operational guardrails:
  - explicit command/runbook for artifact retention cleanup job
  - explicit runbook for recovering paused sessions and drift decisions

## Implementation Sequence and Dependency Order
1. Create `config/repo_analysis.php` and wire feature flag, defaults, policy values, analyzer registry config.
2. Add migrations for five new tables with indexes/constraints; add models with casts/relations.
3. Implement authorization policies, admin override logic, and Code Analysis active-session cap enforcement.
4. Implement `SessionStateTransitionService` and transition matrix tests.
5. Implement `EventWriter` with sequence generation, redaction, UTF-8 normalization, and broadcast integration.
6. Implement `SnapshotBuilder` with canonical traversal/serialization and snapshot hash policy (`mtime` excluded from hash input).
7. Implement `TaskGraphBuilder`, analyzer interface/registry, and initial analyzer classes.
8. Implement job pipeline (`GenerateRepoSnapshotJob`, `PlanRepoAnalysisTasksJob`, `ExecuteRepoAnalysisTaskJob`, `ValidateRepoAnalysisCoverageJob`, `GenerateRepoAnalysisReportJob`) with queue bindings and retry/pause semantics.
9. Implement coverage gates, deterministic report composer, and report persistence/hash derivation.
10. Implement export service with versioned collision-safe markdown/json outputs and path policy checks.
11. Implement drift detection and operator-decision flow for continue-old-snapshot vs restart.
12. Implement API controller/routes/requests/resources for CRUD, lifecycle, and read endpoints.
13. Implement wizard UI pages/components, tools navigation exposure, realtime subscription + polling fallback, and operator controls.
14. Implement audit logging and observability instrumentation (structured logs, metrics points, Horizon tagging).
15. Implement retention cleanup workflow for task artifacts and verify final export retention behavior.
16. Complete full test suite across unit/feature/integration/determinism/UI discoverability; verify acceptance criteria end-to-end.

## Completion Checklist (Acceptance Mapping)
- Wizard/API complete flow works from create to completed report with no manual shell orchestration.
- Transition service enforces valid atomic lifecycle transitions and rejects invalid mutations consistently.
- Snapshot and planning are deterministic and reproducible for fixed snapshot/profile/analyzer versions.
- Task execution stores full hash/version/runtime metadata and artifact references per task.
- Failure policy behaves as specified: one auto-retry for retryable task failures, then pause.
- Resume/restart/drift semantics match policy with explicit operator choice capture.
- Coverage gates block false completion and allow no-test completion only with explicit warning persisted in report.
- Report hash is derived from ordered artifact hashes and persisted with report package metadata.
- Exported markdown/json files are versioned and collision-safe in `docs/discovery/code-analysis`.
- Realtime event stream preserves sequence parity across websocket and polling consumers.
- Authorization and discoverability checks pass for owner-only defaults and admin overrides.
- Retention behavior enforces task artifact TTL and indefinite final report export availability.

## Sections

- Scope Boundary
- Architecture Changes
- Data Model and Migrations
- API and Tool Contracts
- Event Contracts and Realtime Delivery
- Authorization and Scope Enforcement
- Deterministic Snapshot, Planning, and Analyzer Contracts
- Failure, Retry, Resume, and Drift Behavior
- Coverage Gates, Reporting, Export, and Retention
- User and Operator Surface Exposure (Routes, Pages, Navigation, Discoverability)
- Observability and Auditability
- Test Strategy (Unit, Feature, Integration, Determinism, UI Discoverability)
- Backward Compatibility
- Rollout and Rollback Controls
- Implementation Sequence and Dependency Order
- Completion Checklist (Acceptance Mapping)


## Risks

- Nondeterministic analyzer output ordering (unordered maps, filesystem API differences, parser library behavior) can break reproducibility and report hash parity.
- Path traversal or symlink edge cases can bypass intended scope boundaries if `PathPolicy` checks are not applied at every filesystem touchpoint.
- Analyzer resource exhaustion (large files, deep trees, heavy graph construction) can stall workers without strict file-size, timeout, and memory guardrails.
- Drift detection false positives/negatives can lead to incorrect pause behavior or invalid reuse of stale task outputs.
- Auto-retry logic can mask deterministic failures if retryable classification is too broad or inconsistent across analyzers.
- Retention cleanup can delete artifacts still needed for troubleshooting if report/artifact linkage and TTL boundaries are not explicit.
- Secret redaction can over-redact (losing diagnostics) or under-redact (data exposure) without stable allow/deny patterns and tests.
- Owner/admin authorization drift between API and UI can expose controls users cannot execute or hide valid controls, reducing operability.
- Queue isolation misconfiguration can route code-analysis jobs to existing workers, causing contention or behavior coupling.
- Export collision handling bugs can overwrite prior reports or produce non-canonical version suffix increments.
- No-test coverage warning path can be implemented inconsistently, causing either false blocking or silent pass without required warning evidence.


## Assumptions

- Redis and Horizon are available and already used as operational dependencies in the target environment.
- Existing websocket/private channel infrastructure used by Requirements Discovery can be reused for Code Analysis events with minimal adaptation.
- `PathPolicy` supports the required absolute-path and base-directory validation semantics for code-analysis project directories and export paths.
- Repository scanning excludes (`vendor`, `node_modules`, `storage`, `bootstrap/cache`, `.git`) are acceptable defaults for all supported projects unless explicitly extended.
- Analyzer versioning is controlled in configuration/code and will be bumped intentionally when output-affecting behavior changes.
- Canonical JSON serialization utilities are available or can be implemented consistently in PHP for stable key ordering and hash input generation.
- Audit log infrastructure is available for lifecycle mutation recording without schema redesign.
- Frontend tools navigation has an existing extension point where Code Analysis entry and settings links can be added without redesigning global layout.
- Fixture repositories for integration and determinism tests can be added under test assets and executed in CI environments.
- Optional narrative synthesis provider dependencies may be absent by default; deterministic completion must remain fully functional when narrative is disabled.

