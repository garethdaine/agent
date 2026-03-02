# Implementation Plan

Derived from discovery session 15.

# Unified Documentation and Tooltip System — Implementation Plan

## 1) Scope Boundary
- Implement a **repo-first, runtime-ingested** documentation platform covering three domains: `product_doc`, `tooltip`, `api_doc`.
- Enforce **read-only in-app consumption** (no in-app authoring/editing endpoints or admin forms in this rollout).
- Keep visibility **private/internal only** through existing authenticated app access; no public docs routes.
- Support **latest-only live state**; publish overwrites previous runtime content, with revision metadata retained for audit only.
- Include required coverage surfaces: Dashboard, Agent Jobs, Monitor, Messenger, Requirements Discovery, Backups, Feature Flags, Memory diagnostics, Delegation, Org layer, Profile/Security/Account, API/token/integration flows.
- Out of scope: public portal, localization content authoring, per-team overrides, external static docs as source of truth.
- Impacted files/components:
  - `docs/product/**`, `docs/api/**`, `docs/tooltips/**`
  - `config/documentation.php` (new)
  - `config/docs_coverage.php` (new)

## 2) Architecture Changes
- Add a new documentation domain under `app/Support/Documentation` with clear service boundaries:
  - `DocsSyncService` for orchestration.
  - `DocsIngestionPipeline` for parse/validate/load.
  - `TooltipRegistryService` for `ui_key` resolution.
  - `DocsSearchService` for Scout + route-affinity boosting.
  - `CoverageAuditService` for coverage policy and CI artifacts.
  - `DocsTelemetryService` for missing key and search outage signals.
- Runtime read path uses DB/runtime models only; request handlers never parse raw repo files.
- Preserve Scramble as API spec generator; ingest spec metadata into `ApiDocArtifact` records.
- Add route-aware docs center and shared UI hint component integration.
- Impacted files/components:
  - `app/Support/Documentation/*.php` (new)
  - `app/Providers/AppServiceProvider.php` (service bindings)
  - `app/Console/Commands/DocsSyncCommand.php` (new)

## 3) Data Model and Migrations
- Create runtime entities and schema:
  - `DocumentationEntry`: `domain`, `slug`, `title`, `summary`, `section`, `audience`, `status`, `version`, `tags`, `owner`, `locale`, `body_markdown`, `body_html`, `source_path`, `source_checksum`, `source_commit`, `published_at`, `last_reviewed_at`.
  - `DocumentationFragment`: `ui_key`, `short_text`, `long_text`, `learn_more_slug`, `severity`, `feature_flag`, `status`, `locale`, `route_names`, `setting_keys`, `source_path`, `source_checksum`, `published_at`.
  - `DocumentationLink`: normalized associations to `route_name`, `setting_key`, `feature_flag`, plus polymorphic linkage to entry/fragment.
  - `ApiDocArtifact`: `operation_id`, `http_method`, `path`, `summary`, `description`, `tags`, `spec_version`, `spec_checksum`, `linked_doc_slugs`, `published_at`.
- Enforce constraints:
  - Unique `slug` within `domain+locale`.
  - Unique `ui_key` within `locale`.
  - Check constraints for `severity in (info,warning,risk)` and supported domains.
  - Foreign key integrity for cross-links.
- Include indexes for search/filter access paths: `domain`, `section`, `updated_at`, `route_names`, `setting_keys`.
- Impacted files/components:
  - `database/migrations/*_create_documentation_entries_table.php`
  - `database/migrations/*_create_documentation_fragments_table.php`
  - `database/migrations/*_create_documentation_links_table.php`
  - `database/migrations/*_create_api_doc_artifacts_table.php`
  - `app/Models/DocumentationEntry.php`
  - `app/Models/DocumentationFragment.php`
  - `app/Models/DocumentationLink.php`
  - `app/Models/ApiDocArtifact.php`

## 4) Repository Content Contract and Ingestion Pipeline
- Define repository source-of-truth layout:
  - `docs/product/<area>/<slug>.md` (Markdown + YAML front matter)
  - `docs/api/<workflow>/<slug>.md` (Markdown + YAML front matter)
  - `docs/tooltips/<area>.yaml` (fragment arrays keyed by `ui_key`)
- Enforce required front matter fields: `slug`, `title`, `summary`, `section`, `audience`, `status`, `version`, `tags`, `owner`, linkage fields (`route_names`, `setting_keys`, `feature_flags`).
- Implement strict conflict policy: same `slug` or `ui_key` always overwritten by repo content during sync.
- Build ingestion stages:
  1. File discovery and schema validation.
  2. Normalization and checksum generation.
  3. Upsert runtime entities.
  4. Link resolution and referential validation.
  5. Asynchronous search reindex trigger.
  6. Coverage audit snapshot generation.
