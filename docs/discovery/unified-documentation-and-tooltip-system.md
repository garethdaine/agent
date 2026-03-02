# Requirements Discovery Summary

Session: 15

## Unified Documentation and Tooltip System — Discovery Summary

### Final architecture and source of truth
- Adopt a **repo-first documentation platform** with runtime ingestion:
  - Repository files are the only authoritative source for product docs, tooltip fragments, and API narrative docs.
  - Runtime app reads ingested records (DB/runtime models), not raw files during requests.
  - Conflict policy is strict: repo content always wins for identical `slug` or `ui_key`.
- Keep **Scramble** as the OpenAPI generation mechanism.
- Treat API docs and human docs as separate domains under one system, with cross-links.
- Preserve optional static export as a later adapter; it is not the authoritative source.

### Authoring, publish, and visibility model
- **No in-app editing** for docs or tooltips in this rollout.
- Publish/update signal is **commit-driven on main** (trunk-based flow): docs/tooltips are auto-synced after commits.
- Visibility is **private internal only**: authenticated users only, enforced by existing app auth/roles.
- Scope is **global canonical content** for all teams (no per-team overrides in Phase 1).
- Versioning is **single live latest only**:
  - No historical user-visible versions.
  - Publish overwrites prior live state.

### Repository content contract (required)
- Product and API narrative docs: **Markdown with YAML front matter**.
- Tooltip/helper content: **separate YAML fragment files** keyed by `ui_key`.
- Required metadata model includes canonical fields such as `slug`, `title`, `summary`, `section`, `audience`, `status`, `version`, `tags`, `owner`, plus route/setting linkage fields.

### Runtime entities and service boundaries
- Runtime entities:
  - `DocumentationEntry` (long-form product/API narrative)
  - `DocumentationFragment` (tooltip/helper microcopy)
  - `DocumentationLink` (route/setting/feature-flag associations)
  - `ApiDocArtifact` (OpenAPI artifact metadata + deep links)
- Recommended services:
  - `DocsSyncService` (orchestration of parse/validate/upsert/reindex)
  - `DocsIngestionPipeline` (file loaders + schema validators)
  - `TooltipRegistryService` (`ui_key` resolution)
  - `DocsSearchService` (Scout search and route-affinity ranking)
  - `CoverageAuditService` (coverage computation and CI output)
  - `DocsTelemetryService` (missing key/search outage instrumentation)

### Search contract (Scout + Typesense)
- **Typesense is mandatory in all environments** (local/staging/production).
- No Scout fallback engine for docs search.
- Indexed domains: product docs, tooltip fragments, API docs metadata/narratives.
- Indexed fields include `domain`, `title`, `summary`, `body`, `tags`, `section`, `route_names`, `setting_keys`, `updated_at`.
- Freshness policy: publish immediately and reindex asynchronously with **<=60s** target freshness.
- Runtime outage UX: no fallback results; show graceful **"search temporarily unavailable"** state with retry guidance.

### Tooltip runtime behavior
- `HelpHint` resolves content by stable `ui_key`.
- Missing `ui_key` behavior:
  - User-facing: silent fallback (render no tooltip/helper).
  - System-facing: emit telemetry/logs for missing key.
- Fragment schema supports short text, optional long text/"learn more" link, severity (`info|warning|risk`), and feature-flag metadata.

### Automation, hooks, and enforcement
- One shared docs-sync pipeline/script is invoked by both:
  - Claude/Codex tooling hook path
  - Git `pre-commit` hook path
- If artifacts are stale at commit time: auto-regenerate and auto-stage, then allow commit.
- If sync fails at commit time: do not block commit; emit alerts/issues/telemetry.
- Deploy-time enforcement for Typesense reindex failure after successful upsert:
  - Retry policy: **3 retries**, **30s** interval, **2m** total timeout window.
  - If still failing after bounded retries: fail deployment.

### Localization posture
- Content is English-only in this release.
- Data model must be locale-ready now (schema/keys/fallback-ready for future translation).

### Coverage scope and quality gate
- Required documentation coverage includes: Dashboard, Agent Jobs, Monitor, Messenger control plane, Requirements Discovery, Backups, Feature Flags, Memory diagnostics, Delegation, Org layer, Profile/Security/Account, API/token/integration flows.
- CI coverage threshold is **100%** across defined surfaces.
- Strict pass rule for a documented surface:
  - Published overview
  - Configuration/setting details
  - At least one concrete example
  - Troubleshooting guidance
  - Linked tooltip `ui_key` entries where applicable

## Goals

- Deliver complete, searchable human-readable documentation for all user-facing app areas and authenticated API workflows.
- Provide consistent contextual helper text/tooltips tied to stable `ui_key` identifiers across non-trivial controls and states.
- Maintain API documentation quality via Scramble-generated OpenAPI plus endpoint narratives linked to product workflows.
- Enforce automated, repo-driven documentation synchronization with no in-app editing and minimal manual maintenance.
- Guarantee high-quality discovery outcomes via strict coverage and operational acceptance gates.


## Constraints

- Documentation and tooltip content are private internal assets visible only to authenticated users under existing app auth/role controls.
- No in-app authoring/editing is allowed; all content changes originate from repository files and automated sync.
- Versioning is latest-only: single live version, publish overwrites prior state, no user-facing version selector.
- Search backend is mandatory Typesense in local/staging/production; no Scout fallback engine for docs search.
- Repository contract is fixed to Markdown with YAML front matter for product/API narratives and YAML files for tooltip fragments.
- Tenant scoping is global canonical content only; no per-team or per-org content overrides in this phase.
- Missing `ui_key` at runtime must silently render nothing for users while logging/telemetry records the miss.
- Commit-time sync failures must not block commits; stale artifacts should auto-regenerate and auto-stage before commit proceeds.
- Deployment must fail if Typesense reindex remains unsuccessful after bounded retries (3 retries, 30s interval, 2m total timeout).
- Localization scope is English-only content now with locale-ready schema and fallback-capable model design.


## Acceptance Criteria

- Repository contains complete docs/tooltips in the approved file contract and ingestion validates schema without errors.
- Docs sync pipeline ingests repository sources, upserts runtime records, and app resolves docs/tooltips from runtime storage only.
- Scramble-generated OpenAPI artifacts are ingested and cross-linked to human/product guidance in the unified docs experience.
- Scout search uses Typesense in every environment and returns domain-aware results with route affinity metadata.
- Published content becomes searchable with asynchronous reindex freshness target of <=60s under normal operation.
- When Typesense is unavailable at runtime, UI shows a graceful "search temporarily unavailable" state with retry guidance and no fallback results.
- `HelpHint` resolves by `ui_key`; missing keys produce silent UI fallback plus telemetry/log emission.
- Coverage audit enforces 100% scope compliance for screens/settings/authenticated API endpoints using strict documentation criteria.
- Commit automation invokes shared sync pipeline from both Claude/Codex hook path and git pre-commit path, auto-regenerating and auto-staging stale artifacts.
- If commit-time sync fails, alerts/issues/telemetry are emitted and commit is allowed; if deploy-time reindex fails after bounded retry window, deployment fails.

