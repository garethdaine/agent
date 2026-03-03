# Requirements Discovery Summary

Session: 16

## Code Analysis Discovery Summary
A new `Code Analysis` tool will be added under Tools, reusing existing wizard UX, queue orchestration patterns, transition safety, and sequenced realtime event streaming from Requirements Discovery, but with a deterministic-first execution model over the full repository.

### Final Product Direction
- Deterministic analysis pipeline with phases: `Setup (0) -> Snapshot (1) -> Plan (2) -> Execute (3) -> Validate (4) -> Report (5) -> Complete (6)`.
- Core analysis must be LLM-independent; optional narrative is post-gate only and cannot mutate deterministic artifacts.
- Outputs are durable artifacts plus versioned report exports at `docs/discovery/code-analysis/{slug}.md` and `.json`.

### Confirmed Policy Decisions (Resolved)
- Per-user limits: dedicated Code Analysis limit, separate from Requirements Discovery.
- Per-user active session cap: `2` active Code Analysis sessions.
- Access control default: owner-only read/write; admins can override.
- Narrative synthesis default: opt-in only (`default OFF`).
- Task failure behavior in Execute: auto-retry once for retryable errors, then pause for operator action.
- Resume behavior: reuse completed task outputs only when `input_hash` and `analyzer_version` still match; otherwise rerun.
- Snapshot drift behavior: if repo changes after snapshot, pause and require explicit operator choice to continue on old snapshot or restart.
- Snapshot hash policy: store `mtime` in manifest metadata, exclude `mtime` from `snapshot_hash`.
- No-tests coverage policy: allow completion when test mapping is empty, but emit warning in report.
- Artifact retention: delete task-level artifacts after 30 days; keep final markdown/json reports indefinitely.

### Data Model (Dedicated Tables)
- `repo_analysis_sessions`: run identity, phase/status lifecycle, profile, snapshot hash, manifest stats, report summary, error fields, metadata.
- `repo_analysis_events`: append-only sequenced events per session (`sequence` unique per session), typed payload stream.
- `repo_analysis_tasks`: deterministic DAG nodes with stable `task_key`, dependencies, hashes, attempts, status.
- `repo_analysis_artifacts`: normalized artifact registry with `artifact_type`, `artifact_key`, `content_hash`, schema/analyzer version metadata.
- `repo_analysis_reports`: persisted report package with `report_hash`, structured payload, export paths.

### Core Services and Responsibilities
- `SessionStateTransitionService`: atomic allowed-from phase/status transitions.
- `EventWriter`: sequenced append-only writes + websocket broadcast + redaction/UTF-8 normalization.
- `SnapshotBuilder`: canonical manifest generation from deterministic traversal.
- `TaskGraphBuilder`: deterministic DAG construction with stable keys/order.
- `AnalyzerInterface` + `AnalyzerRegistry`: versioned analyzers, deterministic ordered selection by profile.
- `CoverageGateService`: required-artifact and critical-failure gate enforcement.
- `ReportComposer`: deterministic report assembly from locked artifacts.
- `ExportService`: versioned `.md`/`.json` exports with collision suffixing.
- `NarrativeSynthesisService` (optional): post-gate narrative artifact with provenance only.

### Initial Analyzer Set
- `FilesystemManifestAnalyzer`
- `DependencyManifestAnalyzer`
- `LaravelRoutesAnalyzer`
- `LaravelModelsMigrationsAnalyzer`
- `QueueJobsEventsAnalyzer`
- `FrontendModuleGraphAnalyzer`
- `TestCoverageMapAnalyzer`
- `RiskHotspotAnalyzer`

### Queue and Runtime Model
- Queue: dedicated `code-analysis` queue on Redis with bounded Horizon concurrency.
- Jobs:
  - `GenerateRepoSnapshotJob`
  - `PlanRepoAnalysisTasksJob`
  - `ExecuteRepoAnalysisTaskJob`
  - `ValidateRepoAnalysisCoverageJob`
  - `GenerateRepoAnalysisReportJob`
- Retry semantics are task-policy driven (`auto-retry once`, then pause) rather than broad queue retries.

### API and UI Scope
- API namespace: `/agent/api/v1/code-analysis/*`.
- Session CRUD + lifecycle endpoints for snapshot, planning, execution, retry task, coverage validation, report generation/export, pause/resume/retry/restart.
- Read endpoints for events/tasks/artifacts/reports.
- UI pages: `Index`, `Create`, `Wizard`, `Settings` under `resources/js/Pages/Tools/CodeAnalysis/`.
- UI components: task graph, coverage panel, report viewer, artifact inspector.
- Realtime pattern mirrors existing wizard: private channel subscription + ordered event append + polling fallback.