- Add sync artifacts file for deterministic tracking (for auto-stage behavior), e.g. `storage/app/docs-sync/manifest.json` or committed `docs/.generated/manifest.json` as decided by repo policy.
- Impacted files/components:
  - `app/Support/Documentation/Ingestion/*.php`
  - `app/Support/Documentation/Schemas/*.php`
  - `app/Console/Commands/DocsValidateCommand.php` (new)
  - `app/Console/Commands/DocsCoverageCommand.php` (new)
  - `docs/README.md` (contract docs)

## 5) API and Tool Contracts
- Add read-only app/API contracts:
  - `GET /docs` (Inertia docs center shell).
  - `GET /docs/{slug}` (entry detail route).
  - `GET /agent/api/v1/docs/search?q=&domain=&section=&route=` returns title, snippet, domain, section, route affinity, updated timestamp.
  - `GET /agent/api/v1/docs/fragments/{uiKey}` returns tooltip payload for fallback fetch.
  - `GET /agent/api/v1/docs/coverage` for operator-facing coverage dashboards (auth-restricted).
- Add CLI/tool contracts:
  - `php artisan docs:sync --mode=commit|deploy --source=repo`
  - `php artisan docs:validate`
  - `php artisan docs:coverage --fail-on-missing`
  - `php artisan docs:openapi:ingest` (Scramble artifact import)
- Document machine-readable response schema for search and fragments to avoid frontend drift.
- Impacted files/components:
  - `routes/web.php`
  - `routes/api.php`
  - `app/Http/Controllers/Docs/*.php`
  - `app/Http/Requests/Docs/*.php`

## 6) Event Contracts
- Define internal domain events with explicit payload contracts:
  - `DocsSyncStarted` `{mode, source_commit, initiated_by}`
  - `DocsSyncCompleted` `{mode, upsert_counts, validation_errors, coverage_snapshot_id}`
  - `DocsReindexRequested` `{entity_types, changed_ids, reason}`
  - `DocsReindexCompleted` `{duration_ms, indexed_counts}`
  - `DocsReindexFailed` `{attempt, reason, recoverable}`
  - `TooltipKeyMissingDetected` `{ui_key, route_name, component_name, actor_id}`
  - `DocsSearchUnavailableDetected` `{route_name, query_hash, error_class}`
- Use queued listeners for reindex and telemetry emission; keep user request path non-blocking.
- Persist audit events for content sync and publish state transitions.
- Impacted files/components:
  - `app/Events/Documentation/*.php`
  - `app/Listeners/Documentation/*.php`
  - `app/Jobs/Documentation/*.php`

## 7) Authorization and Scope Enforcement
- Enforce auth gates for docs reading and operator endpoints:
  - Viewer: can access docs center/search/fragment APIs.
  - Publisher/Operator-level capability: can access coverage and sync status endpoints.
- Keep content private/internal through middleware and policy checks; reject unauthenticated access.
- Disable in-app write paths entirely by omission; no create/update/delete HTTP routes for docs domain.
- Ensure API docs exposure follows authenticated API visibility rules.
- Impacted files/components:
  - `app/Policies/Documentation*.php`
  - `app/Http/Middleware/*` (reuse existing auth/role middleware)
  - `routes/web.php`, `routes/api.php` (middleware groups)

## 8) Failure and Retry Behavior
- Commit-time behavior:
  - Shared sync script runs in commit mode from both Claude/Codex hook path and git `pre-commit`.
  - If docs artifacts stale: regenerate + auto-stage updated artifacts.
  - If sync fails: emit telemetry/alert artifact and return success code to allow commit.
- Deploy-time behavior:
  - Upsert success + reindex attempts with bounded retries (3 attempts, 30s interval, 2m timeout ceiling).
  - If reindex still failing after retry policy: deployment step exits non-zero.
- Runtime search outage behavior:
  - Do not provide fallback search results.
  - UI presents deterministic "search temporarily unavailable" state with retry action.
- Missing tooltip key behavior:
  - UI renders no hint text.
  - Backend/frontend telemetry logs structured miss event.
- Impacted files/components:
  - `scripts/docs/sync.sh` (shared)
  - `.githooks/pre-commit`
  - `.claude/hooks/pre-commit` or equivalent configured hook entrypoint
  - CI/deploy workflow files under `.github/workflows/*.yml`

