# Code Analysis Tool — Deterministic Implementation Plan

## Metadata
- Status: Draft
- Author: Codex
- Last Updated: 2026-03-02
- Primary Consumers: Platform Engineering, API/UI Engineering
- Related Systems:
  - `Requirements Discovery` wizard runtime
  - Interrogation queue and websocket event pipeline
  - Path/command policy enforcement

## Executive Summary
Build a new `Code Analysis` tool under `Tools` that reuses the existing wizard, queue, transition, and event-stream architecture, but executes a deterministic-first task graph over the full codebase. The system produces reproducible analysis artifacts and versioned reports, then optionally allows LLM-assisted narrative synthesis from locked artifacts.

The implementation should prioritize reproducibility, coverage guarantees, and operational safety over generative output speed.

## Background and Existing Leverage
Current capabilities that should be reused as-is:
- Atomic phase/status transitions (`SessionStateTransitionService` pattern).
- Sequenced append-only event stream (`InterrogationEventWriter` pattern + websocket broadcasts).
- Queue orchestration on dedicated connection/queue (`interrogation` queue).
- Wizard-style phase UX with polling + realtime subscription.
- Export workflow writing versioned markdown artifacts to `docs/`.
- Policy-driven path safety (`PathPolicy`) and command constraints.

Current gaps relative to requested code-analysis behavior:
- Discovery is runner-stream based and not deterministic/reproducible across runs.
- No deterministic task DAG for whole-code analysis coverage.
- No analysis artifact registry (manifest, graph, report components) with hash chaining.

## Goals
1. Deterministically analyze the full repository using task-based execution.
2. Produce durable, queryable analysis artifacts with strict reproducibility metadata.
3. Provide a wizard workflow that mirrors current discovery ergonomics.
4. Generate detailed reports and export them to versioned files.
5. Support resumable/retryable task execution with precise operator diagnostics.

## Non-Goals
1. Replacing existing Requirements Discovery flow.
2. Executing implementation/build actions from code analysis.
3. Introducing broad write access to repository files during analysis.
4. Dependence on an LLM to complete core analysis stages.

## User Workflow (Wizard)
Phase model for new tool (`Code Analysis`):

| Phase | Name | Purpose | Deterministic |
|---|---|---|---|
| 0 | Setup | repo path, include/exclude globs, analyzer profile | Yes |
| 1 | Snapshot | create canonical repo manifest + snapshot hash | Yes |
| 2 | Plan | generate task DAG from repo profile/analyzers | Yes |
| 3 | Execute | run analyzers in dependency order, emit live progress | Yes |
| 4 | Validate | enforce coverage and artifact completeness gates | Yes |
| 5 | Report | compose report package + optional narrative | Mostly |
| 6 | Complete | lock outputs, export, optional handoff to planning | Yes |

Notes:
- Optional narrative synthesis in phase 5 is strictly post-artifact and must never mutate deterministic source artifacts.
- If deterministic gates fail, phase 5 cannot proceed.

## Deterministic Contract
The system is considered deterministic only if all are true:
1. File traversal order is stable (sorted canonical relative paths).
2. Snapshot manifest format is canonical JSON (stable key order).
3. Every task output includes:
  - analyzer id/version
  - input hash
  - output hash
  - runtime metadata
4. Final report hash is derived from ordered artifact hashes.
5. Re-running with identical snapshot hash and analyzer versions yields identical report hash (except timestamps).
6. Coverage gates explicitly pass.

## Data Model
Introduce new dedicated models/tables (do not overload interrogation tables).

### 1) `repo_analysis_sessions`
Purpose: top-level analysis run identity and lifecycle.

Key columns:
- `id`
- `user_id` (FK)
- `name` (nullable)
- `project_directory` (absolute path)
- `status` (`setup|snapshotting|planning|executing|validating|reporting|completed|failed|paused`)
- `phase` (0-6)
- `profile` (json: analyzer profile/options)
- `snapshot_hash` (nullable string)
- `manifest_stats` (json)
- `report_json` (json, nullable summary payload)
- `metadata_json` (json)
- `error_code`, `error_summary`
- `started_at`, `finished_at`
- soft deletes + timestamps

### 2) `repo_analysis_events`
Purpose: append-only sequenced runtime event stream.

Key columns:
- `id`
- `repo_analysis_session_id` (FK)
- `sequence` (unique per session)
- `event_type`
- `payload` (json)
- `event_ts`
- timestamps

Event types:
- `phase_transition`
- `snapshot_progress`
- `task_queued`
- `task_started`
- `task_completed`
- `task_failed`
- `coverage_gate`
- `report_generated`
- `system`
- `error`

### 3) `repo_analysis_tasks`
Purpose: deterministic task DAG nodes.