### Security and Operational Constraints
- Enforce `PathPolicy` for project directory.
- Default deterministic excludes: `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `.git/`.
- Analyzer file-size caps, timeouts, and memory limits required.
- Event/report payload secret redaction required.
- Lifecycle mutations must be audit-logged.
- Analysis is read-focused on repo contents; writes are limited to system records/artifacts and explicit report exports.

### Configuration Surface (`config/repo_analysis.php`)
- Enabled analyzers + versions.
- Include/exclude defaults.
- Scan guardrails: max files, max file size bytes.
- Queue/supervisor defaults for `code-analysis`.
- Coverage thresholds and required artifact classes.
- Export directory policy.
- User settings including:
  - `max_active_sessions_per_user = 2` (Code Analysis only)
  - default analyzer profile
  - `narrative_synthesis_default = false`
  - retention settings (task artifacts 30 days, final reports retained indefinitely).

### Determinism and Completion Gates
- Canonical sorted file traversal and canonical JSON manifests.
- Every task must persist analyzer/version + input/output hashes + runtime metadata.
- Final report hash derived from ordered artifact hashes.
- Determinism regression requirement: identical snapshot + analyzer versions => identical report hash (excluding timestamps).
- Completion blocked unless snapshot exists, required analyzers complete for detected stack, required artifact classes exist, no unresolved critical failures, and report hash is generated.
- Repositories with no tests may complete with explicit warning when test mapping is empty.

## Goals

- Implement a deterministic full-repository analysis workflow that is reproducible across reruns with identical inputs.
- Introduce dedicated Code Analysis persistence models for sessions, events, tasks, artifacts, and reports without overloading existing interrogation tables.
- Reuse proven orchestration patterns from Requirements Discovery for atomic transitions, sequenced events, queue execution, and realtime wizard UX.
- Produce durable, hash-addressable artifacts and versioned markdown/json report exports suitable for downstream planning and audit.
- Provide robust operator controls for pause, resume, retry task, retry session, and restart-from-beginning with clear diagnostics.
- Enforce safety and policy controls for path access, exclusions, secret redaction, resource limits, and lifecycle auditing.
- Support deterministic resume behavior by reusing outputs only when input and analyzer identity are unchanged.
- Allow optional narrative synthesis only as a non-blocking post-processing step over locked deterministic artifacts.


## Constraints

- Do not replace or regress existing Requirements Discovery behavior; Code Analysis is a separate tool path.
- Core analysis stages must not depend on LLM output for correctness or completion.
- Use a dedicated per-user active-session policy for Code Analysis with a cap of two active sessions per user.
- Access defaults must be owner-only for sessions/artifacts/reports, with admin override capabilities.
- Task-level artifacts must be retained for 30 days, while final exported markdown/json reports are retained indefinitely.
- On retryable Execute-phase task failure, auto-retry once and then pause the session if failure persists.
- On resume, completed task outputs are reusable only if both input_hash and analyzer_version still match current execution context.
- If repository drift is detected after snapshot creation, pause and require explicit operator choice to continue old snapshot or restart.
- Snapshot manifests may store mtime metadata, but snapshot hashing must exclude mtime values.
- If no tests exist and test mapping is empty, coverage gate may pass only with an explicit warning captured in the report.
- Deterministic defaults must exclude vendor, node_modules, storage, bootstrap/cache, and .git from analysis scope.
- All lifecycle mutations must be auditable and event/report payloads must apply secret redaction and UTF-8 normalization.


## Acceptance Criteria

- A user can create, run, monitor, and complete a Code Analysis session through the wizard and API without manual shell orchestration.
- Session lifecycle enforces valid atomic phase/status transitions and rejects invalid transition attempts with consistent error handling.
- Snapshot generation is deterministic with canonical traversal/order and stable snapshot_hash behavior that excludes mtime from hash input.
- Task planning creates a deterministic DAG with stable task keys, explicit dependencies, and reproducible ordering for a fixed snapshot/profile.
- Analyzer executions persist input_hash, output_hash, analyzer version, runtime metadata, and artifact references for every task.
- Execute-phase task failures follow the default policy: one automatic retry for retryable errors, then session pause for operator action.
- Resume/restart semantics are correct: reusable outputs are kept only when input_hash and analyzer_version match; otherwise affected tasks rerun.
- Drift detection pauses the session and records an operator decision path before continuation on old snapshot or restart.
- Coverage validation blocks completion when required artifacts or critical tasks are missing, except no-test repositories which complete with report warning.
- Report generation computes a report hash from ordered artifact hashes and persists both report package metadata and export paths.
- Exports are written as versioned markdown and json files under docs/discovery/code-analysis with collision-safe suffixing.
- Realtime event delivery preserves per-session sequence ordering and remains consumable by both websocket subscribers and polling fallback.
- Determinism regression checks pass: two runs with identical snapshot hash and analyzer versions yield identical report hash aside from timestamp fields.
- Retention behavior is enforced: task-level artifacts are eligible for deletion after 30 days while final report exports remain available indefinitely.
- Authorization enforces owner-only defaults for code-analysis resources with admin override support.