## 9) Observability and Auditing
- Add structured logging channel for documentation subsystem: sync runs, validation failures, missing keys, search outages.
- Add metrics counters/gauges:
  - `docs_sync_success_total`, `docs_sync_failure_total`
  - `docs_reindex_latency_ms`, `docs_reindex_failure_total`
  - `tooltip_missing_key_total`
  - `docs_search_unavailable_total`
  - `docs_coverage_percent_by_surface`
- Emit audit records for sync and publish transitions (aligned with existing audit model conventions).
- Provide operator-facing diagnostics endpoint/page for current index health and recent failures.
- Impacted files/components:
  - `config/logging.php` (docs channel)
  - `app/Support/Documentation/DocsTelemetryService.php`
  - `app/Http/Controllers/Docs/DiagnosticsController.php`

## 10) User/Operator Surface Exposure (Routes/Pages/Navigation/Discoverability)
- Introduce docs center in primary authenticated navigation:
  - Sidebar/top-nav `Docs` entry linking to `/docs`.
  - Route-level breadcrumbs and section filters for discoverability.
- Build docs pages:
  - `resources/js/Pages/Docs/Index.vue` search + filters + domain tabs.
  - `resources/js/Pages/Docs/Show.vue` detail with related links and troubleshooting anchors.
- Add reusable `HelpHint` component:
  - `resources/js/Components/HelpHint.vue` with hover/focus, keyboard dismissal, ARIA labels, mobile tap fallback.
  - Supports short text, optional long text, severity styling, and learn-more deep link.
- Integrate `HelpHint` into critical user surfaces:
  - Jobs, Monitor, Discovery, Messenger, Memory, Feature Flags, Backups, Delegation, Org, Profile/Security/Account, API integration/token screens.
- Discoverability acceptance checks (must pass):
  - Docs nav item visible for authenticated viewers.
  - Each required surface exposes at least one visible docs entry point (top-level page link, section help icon, or field helper).
  - Contextual docs result boosting works when searching from a route-linked screen.
  - Tooltip interaction works with keyboard-only navigation and mobile tap flow.
- Impacted files/components:
  - `resources/js/Layouts/**/*.vue` (nav exposure)
  - `resources/js/Pages/**` for listed surfaces
  - `resources/js/Components/HelpHint.vue`

## 11) Test Strategy (Unit / Feature / Integration)
- Unit tests:
  - Front matter parser and YAML tooltip schema validation.
  - Repo conflict resolution (`slug`/`ui_key` overwrite behavior).
  - Route-affinity ranking and search payload shaping.
  - Missing key telemetry emission logic.
- Feature tests:
  - Auth-protected docs routes and APIs.
  - Docs center page rendering and discoverability markers.
  - Search unavailable UX contract response.
  - Coverage command fail/pass behavior against required scope map.
- Integration tests:
  - Scout + Typesense indexing/reindex workflows.
  - Scramble artifact ingestion and API narrative cross-link persistence.
  - Commit-mode sync script behavior (auto-stage on stale, non-block on failure).
  - Deploy-mode reindex strict failure path after bounded retries.
- Accessibility/UI tests:
  - Keyboard focus/dismiss for tooltip.
  - ARIA attributes and mobile fallback interactions.
- Impacted files/components:
  - `tests/Unit/Documentation/*.php`
  - `tests/Feature/Documentation/*.php`
  - `tests/Integration/Documentation/*.php`
  - Frontend component tests under `resources/js/**/__tests__/*` if existing test harness supports them.

## 12) Backward Compatibility
- Keep existing inline helper strings active until each surface is migrated to `HelpHint`; then remove duplicates deliberately.
- Maintain existing API docs consumers by preserving Scramble output path/format while adding ingestion layer.
- Provide compatibility mapping for legacy route names or setting keys where docs links are introduced.
- Ensure no breaking changes to current authenticated navigation beyond adding Docs entry.
- Impacted files/components:
  - Existing surface Vue pages/components with inline helper text
  - `docs/mappings/legacy-keys.yaml` (if needed)

## 13) Rollout and Rollback Controls
- Rollout controls:
  - Feature flags: `docs_center_enabled`, `help_hint_enabled`, `docs_search_enabled`, `docs_coverage_gate_enabled`.
  - Gradual enablement by route group while maintaining private auth boundary.
- Rollback controls:
  - Disable feature flags to hide docs surfaces instantly.
  - Re-run `docs:sync --mode=deploy --source=repo` against last known good commit snapshot.
  - Keep deterministic source checksums to verify runtime state against repo revision.
- Deployment gates:
  - Block deployment only when strict reindex policy fails after bounded retries.
  - Commit path remains non-blocking on sync errors by requirement.