Key columns:
- `id`
- `repo_analysis_session_id` (FK)
- `task_key` (stable identifier, unique per session)
- `title`
- `analyzer` (e.g. `fs.manifest`, `laravel.routes`, `js.import-graph`)
- `sequence` (stable display order)
- `depends_on` (json array of task keys)
- `status` (`pending|running|completed|failed|skipped`)
- `input_hash`
- `output_hash`
- `attempt_count`
- `artifact_ids` (json array)
- `error_summary`
- `metadata_json`
- `started_at`, `finished_at`
- timestamps

### 4) `repo_analysis_artifacts`
Purpose: normalized artifact storage and lookup.

Key columns:
- `id`
- `repo_analysis_session_id` (FK)
- `task_id` (FK nullable for session-level artifacts)
- `artifact_type` (manifest, graph, api-map, model-map, risk-map, report-section)
- `artifact_key` (stable key)
- `content_json` (json)
- `content_hash`
- `schema_version`
- `analyzer_version`
- `metadata_json`
- timestamps

### 5) `repo_analysis_reports`
Purpose: persisted report packages and exports.

Key columns:
- `id`
- `repo_analysis_session_id` (FK)
- `report_version`
- `report_hash`
- `report_json`
- `markdown_path` (nullable)
- `json_path` (nullable)
- `metadata_json`
- timestamps

## Backend Services and Class Scaffolding

### Core Session Lifecycle
- `app/Models/RepoAnalysisSession.php`
- `app/Models/RepoAnalysisEvent.php`
- `app/Models/RepoAnalysisTask.php`
- `app/Models/RepoAnalysisArtifact.php`
- `app/Models/RepoAnalysisReport.php`

- `app/Support/CodeAnalysis/SessionStateTransitionService.php`
  - Atomic status/phase transitions with allowed-from guards.

- `app/Support/CodeAnalysis/EventWriter.php`
  - Sequenced event append + broadcasts.
  - Payload redaction and utf8 normalization (mirror interrogation behavior).

### Snapshot and Planning
- `app/Support/CodeAnalysis/SnapshotBuilder.php`
  - Build canonical manifest:
    - relative path
    - type (file/dir/symlink)
    - size
    - mtime (optional)
    - sha256 for eligible files
  - Apply include/exclude filters deterministically.

- `app/Support/CodeAnalysis/TaskGraphBuilder.php`
  - Build DAG based on detected stack + configured analyzers.
  - Emit stable task keys and dependency list.

### Analyzer Execution
- `app/Support/CodeAnalysis/Analyzers/AnalyzerInterface.php`
  - `key()`, `version()`, `supports(sessionProfile)`, `run(context): AnalyzerResult`.

- `app/Support/CodeAnalysis/Analyzers/AnalyzerRegistry.php`
  - Returns deterministic ordered analyzer set by profile.

- Initial analyzers:
  - `FilesystemManifestAnalyzer`
  - `DependencyManifestAnalyzer` (composer/npm lock + package manifests)
  - `LaravelRoutesAnalyzer`
  - `LaravelModelsMigrationsAnalyzer`
  - `QueueJobsEventsAnalyzer`
  - `FrontendModuleGraphAnalyzer` (imports/pages/components)
  - `TestCoverageMapAnalyzer` (tests to domains/files mapping)
  - `RiskHotspotAnalyzer` (fan-in/out, churn proxy, orphaned modules)

### Coverage and Reporting
- `app/Support/CodeAnalysis/CoverageGateService.php`
  - Validates required artifacts and thresholds.

- `app/Support/CodeAnalysis/ReportComposer.php`
  - Assemble report sections from deterministic artifacts only.

- `app/Support/CodeAnalysis/ExportService.php`
  - Write:
    - `docs/discovery/code-analysis/{slug}.md`
    - `docs/discovery/code-analysis/{slug}.json`
  - Version suffix strategy on name conflict (`-v2`, `-v3`).

### Optional Narrative
- `app/Support/CodeAnalysis/NarrativeSynthesisService.php`
  - Optional, post-gate only.
  - Input is locked artifacts/report JSON.
  - Output saved as separate artifact section with provenance.

## Jobs and Queue Orchestration

### Jobs
- `GenerateRepoSnapshotJob`
- `PlanRepoAnalysisTasksJob`
- `ExecuteRepoAnalysisTaskJob`
- `ValidateRepoAnalysisCoverageJob`
- `GenerateRepoAnalysisReportJob`

Queue recommendations:
- New queue: `code-analysis` on redis.
- Dedicated Horizon supervisor (parallelism bounded and configurable).
- Keep `tries=1` by default, retries controlled explicitly per task state.

Execution behavior:
1. Phase transition to active state.
2. Emit phase transition event.
3. Run step with deterministic input set.
4. Persist artifacts and hash metadata.
5. Emit completion/failure events.
6. Dispatch next step.

## API Surface
Add under `/agent/api/v1/code-analysis/*`.

Session endpoints:
- `GET /sessions`
- `POST /sessions`
- `GET /sessions/{id}`
- `PATCH /sessions/{id}`
- `DELETE /sessions/{id}`
- `POST /sessions/{id}/restore`