- Impacted files/components:
  - `config/agent.php` or `config/documentation.php` flag definitions
  - deployment workflow files and release scripts

## 14) Execution Order and Dependency Gates
1. **Define contracts first**: create `config/documentation.php`, repository schema docs, and coverage map (`config/docs_coverage.php`).
2. **Add data layer**: migrations/models with constraints and indexes.
3. **Build ingestion + sync services**: parse/validate/upsert/link + commands.
4. **Wire search**: mandatory Typesense Scout config, indexing jobs, route-affinity ranking.
5. **Ingest Scramble artifacts**: OpenAPI metadata + link model.
6. **Expose read APIs and docs center pages**: routes/controllers/Inertia pages.
7. **Implement `HelpHint` and integrate into required surfaces**.
8. **Add telemetry, diagnostics, and audit logging**.
9. **Add commit/deploy automation scripts and hooks**.
10. **Enable CI gates**: validation, coverage 100%, link checks, integration tests.
11. **Run acceptance checklist** and enable feature flags in production sequence.

## 15) Acceptance and Done Gates
- Repository contract validation passes without schema/linkage errors.
- Runtime reads all docs/tooltips from DB/runtime entities only.
- Docs center is visible in authenticated navigation and reachable via `/docs`.
- Each required surface includes discoverable docs entry points and applicable tooltip keys.
- Search returns domain-aware payload with route affinity and honors Typesense-only backend.
- Search index freshness target (<=60s) validated under normal async reindex conditions.
- Search outage UX verified to show temporary-unavailable state without fallback results.
- Missing `ui_key` produces silent UI behavior and structured telemetry event.
- Commit hook paths invoke shared sync pipeline; stale artifacts auto-stage; sync failures emit alerts but do not block commit.
- Deploy fails when reindex remains failed after bounded retry policy.
- Coverage audit reports 100% pass for required screens/settings/authenticated API workflows using strict criteria.

## Sections

- Scope Boundary
- Architecture Changes
- Data Model and Migrations
- Repository Content Contract and Ingestion Pipeline
- API and Tool Contracts
- Event Contracts
- Authorization and Scope Enforcement
- Failure and Retry Behavior
- Observability and Auditing
- User/Operator Surface Exposure (Routes/Pages/Navigation/Discoverability)
- Test Strategy (Unit / Feature / Integration)
- Backward Compatibility
- Rollout and Rollback Controls
- Execution Order and Dependency Gates
- Acceptance and Done Gates


## Risks

- Documentation source files drift from evolving route names, setting keys, and feature flags, creating stale cross-links despite successful sync.
- Typesense availability or schema mismatch causes search unavailability and blocks deploy under strict reindex policy.
- Hook installation inconsistency across developer environments reduces commit-time sync reliability and artifact consistency.
- Route-affinity boosting introduces relevance bias if linkage metadata quality is poor or incomplete.
- Strict 100% coverage gate can fail frequently if coverage inventory is not maintained with every new screen/control/API addition.
- Tooltip `ui_key` naming inconsistency across frontend components increases missing-key telemetry noise and weakens discoverability.
- OpenAPI operation IDs or paths can change during backend refactors, breaking API narrative cross-links if mappings are not regenerated atomically.
- Non-blocking commit behavior on sync failure can permit temporary runtime/repo divergence until deploy-time enforcement catches failures.
- Read-only docs model may create operational bottlenecks if ownership and review workflows are not enforced in repository practices.
- Accessibility regressions in tooltip interactions can reduce usability if keyboard/mobile behavior is not tested on all integrated surfaces.


## Assumptions

- Current Laravel 12 stack can support additional documentation models, commands, queue jobs, and Scout indexing without structural blockers.
- Typesense infrastructure is provisioned and reachable in local, staging, and production environments, with credentials available via `.env`.
- Scramble remains the canonical OpenAPI generation mechanism and can be executed during CI/deploy workflows.
- Existing authentication/authorization model can represent viewer and operator/publisher capabilities needed for docs read and diagnostics access.
- Frontend stack (Vue 3 + Inertia) has a consistent component pattern suitable for introducing a shared `HelpHint` component across pages.
- Repository governance permits adding docs/tooltips directories, schema contracts, generated artifacts, and hook scripts.
- CI/CD pipeline can run documentation validation, coverage checks, and Typesense-backed integration tests as release gates.
- Coverage inventory for required surfaces and endpoints can be codified in config and kept authoritative by the product/team process.
- English-only content is acceptable for current users while locale-ready schema fields are introduced for future localization.
- No per-team or per-org content override is required in this phase; one global canonical docs set satisfies product requirements.