Lifecycle endpoints:
- `POST /sessions/{id}/start-snapshot`
- `POST /sessions/{id}/plan-tasks`
- `POST /sessions/{id}/start-execution`
- `POST /sessions/{id}/retry-task/{taskId}`
- `POST /sessions/{id}/validate-coverage`
- `POST /sessions/{id}/generate-report`
- `POST /sessions/{id}/export-report`
- `POST /sessions/{id}/pause`
- `POST /sessions/{id}/resume`
- `POST /sessions/{id}/retry`
- `POST /sessions/{id}/restart-from-beginning`

Read endpoints:
- `GET /sessions/{id}/events`
- `GET /sessions/{id}/tasks`
- `GET /sessions/{id}/artifacts`
- `GET /sessions/{id}/reports`

Controller scaffolding:
- `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`

Request scaffolding:
- `StoreRepoAnalysisSessionRequest`
- `UpdateRepoAnalysisSessionRequest`
- `StartSnapshotRequest`
- `PlanTasksRequest`
- `RetryRepoAnalysisTaskRequest`

## Wizard UI Scaffolding
Add pages under:
- `resources/js/Pages/Tools/CodeAnalysis/Index.vue`
- `resources/js/Pages/Tools/CodeAnalysis/Create.vue`
- `resources/js/Pages/Tools/CodeAnalysis/Wizard.vue`
- `resources/js/Pages/Tools/CodeAnalysis/Settings.vue`

Add reusable components under:
- `resources/js/Components/CodeAnalysis/TaskGraphPanel.vue`
- `resources/js/Components/CodeAnalysis/CoveragePanel.vue`
- `resources/js/Components/CodeAnalysis/ReportViewer.vue`
- `resources/js/Components/CodeAnalysis/ArtifactInspector.vue`

Realtime behavior should mirror current wizard:
- subscribe to private channel
- append events in sequence
- periodic poll fallback
- server-sourced conflict handling

## Security and Policy
1. Enforce `PathPolicy` on `project_directory`.
2. Default excludes:
  - `vendor/`
  - `node_modules/`
  - `storage/`
  - `bootstrap/cache/`
  - `.git/`
3. File size cap for hash/parse (configurable).
4. Hard timeout and memory limits per analyzer task.
5. Secret redaction for event payloads and report excerpts.
6. Audit log all lifecycle mutations.

## Configuration
Add `config/repo_analysis.php`:
- enabled analyzers and versions
- include/exclude defaults
- max scanned files
- max file size bytes
- queue/supervisor defaults
- coverage thresholds
- export directory policy

User-level settings (similar to interrogation settings):
- max active sessions
- default analyzer profile
- optional narrative synthesis toggle

## Coverage Gates
Minimum required gates before completion:
1. Snapshot manifest exists and hash is present.
2. Required analyzer tasks completed for detected stack.
3. Artifact completeness:
  - dependency map
  - interface map (routes/APIs/screens)
  - data model map
  - execution/queue map
  - test mapping
4. No unresolved critical task failures.
5. Report hash generated from artifact hashes.

## Testing Strategy

### Unit
- transition service atomic guards
- snapshot canonicalization and hashing
- DAG builder deterministic ordering
- analyzer parser normalization
- coverage gate evaluator
- report hash determinism

### Feature
- session lifecycle endpoints
- phase/status transition conflicts
- event stream ordering and pagination
- retry/restart semantics
- export versioning behavior

### Integration
- end-to-end run on fixture repos:
  - laravel-only fixture
  - mixed laravel + vue fixture
  - failure fixture (broken config, parser errors)

### Determinism Regression
- same fixture + same config run twice:
  - assert same snapshot hash
  - assert same task outputs hashes
  - assert same report hash

## Rollout Plan

### Phase 1 (MVP)
- Data model + session/events/tasks/artifacts.
- Snapshot + DAG + 3 core analyzers.
- Basic report composer + export.
- Wizard skeleton with live events.

### Phase 2 (Hardening)
- Coverage gates and strict completion blocking.
- Retry/pause/resume, operator diagnostics.
- Determinism regression suite in CI.

### Phase 3 (Expansion)
- Additional analyzers (frontend graph, risk hotspots).
- Optional narrative synthesis from locked artifacts.
- Plan handoff integration from report output.

## Acceptance Criteria
1. A user can run code analysis from wizard setup to completed report without manual shell work.
2. Output includes deterministic artifacts and versioned markdown/json reports.
3. Task failures are visible with recoverable retry semantics.
4. Coverage gates prevent false-complete runs.
5. Two identical runs on the same snapshot produce identical report hash.

## Open Questions
1. Should code-analysis sessions share the same per-user active session limit as interrogation or have a dedicated limit?
2. Should narrative synthesis be enabled by default or opt-in only?
3. Should artifacts be retained forever or subject to retention policy by size/age?
