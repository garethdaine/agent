# Agent Platform — Task Log

> Active project status: see `docs/PROJECT-STATUS.md`

---

## Current — Open Items

### Session 15 Task 12 — Wire shared sync automation for pre-commit and deploy enforcement (Completed)

Pre-Execution Goal Articulation

SITUATION
- `docs:sync` exists and writes manifest + dispatches async reindex, but deploy mode does not currently enforce bounded reindex retries or fail deployment on exhausted retry budget.
- Repository currently has no checked-in `.githooks/pre-commit`, no shared `scripts/docs/sync.sh`, and no project-scoped Claude/Codex pre-commit hook entrypoints.
- No integration test currently verifies commit-mode automation behavior (auto-regenerate + auto-stage + non-block on sync failure) or deploy-mode strict retry-fail behavior.
- `.github/workflows` is currently absent, so deploy enforcement must be introduced by creating workflow YAML in-repo.

TASK
- One shared sync pipeline must be callable from git pre-commit and Claude/Codex hook entrypoints.
- Commit mode must auto-regenerate and auto-stage stale docs artifacts, and must not block commit when sync command fails.
- Deploy mode must enforce bounded reindex retries (3 retries, 30s interval, 2m cap) and fail with non-zero exit after retry budget exhaustion.
- CI/deploy workflow must run `php artisan docs:sync --mode=deploy --source=repo` so deploy fails when bounded reindex retries cannot recover.

ACTION
- [x] Add failing integration tests in `tests/Integration/Documentation/DocsAutomationFlowTest.php` for:
  - [x] commit mode stale artifact auto-regenerate + auto-stage behavior
  - [x] commit mode sync failure non-block behavior
  - [x] deploy mode bounded retry policy (3 retries, 30s interval, 2m cap) and terminal failure
- [x] Implement shared script `scripts/docs/sync.sh` with commit/deploy behavior split and robust non-blocking commit semantics.
- [x] Wire shared script from `.githooks/pre-commit` and project Claude/Codex hook entrypoints.
- [x] Implement deploy-time strict retry/fail behavior in docs sync runtime path.
- [x] Add deploy workflow YAML under `.github/workflows/` invoking `php artisan docs:sync --mode=deploy --source=repo`.
- [x] Run verification commands:
  - [x] `php artisan test --filter=DocsAutomationFlowTest`
  - [x] `bash scripts/docs/sync.sh --mode=commit --source=repo` (local run)
  - [x] `bash scripts/docs/sync.sh --mode=deploy --source=repo` (local run)

RESULT
- Completion is proven by fail-then-pass integration test evidence, working local script execution in both modes, and deploy gate behavior producing non-zero exit when retry budget is exhausted.

Assumptions and scope boundaries
- Assumption: adding repo-local hook scripts (`.githooks`, `.claude/hooks`, `.codex/hooks`) is acceptable in this project.
- Assumption: deploy workflow may be introduced as a new `.github/workflows/*.yml` file because none currently exists.
- Scope boundary: this task only changes automation scripts/hooks and deploy-time docs sync/reindex gate behavior; it does not add docs authoring features or alter docs UI contracts.

Failure modes to guard
- Malicious-caller mode: hook/script execution in partial environments (missing git metadata, missing shared script, missing command dependencies) must fail safely without corrupting repo state.
- Tired-maintainer mode: stale artifact drift and silent deploy reindex failures must be surfaced deterministically, with bounded retry behavior that fails hard once budget is exhausted.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test tests/Integration/Documentation/DocsAutomationFlowTest.php` failed with:
    - missing `scripts/docs/sync.sh`,
    - missing hook/workflow entrypoint files,
    - deploy-mode command still returning exit code `0` instead of failure for exhausted reindex retries.
  - Green state:
    - `php artisan test --filter=DocsAutomationFlowTest` passed (`4 passed, 28 assertions`).
    - `php artisan test --filter=DocsSyncCommandTest` passed (`5 passed, 27 assertions`).
    - `bash scripts/docs/sync.sh --mode=commit --source=repo` passed:
      - `Docs sync completed.`
      - `Mode: commit | Source: repo | Entries: 12 | Fragments: 14 | Links: 61`
    - `bash scripts/docs/sync.sh --mode=deploy --source=repo` passed:
      - `Docs sync completed.`
      - `Mode: deploy | Source: repo | Entries: 12 | Fragments: 14 | Links: 61`
  - Deploy retry-fail gate verification:
    - `DocsAutomationFlowTest::test_deploy_mode_retries_three_times_with_thirty_second_interval_then_fails` confirms deploy reindex executes initial attempt + 3 retries with sleep cadence `[30, 30, 30]` and returns non-zero.
- Conditions where this works:
  - `scripts/docs/sync.sh` remains executable and hook files are installed/executable.
  - Deploy runtime has required Scout/Typesense env config; on repeated timeout/failure it now fails with bounded retry policy.
  - Commit-time hook runs inside a git worktree so auto-staging can add regenerated docs artifacts.
- Explicit non-goals / limitations:
  - This task does not install `core.hooksPath` automatically on developer machines.
  - Workflow file is introduced as a deploy gate entrypoint; repository-specific release orchestration beyond this gate is not changed here.

### Session 15 Task 10 — Add coverage audit service and strict CI gates (Completed)

Pre-Execution Goal Articulation

SITUATION
- `docs:validate` currently validates schema-level contract only and does not enforce documentation-surface completeness.
- There is no `docs:coverage` command, no codified coverage inventory, and no strict `--fail-on-missing` gate for CI usage.
- Existing docs data is sparse and does not yet satisfy strict per-surface criteria, so gate logic must be test-driven with isolated fixtures.

TASK
- Introduce a strict coverage audit that enforces required documentation surfaces and pass criteria:
  - published overview doc,
  - settings detail,
  - concrete example,
  - troubleshooting guidance,
  - linked tooltip coverage.
- Add command-level enforcement via `php artisan docs:coverage --fail-on-missing`.
- Extend `docs:validate` output to include link/orphan checks for missing tooltip `ui_key`, broken `learn_more_slug`, and missing critical route docs.

ACTION
- [x] Add failing tests in `tests/Feature/Documentation/DocsCoverageGateTest.php` for strict pass/fail behavior and validation link/orphan reporting.
- [x] Run targeted test command to confirm intentional red state.
- [x] Add `config/docs_coverage.php` surface inventory and implement `CoverageAuditService`.
- [x] Implement `DocsCoverageCommand` with `--fail-on-missing`.
- [x] Extend `DocsValidateCommand` to run and report link/orphan coverage checks.
- [x] Run verification commands:
  - `php artisan docs:coverage --fail-on-missing`
  - `php artisan docs:validate`
  - `php artisan test --filter=DocsCoverageGateTest`

RESULT
- Completion is proven by fail-then-pass test evidence for coverage gates, successful command execution, and explicit validation output that reports/blocks orphan-link and missing-surface failures.

Assumptions and scope boundaries
- Assumption: route/surface inventory defined in task context is accepted and stable for codification in config.
- Assumption: this task is backend validation/coverage only; no UI routing/component changes are required.
- Scope boundary: add strict audit logic and command gates only; do not implement new docs content authoring flows.

Failure modes to guard
- Malicious-caller mode: invalid/missing docs references should fail predictably and not produce false pass coverage.
- Tired-maintainer mode: route or settings renames causing stale mappings should surface as actionable validation failures.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=DocsCoverageGateTest` failed with:
    - `The command "docs:coverage" does not exist.`
    - `Expected status code 1 but received 0.` for `docs:validate` orphan checks.
  - Green state: `php artisan test --filter=DocsCoverageGateTest` passed (`3 passed, 12 assertions`).
  - Required command verification:
    - `php artisan docs:coverage --fail-on-missing` passed with `Coverage: 100.00% (12/12 surfaces)` and `Link/orphan checks passed.`
    - `php artisan docs:validate` passed with `Validated markdown files: 12, tooltip files: 2, tooltip fragments: 14` and `Link/orphan checks: passed.`
  - Regression slice: `php artisan test --filter=Documentation` passed (`52 passed, 352 assertions`).
- Conditions where this works:
  - `docs_coverage` inventory is maintained when routes, settings, or tooltip keys change.
  - Documentation markdown continues to include the required section markers (`Settings`, `Example`, `Troubleshooting`).
  - Tooltip fragments maintain valid `learn_more_slug` references to non-deprecated docs.
- Explicit non-goals / limitations:
  - This task does not add CI workflow YAML because no `.github/workflows` directory exists in this repository.
  - This task does not implement new docs UI; it focuses on validation and command-level gating only.

### Session 15 Task 6 — Add authenticated read-only docs HTTP contracts (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs web routes (`/docs`, `/docs/{slug}`) and docs API read routes (`/agent/api/v1/docs/search`, `/agent/api/v1/docs/fragments/{uiKey}`, `/agent/api/v1/docs/coverage`) are already present in `routes/web.php` and `routes/api.php`.
- Existing feature coverage in `tests/Feature/Documentation/DocsAuthorizationTest.php` already validates authenticated access, guest denial/redirect, coverage authorization, unknown slug/ui key handling, malformed query params, and read-only method exposure.
- Controllers/requests for docs read endpoints already exist under `app/Http/Controllers/Docs/`, `app/Http/Controllers/Api/V1/Docs/`, and `app/Http/Requests/Docs/`.

TASK
- Ensure docs HTTP contracts remain authenticated and read-only with no write surface.
- Verify authorization boundaries and edge/failure paths for unknown slugs/ui keys and malformed query parameters.
- Prove completion with route and test evidence.

ACTION
- [x] Execute `php artisan test --filter=DocsAuthorizationTest`.
- [x] Execute `php artisan route:list --path=docs` and verify web + API docs read routes exist.
- [x] Execute a mutating-method route scan for docs URIs and verify no POST/PUT/PATCH/DELETE routes are registered.
- [x] Update task log with assumptions, verification evidence, correctness conditions, and non-goals.

RESULT
- Completion is proven when:
  - `DocsAuthorizationTest` passes,
  - docs route listing contains only the expected GET/HEAD contracts,
  - no mutating docs routes are present.

Assumptions and scope boundaries
- Assumption: existing auth middleware (`auth:sanctum` and web auth stack) remains the canonical guard for private docs surfaces.
- Assumption: `view-docs-coverage` gate remains mapped to the intended internal role/capability policy.
- Scope boundary: no create/update/delete docs endpoints, no docs authoring UI, and no search/indexing logic changes.

Failure modes to guard
- Malicious-caller mode: unauthenticated requests or unauthorized roles attempting to access docs APIs, especially `/agent/api/v1/docs/coverage`.
- Tired-maintainer mode: accidentally introducing mutating docs routes or weakening auth middleware while adding docs-related routes.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - `php artisan test --filter=DocsAuthorizationTest` passed (`9 passed, 18 assertions`).
  - `php artisan route:list --path=docs` reported:
    - `GET|HEAD docs`
    - `GET|HEAD docs/{slug}`
    - `GET|HEAD agent/api/v1/docs/search`
    - `GET|HEAD agent/api/v1/docs/fragments/{uiKey}`
    - `GET|HEAD agent/api/v1/docs/coverage`
  - Mutating method scan over docs URIs (`POST|PUT|PATCH|DELETE`) returned no matches.
- Conditions where this works:
  - Auth middleware and gate configuration remain active as currently wired in `routes/web.php` and `routes/api.php`.
  - Coverage authorization continues to be enforced by `can:view-docs-coverage`.
- Explicit non-goals / limitations:
  - No new docs route/controller code was required in this task run because the target contracts were already implemented.
  - This task does not add any docs write endpoints or in-app authoring surfaces.

### Session 15 Task 5 — Ingest Scramble OpenAPI artifacts into docs domain (Completed)

Pre-Execution Goal Articulation

SITUATION
- `ApiDocArtifact` schema/model exists, but there is no ingest command to import Scramble OpenAPI operations into runtime metadata.
- There is no feature coverage yet for OpenAPI artifact ingest behavior (`operation_id` import, checksum/version updates, linked narrative slug integrity, idempotent reruns).
- Existing docs sync/search pipelines already index `ApiDocArtifact`, so this task should focus on import path and integrity rules, not search API/UI rewiring.

TASK
- Implement `docs:openapi:ingest` to parse exported OpenAPI artifact metadata and upsert `ApiDocArtifact` rows safely and idempotently.
- Ensure ingest behavior handles required edge/failure paths: missing `operationId`, changed endpoint path/method, broken narrative slug links, checksum/version changes.
- Keep Scramble export as canonical artifact source and wire ingest to the project-standard export path.

ACTION
- [x] Add failing tests first in `tests/Feature/Documentation/OpenApiArtifactIngestTest.php` for:
  - operation import and field mapping,
  - checksum/spec-version updates on artifact changes,
  - linked narrative slug integrity (`linked_doc_slugs`),
  - idempotent rerun behavior,
  - failure on missing `operationId`,
  - behavior when endpoint path/method changes.
- [x] Run `php artisan test --filter=OpenApiArtifactIngestTest` and confirm red state.
- [x] Implement `app/Console/Commands/DocsOpenApiIngestCommand.php`:
  - load spec from configured/project-standard export path,
  - parse JSON or YAML,
  - normalize operations and linked slugs,
  - upsert by `operation_id`,
  - update path/method/summary/description/tags/spec checksum/version/published timestamp.
- [x] Add required config wiring for OpenAPI artifact path/format assumptions if missing.
- [x] Ensure ingestion is idempotent and safe for reruns.
- [x] Run verification commands:
  - `php artisan docs:openapi:ingest`
  - `php artisan test --filter=OpenApiArtifactIngestTest`

RESULT
- Completion evidence requires fail-then-pass for `OpenApiArtifactIngestTest`, successful `docs:openapi:ingest` execution, and DB assertions proving correct upsert/update/idempotency/integrity behavior.

Assumptions and scope boundaries
- Assumption: Scramble artifact file is available at repo-standard path (or configured equivalent) and is valid JSON/YAML.
- Assumption: API narrative docs are already synced into `documentation_entries` (`domain=api_doc`) when link integrity checks run.
- Scope boundary: ingest command + runtime metadata integrity only; no public docs portal/search UX changes.

Failure modes to guard
- Malicious-caller mode: malformed spec payload, missing required operation metadata (`operationId`), invalid linked slug references.
- Tired-maintainer mode: duplicate operation IDs, stale path/method drift, repeated reruns creating duplicate artifacts.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state: `php artisan test --filter=OpenApiArtifactIngestTest` failed (`4 failed`) with `The command "docs:openapi:ingest" does not exist.`
  - Green state: `php artisan test --filter=OpenApiArtifactIngestTest` passed (`4 passed, 27 assertions`).
  - Runtime command verification:
    - `php artisan docs:sync --mode=commit --source=repo` passed (`Entries: 2 | Fragments: 1 | Links: 6`).
    - `php artisan docs:openapi:ingest --path=storage/app/docs-sync/openapi-ingest-verification.yaml` passed (`Operations: 1 | Version: 1.2.3`) with checksum output.
- Conditions where this works:
  - OpenAPI artifact path points to a valid JSON or YAML OpenAPI document containing `paths`.
  - Each ingestible operation includes `operationId`.
  - Each ingestible operation includes linked narrative slugs using configured extension key (default `x-linked-doc-slugs`).
  - Linked narrative slugs resolve to existing `documentation_entries` rows in `domain=api_doc`.
- Explicit non-goals / limitations:
  - This task does not run or configure Scramble export itself; it ingests an already-exported artifact.
  - This task does not create API narrative docs automatically for unresolved slugs.
  - This task does not build public API portal views; scope is artifact import + runtime link integrity.

### Session 15 Task 4 — Implement Scout+Typesense docs search service (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs API search currently uses `DocsCatalog` in-memory stubs rather than runtime models and has no Scout/Typesense dependency.
- `laravel/scout` config/package is not present yet, so there is no Typesense-backed indexing contract in code.
- Existing docs runtime entities (`DocumentationEntry`, `DocumentationFragment`, `DocumentationLink`, `ApiDocArtifact`) exist and are populated by `docs:sync`, which gives a reliable base for searchable data.
- Constraints:
  - Typesense must be treated as mandatory search backend.
  - Search API must provide outage contract (`search temporarily unavailable`) and must not return fallback results when search backend is unavailable.
  - Scope is backend indexing/search API behavior only (no UI work).

TASK
- Implement a docs search stack where indexing and queries are Scout+Typesense-backed, with route-affinity ranking and strict outage behavior.
- Ensure API returns required payload fields: `title`, `snippet`, `domain`, `section`, `route_affinity`, `updated_at`.
- Ensure async index freshness path dispatches reindex work targeting <=60s freshness.
- Ensure invalid filters and empty query are handled explicitly, and Typesense outage returns deterministic unavailable response with no fallback results.

ACTION
- [x] Add tests first:
  - `tests/Feature/Documentation/DocsSearchApiTest.php`
  - `tests/Unit/Documentation/DocsSearchServiceTest.php`
  covering domain filters, route-affinity boost/order, payload shape, empty query + invalid params, and outage response contract.
- [x] Run `php artisan test --filter=DocsSearchApiTest` to confirm red state.
- [x] Add Scout and Typesense integration:
  - install required packages,
  - add `config/scout.php`,
  - add env defaults in examples,
  - add searchable mappings (`Searchable`, `toSearchableArray`, Typesense schema options) on docs models.
- [x] Implement `app/Support/Documentation/DocsSearchService.php` and wire `DocsSearchController` to use it.
- [x] Add async reindex job for changed docs entities and trigger from docs sync path.
- [x] Re-run `php artisan test --filter=DocsSearch`.
- [x] Run `php artisan scout:sync-index-settings` with `SCOUT_DRIVER=typesense` and capture result.

RESULT
- Completion is proven by:
  - fail-then-pass evidence for `DocsSearchApiTest` and `DocsSearchServiceTest`,
  - passing docs-search test slice (`--filter=DocsSearch`),
  - successful `scout:sync-index-settings` execution under Typesense driver,
  - API response evidence for required payload fields and outage behavior.

Assumptions and scope boundaries
- Assumption: test DB/runtime env is already configured and safe for non-destructive test runs.
- Assumption: Typesense credentials/host are provided through environment when command-level sync is executed.
- Scope boundary: search indexing/query pipeline and API payloads only; docs pages/tooltip UI integration remain unchanged.

Failure modes to guard
- Malicious-caller mode: invalid domain/section/route filters and empty query should fail predictably (validation) and avoid expensive backend work.
- Tired-maintainer mode: silent fallback to local/in-memory search during Typesense outage must be prevented to avoid misleading results.

Review
- Added tests:
  - `tests/Feature/Documentation/DocsSearchApiTest.php`
  - `tests/Unit/Documentation/DocsSearchServiceTest.php`
- Added Scout/Typesense integration:
  - `composer.json` + `composer.lock` include `laravel/scout` and `typesense/typesense-php`.
  - `config/scout.php` with Typesense client settings and docs model search parameters.
  - `.env.example` and `.env.testing.example` now include Scout/Typesense env defaults.
- Added new docs search runtime path:
  - `app/Support/Documentation/DocsSearchIndexClient.php`
  - `app/Support/Documentation/ScoutDocsSearchIndexClient.php`
  - `app/Support/Documentation/DocsSearchService.php`
  - `app/Support/Documentation/DocsSearchUnavailableException.php`
  - `app/Http/Controllers/Api/V1/Docs/DocsSearchController.php` now returns 503 outage contract (`search temporarily unavailable`) with no fallback data.
  - `app/Http/Requests/Docs/SearchDocsRequest.php` now requires non-empty `q` and supports `route`.
  - `app/Providers/AppServiceProvider.php` binds `DocsSearchIndexClient` to `ScoutDocsSearchIndexClient`.
- Added async freshness hook:
  - `app/Jobs/ReindexDocumentationSearchJob.php`
  - `app/Support/Documentation/DocsSyncService.php` now disables inline Scout syncing during upsert and dispatches delayed reindex job (default 5s delay, <=60s freshness target in config).
  - `config/documentation.php` includes `search.reindex_delay_seconds` and `search.freshness_target_seconds`.
- Verification evidence:
  - Red state: `php artisan test --filter=DocsSearchApiTest` failed (missing classes/contracts and old API behavior).
  - Green state: `php artisan test --filter=DocsSearch` passed (`8 passed, 24 assertions`).
  - Contract check: `php artisan test --filter=DocsSearchApiTest` passed (`4 passed, 15 assertions`).
  - Regression safety: `php artisan test --filter=DocsAuthorizationTest` and `php artisan test --filter=DocsSyncCommandTest` both passed.
  - Command check: `SCOUT_DRIVER=typesense php artisan scout:sync-index-settings` exits successfully and reports: `The \"typesense\" engine does not support updating index settings.`
- Conditions where this works:
  - Typesense is reachable and configured via `TYPESENSE_*` env values.
  - `SCOUT_DRIVER=typesense` is enabled in runtime environments.
  - Queue workers process `ReindexDocumentationSearchJob` on the `agent` queue.
- Explicit non-goals / limitations:
  - `scout:sync-index-settings` does not apply settings for Typesense in Scout v10 (engine limitation); collection schema is driven by model schema on import/reindex.
  - This task does not rewire docs page rendering (`DocsPageController`) away from `DocsCatalog`.

### Session 15 Task 3 — Build sync pipeline and command orchestration (Completed)

Pre-Execution Goal Articulation

SITUATION
- Runtime docs schema/models and contract validators exist, but there is no ingestion orchestration that parses repo files and persists deterministic runtime state.
- `docs:sync` command does not exist yet, so there is no canonical entrypoint for commit/deploy sync mode orchestration.
- Existing docs read paths currently use in-memory catalog stubs, so this task must stay scoped to ingestion/persistence and not rewire search/ranking behavior.

TASK
- Implement a parse-validate-normalize-upsert-link pipeline with repo-wins conflict behavior for duplicate `slug`/`ui_key` keys against existing runtime records.
- Add `docs:sync` orchestration (`--mode=commit|deploy --source=repo`) that writes deterministic sync manifest output and is idempotent on rerun.
- Ensure failure paths are explicit and safe: duplicate keys across files, unresolved `learn_more_slug`, partial parse/validation failures, and stale runtime rows being overwritten by repo content.

ACTION
- [x] Add failing feature tests in `tests/Feature/Documentation/DocsSyncCommandTest.php` for:
  - repo-wins overwrite on same `slug` / `ui_key`
  - checksum updates when repo content changes
  - link upserts from route/setting/flag associations
  - deterministic and idempotent manifest output
- [x] Run `php artisan test --filter=DocsSyncCommandTest` and capture expected red-state evidence.
- [x] Implement `app/Support/Documentation/DocsIngestionPipeline.php`.
- [x] Implement `app/Support/Documentation/DocsSyncService.php`.
- [x] Implement `app/Console/Commands/DocsSyncCommand.php` with strict option validation for `--mode` and `--source`.
- [x] Ensure transaction-safe upsert semantics and repo-wins overwrite behavior for pre-existing runtime records.
- [x] Ensure manifest artifact is written to `storage/app/docs-sync/manifest.json` with stable ordering/content.
- [x] Run `php artisan docs:sync --mode=commit --source=repo`.
- [x] Re-run `php artisan test --filter=DocsSyncCommandTest` until green.

RESULT
- Completion is proven by:
  - fail-then-pass evidence for `DocsSyncCommandTest`,
  - successful `php artisan docs:sync --mode=commit --source=repo` execution,
  - deterministic `storage/app/docs-sync/manifest.json` content across idempotent reruns,
  - persisted runtime rows showing repo-wins overwrite + link upsert behavior.

Assumptions and scope boundaries
- Assumption: schema/migrations from earlier tasks are present and available in `APP_ENV=testing` with `DB_CONNECTION=pgsql_testing`.
- Assumption: repository docs contract files under configured paths are readable.
- Scope boundary: ingestion orchestration + persistence only; search ranking/index relevance logic remains out of scope.

Failure modes to guard
- Malicious-caller mode: invalid file payloads or unresolved cross-links must not partially corrupt persisted runtime state.
- Tired-maintainer mode: duplicate keys across files, stale row drift, and nondeterministic manifest ordering must be surfaced/blocked.

Review
- Added feature coverage in `tests/Feature/Documentation/DocsSyncCommandTest.php` for:
  - repo-wins overwrite of stale `DocumentationEntry`/`DocumentationFragment` rows by `slug`/`ui_key`
  - checksum refresh behavior on content updates
  - deterministic link replacement/upsert behavior
  - deterministic manifest output with idempotent rerun behavior
  - failure paths for duplicate keys and unresolved `learn_more_slug`
- Added ingestion orchestration implementation:
  - `app/Support/Documentation/DocsIngestionPipeline.php`
  - `app/Support/Documentation/DocsSyncService.php`
  - `app/Console/Commands/DocsSyncCommand.php`
- Added sync config for command constraints + artifact path in `config/documentation.php`.
- Verification evidence:
  - Red state: `php artisan test --filter=DocsSyncCommandTest` failed with `The command "docs:sync" does not exist.` (5 failing tests).
  - Green state: `php artisan test --filter=DocsSyncCommandTest` passed (`5 passed, 27 assertions`).
  - Required command: `php artisan docs:sync --mode=commit --source=repo` passed (`Entries: 2 | Fragments: 1 | Links: 6`) and wrote manifest to `storage/app/docs-sync/manifest.json`.
  - Regression slice: `php artisan test --filter=Documentation` passed (`32 passed, 105 assertions`).
- Conditions where this works:
  - Docs contract directories configured in `config/documentation.php` are readable and contain valid phase-1 contract content.
  - DB writes run inside transaction boundaries; unresolved cross-links and duplicate keys fail the run without partial persistence.
  - Command options must be `--mode=commit|deploy` and `--source=repo`.
- Explicit non-goals / limitations:
  - Search ranking behavior and Typesense relevance logic are out of scope.
  - This task does not rewire docs read APIs/pages from `DocsCatalog` to runtime DB-backed retrieval.

### Runtime Documentation Schema and Models (Completed)

Pre-Execution Goal Articulation

SITUATION
- Documentation runtime tables/models for entries, fragments, links, and API artifacts are not present yet.
- Existing test suite has docs auth/navigation tests but no schema-level constraint coverage for the new docs runtime entities.
- Repository patterns enforce DB constraints directly, with PostgreSQL checks and pragmatic test behavior for SQLite/other drivers.

TASK
- Add runtime data-layer support for `DocumentationEntry`, `DocumentationFragment`, `DocumentationLink`, and `ApiDocArtifact` with enforceable uniqueness, FK integrity, enum constraints, and required indexes.
- Provide feature tests in `tests/Feature/Documentation/DocumentationSchemaTest.php` proving these constraints and relationships.

ACTION
- [x] Add failing schema tests for table shape + constraints:
  - unique `documentation_entries` (`domain`, `slug`, `locale`)
  - unique `documentation_fragments` (`ui_key`, `locale`)
  - enum/check behavior for `domain` and `severity`
  - FK integrity for cross-links (`documentation_links`, `api_doc_artifacts`)
- [x] Run `php artisan test --filter=DocumentationSchemaTest` and capture red state (missing schema/constraints).
- [x] Add migrations for:
  - `documentation_entries`
  - `documentation_fragments`
  - `documentation_links`
  - `api_doc_artifacts`
  with indexes on `domain`, `section`, `updated_at` where applicable.
- [x] Add models with casts/relationships:
  - `DocumentationEntry`
  - `DocumentationFragment`
  - `DocumentationLink`
  - `ApiDocArtifact`
- [x] Re-run `php artisan test --filter=DocumentationSchemaTest` until green.
- [x] Run `php artisan migrate --pretend` and confirm SQL generation is non-destructive.

RESULT
- Completion proof is a fail-then-pass test sequence for `DocumentationSchemaTest` plus `migrate --pretend` output showing create/index/check/FK SQL for new docs runtime tables.

Assumptions and scope boundaries
- Assumption: PostgreSQL test connection (`pgsql_testing`) is available and enforces enum/FK/unique constraints at database level.
- Assumption: runtime docs data remains private/authenticated; this task does not add route/controller/search wiring.
- Scope boundary: data layer only (migrations + models + schema tests); no ingestion, search, API response shaping, or UI behavior.

Failure modes to guard
- Malicious-caller mode: invalid `domain`/`severity` values or broken FK references must fail at DB write time.
- Tired-maintainer mode: accidental duplicate `domain+slug+locale` or `ui_key+locale` should be blocked by unique constraints.

Review
- Added feature test: `tests/Feature/Documentation/DocumentationSchemaTest.php`.
- Added migrations:
  - `database/migrations/2026_03_02_150000_create_documentation_entries_table.php`
  - `database/migrations/2026_03_02_150100_create_documentation_fragments_table.php`
  - `database/migrations/2026_03_02_150200_create_documentation_links_table.php`
  - `database/migrations/2026_03_02_150300_create_api_doc_artifacts_table.php`
- Added models:
  - `app/Models/DocumentationEntry.php`
  - `app/Models/DocumentationFragment.php`
  - `app/Models/DocumentationLink.php`
  - `app/Models/ApiDocArtifact.php`
- Verification evidence:
  - Red state: `php artisan test --filter=DocumentationSchemaTest` failed with undefined docs runtime tables.
  - Green state: `php artisan test --filter=DocumentationSchemaTest` passed (7 tests, 14 assertions).
  - `php artisan migrate --pretend` output: `INFO  Nothing to migrate.` (migrations already applied in this environment).
  - Migration registration evidence: `php artisan migrate:status` shows all four new documentation migrations as `Ran`.

### Docs Center Pages + Auth Navigation Discoverability (Completed)

Pre-Execution Goal Articulation

SITUATION
- `/docs` and `/docs/{slug}` web routes already exist and are authenticated.
- `DocsPageController` already returns Inertia views `Docs/Index` and `Docs/Show`.
- `resources/js/Pages/Docs/Index.vue` and `resources/js/Pages/Docs/Show.vue` do not exist yet.
- Shared authenticated navigation in `resources/js/Layouts/AppLayout.vue` currently has no Docs nav item.
- Existing docs feature tests cover auth/read-only behavior, but no dedicated `DocsNavigationTest` exists for discoverability requirements.

TASK
- Authenticated users can reach `/docs` and `/docs/{slug}` through working Inertia pages.
- Shared authenticated primary navigation includes a Docs entry linking to `/docs`.
- A dedicated feature test (`DocsNavigationTest`) proves docs route reachability, nav discoverability, slug resolution, unknown slug 404 behavior, and guest redirect behavior.

ACTION
- [x] Add `tests/Feature/Documentation/DocsNavigationTest.php` with assertions for:
  - authenticated `/docs` route + expected Inertia component,
  - docs navigation link discoverability in primary layout,
  - `/docs/{slug}` resolution and unknown slug 404,
  - guest redirect on docs routes.
- [x] Run `php artisan test --filter=DocsNavigationTest` and capture initial failure.
- [x] Implement docs UI shell pages:
  - `resources/js/Pages/Docs/Index.vue`
  - `resources/js/Pages/Docs/Show.vue`
- [x] Add Docs entry to authenticated navigation in `resources/js/Layouts/AppLayout.vue` (desktop + responsive menus).
- [x] Re-run `php artisan test --filter=DocsNavigationTest` and confirm pass.
- [x] Run `npm run build` and confirm pass.

RESULT
- Evidence is:
  - failing-then-passing `DocsNavigationTest` output,
  - successful `npm run build`,
  - test assertions proving docs link target and docs route resolution from in-app navigation target.

Assumptions and scope boundaries
- Assumption: `AppLayout.vue` is the shared authenticated navigation for targeted user roles.
- Assumption: route names `docs.index` and `docs.show` remain stable.
- Scope boundary: docs center shell + nav discoverability only; no tooltip/help hint integration.
- Scope boundary: no changes to docs ingestion/search architecture in this task.

Failure modes to guard
- Malicious-caller mode: unauthenticated access to docs routes must remain blocked by auth middleware.
- Tired-maintainer mode: route/link drift between nav and route names should be caught by explicit test assertions.

Review
- Added `tests/Feature/Documentation/DocsNavigationTest.php` with assertions for docs index reachability, layout nav discoverability, slug resolution, unknown slug 404, and guest redirects.
- Added docs pages:
  - `resources/js/Pages/Docs/Index.vue`
  - `resources/js/Pages/Docs/Show.vue`
- Added `Docs` navigation entry to both desktop and responsive authenticated nav menus in `resources/js/Layouts/AppLayout.vue`.
- Verification evidence:
  - Initial red state: `php artisan test --filter=DocsNavigationTest` failed due missing Inertia pages (`Docs/Index`, `Docs/Show`) and missing docs nav target in `AppLayout.vue`.
  - Final green state: `php artisan test --filter=DocsNavigationTest` passed (5 tests, 32 assertions).
  - Build validation: `npm run build` passed (client + SSR).

### Documentation + Tooltip Platform Discovery Brief (Completed)

- [x] Audit current app surfaces (routes/pages/models/settings) to define documentation coverage boundaries.
- [x] Compare API-doc and human-doc tooling options (Scramble, Scribe, LaRecipe, Jigsaw, Docusaurus) against project needs.
- [x] Define requirements for searchable user docs using Laravel Scout.
- [x] Define requirements for contextual helper text and hover tooltips across the UI.
- [x] Produce a requirements discovery brief with recommendations, phased rollout, and acceptance criteria.

Review
- Added a new requirements discovery brief at `docs/discovery/documentation-and-tooltip-system-requirements-brief.md`.
- Recommendation from discovery:
  - Keep `Scramble` focused on OpenAPI generation.
  - Build first-party human documentation + tooltip domain in-app (with Scout indexing and unified UI context integration).
  - Optionally export static docs later (Docusaurus/Jigsaw) for public docs, while retaining in-app authoring/search as system-of-record.
- Noted current gap: there is no `packages/` directory yet; brief includes a package-ready architecture plan if/when the folder is introduced.

### Agent Org Layer — Remove Raw JSON UX (Completed)

- [x] Replace raw JSON textareas in Org Agents form with structured controls (authority overrides + output schema builder).
- [x] Replace raw JSON textareas in Org Rituals form with structured controls (context inputs + verification + delivery targets).
- [x] Replace raw JSON textareas in Org Councils form with structured controls (evidence/response schema + report sections).
- [x] Preserve existing API contracts by encoding/decoding form controls to the same payload JSON shapes.
- [x] Run targeted verification (`php artisan test` Org API slice + `npm run build`) and record outcomes.

Review
- Replaced JSON textareas with non-technical builders across Org forms:
  - Agents: authority override rule builder + default output field/schema builder.
  - Rituals: context input map builder, typed verification strategy rule builder, delivery target map builder.
  - Councils: evidence/member-response schema field builders + report section list builder.
- Preserved backend payload contracts by converting form controls into the same JSON object/array shapes in submit handlers.
- Verification:
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org` (34 passed)
  - `npm run build` (client + SSR build succeeded)

### Interrogation Reasoning Text Truncation (In Progress)

- [x] Reproduce the reasoning text cutoff path from streamed interrogation payload parsing.
- [x] Update Codex adapter structured-payload selection to avoid early fragment selection.
- [x] Add unit coverage for multi-candidate streamed JSON where early payload is partial.
- [x] Run targeted interrogation adapter tests.
- [x] Record review notes and residual risk.

Review
- Root cause: `CodexAdapter::decodeBestEffortJson()` returned the first structured payload candidate in streamed JSON, which can be an early partial fragment.
- Fix: collect all structured payload candidates and select the most complete (with later-candidate tie-break), preventing partial reasoning text from winning.
- Verification: `php artisan test tests/Unit/InterrogationCodexAdapterCommandTest.php` (11 tests passed).
- Residual risk: if a later payload is semantically incorrect but structurally richer, it could still be selected; existing schema + command flow keep this risk low.

### Discord Active Runs Formatter Crash (Completed)

- [x] Reproduce Discord-side `List Active Runs Failed` error from formatter output contract.
- [x] Patch `ChatResponseFormatter` runs rendering to support both `job_name` and nested `job.name` payload shapes.
- [x] Extend intent/handler path for `list my active jobs` to map to jobs list with active filtering.
- [x] Add regression coverage for nested run payload formatting.
- [x] Run targeted messenger tests and confirm green.

Review
- Root cause: `ChatResponseFormatter::formatRunsList*()` directly accessed `$run['job_name']`, but `RunsListActiveHandler` returns runs shaped like `{ id, status, job: { name } }`.
- Fix: formatter now safely resolves job name from either `job_name` or `job.name`, with defensive fallbacks for run fields.
- Verification: `php artisan test tests/Feature/Messenger/ChatOrchestrationTest.php tests/Feature/Messenger/ChatActionExecutorTest.php tests/Feature/Messenger/AccountLinkTest.php`.
- Residual risk: other action formatter contracts still have loose schema coupling; dedicated DTO alignment remains a future hardening task.

### Global Notification Drawer + Header Bell (In Progress)

- [x] Add backend notification persistence and API endpoints for list/mark-read/clear-all.
- [x] Share notification summary in Inertia props for global header usage.
- [x] Add header notification bell beside theme switcher with unread count badge.
- [x] Implement right-side notification drawer/slideover with system-wide notification list.
- [x] Implement per-notification "Mark as read" action.
- [x] Implement "Clear all" delete action.
- [x] Verify with targeted feature tests and frontend build.
- [x] Record review notes and residual risks.

Review:
- Added `notifications` table migration for Laravel database notifications.
- Added notification API endpoints: list, mark-one-read, mark-all-read, and clear-all.
- Added global notification presenter + shared Inertia payload for immediate header hydration.
- Added desktop/mobile bell trigger in app header and a right-side drawer with refresh, mark-read, mark-all-read, clear-all, and action links.
- Verification: `php artisan test --filter=NotificationApiTest`, `php artisan test --filter=OutboundMessageFailedNotificationTest`, `npm run build`.
- Residual risk: approval/retry events not emitted as notifications yet in all modules; drawer is ready to surface them as soon as producers persist database notifications.

### Discovery Wizard Full Session Validation (Natural Language Scheduling)

- [ ] Create a brand-new discovery session using `docs/discovery/natural-language-scheduling.md` as the feature brief on `https://agent.test`.
- [ ] Drive the session end-to-end (setup → tech stack → discovery → interrogation → summary → planning → rules → tasks → build execution).
- [ ] Capture UI parity findings at every stage/control surface against Figma reference.
- [ ] Patch wizard subcomponents that still use legacy styling and do not match the Figma visual language.
- [ ] Re-run the same session flow checks in browser after patching.
- [ ] Record verification results and any residual gaps.

### Interrogation Loop — Codex Duplicate Question Fix (In Progress)

- [x] Compare Codex vs Claude interrogation round flow to isolate loop vector.
- [x] Add runtime duplicate-intent guard for already-answered questions in interrogation.
- [x] Add repair retry path that forces a materially different question and resets Codex resume state when needed.
- [x] Add regression tests for duplicate-question loop prevention.
- [x] Run targeted interrogation unit tests and capture outcomes.

Review
- Root cause: `ExecuteInterrogationRoundJob` accepted schema-valid questions without checking semantic duplication against already-answered questions, allowing Codex to loop across near-identical visibility/versioning prompts with new IDs.
- Added duplicate-intent detection in round execution using answered-question history, text similarity, option similarity, selected-answer overlap, and topic overlap.
- Added parity continuity prompting for interrogation rounds so both runners receive resolved-decision context in every turn (not only implicit CLI resume state).
- Added automatic repair flow: request a materially different unresolved question; if duplicate persists on resumed Codex thread, retry with `cli_session_id` cleared and a context-recovery prompt.
- Replaced hard failure on persistent duplicate output with non-terminal auto-resolution: duplicate question is auto-answered from prior confirmed answer and interrogation auto-advances to next unresolved question.
- Added bounded auto-recovery depth (`agent.interrogation.duplicate_recovery_max_depth`, default `4`) to prevent endless self-recursion.
- Added unit coverage in `tests/Unit/ExecuteInterrogationRoundJobTest.php` for duplicate repair, resume-reset recovery, and persistent-duplicate auto-resolution behavior.
- Verification:
  - `php artisan test tests/Unit/ExecuteInterrogationRoundJobTest.php`
  - `php artisan test tests/Unit/QuestionPayloadGuardTest.php tests/Unit/InterrogationCodexAdapterCommandTest.php`

### Discord `/jobs list` Slash Command Failure (Completed)

- [x] Reproduce and confirm why Discord slash `/jobs list` resolves to fallback intent.
- [x] Normalize Discord slash payloads (`jobs`/`runs`) into parser-compatible command text.
- [x] Acknowledge `INTERACTION_CREATE` in Discord Gateway mode to prevent "application did not respond".
- [x] Add regression tests for slash-content normalization and gateway interaction ACK.
- [x] Run targeted messenger test slices and capture outcomes.

Review:
- Root causes:
  - Slash payload text normalization produced `jobs list` (and dropped nested option values), which missed existing parser patterns expecting phrases like `list my jobs`.
  - Discord Gateway `INTERACTION_CREATE` events were dispatched to async processing without first acknowledging interaction callbacks, causing Discord timeout banners.
  - Discord Gateway payload parsing treated `INTERACTION_CREATE` as `MESSAGE_CREATE`, yielding empty inbound content (`""`) and causing fallback "I couldn't understand that command."
- Fixes:
  - Updated `DiscordAdapter::buildInteractionContent()` to map known slash command structures (`jobs list`, `jobs run job_id`, `runs active`, `runs stop run_id`) into parser-friendly canonical text and to recursively flatten nested options in fallback mode.
  - Updated `DiscordAdapter::parseGatewayEvent()` to route `INTERACTION_CREATE` events through interaction parsing instead of message parsing.
  - Updated `DiscordGatewayWorker` to immediately acknowledge interactions through Discord callback API (`/interactions/{id}/{token}/callback`), returning type `5` for normal interactions and type `8` for autocomplete.
  - Added regression tests for slash normalization and gateway ACK behavior.
- Verification:
  - `php artisan test tests/Unit/Messenger/Adapters/DiscordAdapterTest.php tests/Unit/Messenger/Gateway/Workers/DiscordGatewayWorkerTest.php` (32 passed)
  - `php artisan test tests/Feature/Messenger/Webhooks/DiscordWebhookTest.php` (19 passed)

### Agent Org Layer — AI Workforce (Next Feature)

- [ ] Complete discovery session (currently in Setup).
- [ ] Drive through interrogation, summary, planning, and build phases.
- [ ] Implement Phase A: Foundation (org_agent_profiles, org_reporting_edges, org_cost_ledgers).
- [ ] Implement Phase B: Ritual Runtime (org_ritual_templates, org_ritual_runs).
- [ ] Implement Phase C: Council/QA Gates (org_council_templates, org_artifact_reviews, org_escalations).
- [ ] Implement Phase D: Governance (cost ledger thresholds, budget enforcement).
- [ ] Implement Phase E: Hardening (CI compatibility, multi-provider testing).

### Agent Org Layer — Run 3 Amendments (Completed)

- [x] Add top padding to Org Layer empty states so cards are visually aligned with other surfaces.
- [x] Replace placeholder Org Agent create/edit pages with API-backed forms (delegatee + capability bindings).
- [x] Replace placeholder Ritual create/show pages with API-backed forms and details.
- [x] Make Org Agents and Rituals index pages data-driven (list existing records + actionable empty states).
- [x] Make Councils, Escalations, and Costs pages data-driven enough to confirm implementation state.
- [x] Verify org UI routes + org API tests + frontend build, and record review notes.

Review:
- Implemented API-backed org UI flows for agents, rituals, councils, escalations, and costs to replace static placeholders.
- Added real create forms for org agents, rituals, and councils with validation/error handling and redirect-to-index on success.
- Fixed UUID route parameter typing in org web routes (`agents/{id}/edit`, `rituals/{id}`) to prevent type errors with UUID IDs.
- Standardized top spacing on empty-state cards using `pt-16 pb-12` across org surfaces.
- Verification:
  - `php artisan test tests/Feature/OrgUiRoutesTest.php tests/Feature/OrgFeatureGateTest.php tests/Feature/Http/Controllers/Api/V1/Org` (41 passed)
  - `npm run build` (client + SSR build succeeded)

### Agent Org Layer — Migration Sync Hotfix (Completed)

- [x] Investigate `500` failures from Org API (`/org/rituals`, `/org/agents`, `/org/costs`) reported in browser console.
- [x] Confirm root cause via Laravel logs and migration status.
- [x] Apply pending Org migrations in runtime database.
- [x] Re-run Org API feature test slice and confirm all green.

Review:
- Root cause: Org Layer schema migrations were pending in local runtime DB, causing `SQLSTATE[42P01]` undefined-table failures (`org_ritual_templates`, `org_agent_profiles`, `org_cost_ledgers`, etc.).
- Fix: executed `php artisan migrate` and applied all Org migrations in batch 10.
- Verification:
  - `php artisan migrate:status` (Org migrations now `Ran`)
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org` (34 passed)

### Agent Org Layer — API Resilience Hardening (Completed)

- [x] Add defensive table-existence guards for Org index/summary endpoints used by page load.
- [x] Ensure fallback responses preserve frontend payload shapes (`data: []`, cost summary defaults).
- [x] Re-run Org route/feature/API test slices.

Review:
- Added schema guards to prevent `500` on Org page loads when runtime schema is incomplete or mismatched:
  - `OrgAgentController@index` (requires `org_agent_profiles`, `delegatee_profiles`)
  - `OrgRitualController@index` (requires `org_ritual_templates`)
  - `OrgCouncilController@index` (requires `org_council_templates`)
  - `OrgEscalationController@index` (requires `org_escalations`, `org_ritual_runs`)
  - `OrgCostController@summary` (requires `org_cost_ledgers`; returns zeroed summary otherwise)
- Added fail-safe `try/catch` handling on Org first-load list/summary endpoints so unexpected runtime exceptions degrade to stable empty/default responses instead of returning `500`.
- Moved cost-summary date parsing inside guarded path to prevent pre-fallback exceptions from invalid date inputs.
- Verification:
  - `php artisan test tests/Feature/Http/Controllers/Api/V1/Org tests/Feature/OrgFeatureGateTest.php tests/Feature/OrgUiRoutesTest.php` (41 passed)
  - `curl /agent/api/v1/org/costs/summary` (HTTP 200)
  - `curl /agent/api/v1/org/costs/summary?start_date=abc&end_date=def` (HTTP 200 fallback payload)

### AgentKeeper-Inspired Cognitive Continuity Integration Discovery (In Progress)

- [x] Audit current memory/delegation implementation and identify exact integration seams for provider-switch continuity.
- [x] Analyze AgentKeeper implementation patterns and extract reusable concepts only.
- [x] Write discovery brief with architecture fit, phased rollout, API/data-model changes, risks, and acceptance criteria.
- [x] Record review notes and residual risk.

Review:
- Added `docs/discovery/agentkeeper-cognitive-continuity-integration-brief.md` with a concrete integration design anchored to existing memory/delegation hooks (`MemoryContextBuilder`, `MemoryFormationPipeline`, `ExecuteAgentRunJob`, `AttemptSpawner`).
- Recommendation is to adopt AgentKeeper patterns (critical fact continuity + token-budget reconstruction) as a native additive layer, not as a vendored dependency.
- Residual risk: continuity fact auto-promotion quality and scope leakage require careful thresholding + classification/scope guards during implementation.

### Discovery/Interrogation/Planning UX + Codex Parity Fix Set (Completed)

- [x] Investigate and patch planning-phase transient empty-state flicker (`Generating plan...` + robust sync/refetch while planning).
- [x] Fix setup/tech stack layout to remove dead right column or provide meaningful contextual content.
- [x] Improve Codex discovery/interrogation ongoing activity visibility to match Claude parity.
- [x] Harden duplicate re-ask prevention parity for Codex interrogation flow end-to-end.
- [x] Redesign active run AI log rendering for readability (structured timeline, grouped lifecycle, heartbeat collapse, command cards, severity styling, truncation/expand).
- [x] Surface clear operator signal when queue worker is not running and discovery/planning cannot progress.
- [x] Detect/classify Codex MCP connection-refused failures and surface a deduplicated actionable issue in Active Run UI and progress state.
- [x] Add/update tests for touched backend transitions and duplicate-question regression protection.
- [x] Run verification commands and capture exact before/after behavior + residual risks.

Review:
- Added deterministic planning hydration behavior in the wizard: planning-without-plan now renders as generating, with stronger auto-refetch (`include_events=1`) and websocket-triggered immediate re-sync for plan events.
- Removed setup/tech stack/discovery dead-column layout by conditionally hiding the right stats rail before interrogation and expanding content to full width.
- Upgraded runner visibility during discovery/interrogation with live activity timelines (including Codex issue/error/status messages) instead of static placeholders.
- Added backend operator signals for queue-stall scenarios (`QUEUE_WORKER_UNAVAILABLE`) and surfaced them in the wizard.
- Added MCP connection-refused classification at both runtime ingest and frontend presentation layers, with deduplicated issue cards and actionable remediation text.
- Replaced raw active-run `<pre>` log block with structured timeline rendering: command cards (status/exit/duration/output expand), lifecycle grouping, heartbeat collapse/summary, stdout/stderr severity styling, and expandable truncation for long outputs.
- Verification:
  - `php artisan test tests/Feature/InterrogationApiWorkflowTest.php --filter="show_keeps_planning_session_with_missing_plan_in_generation_state|show_includes_operator_signal_when_discovery_queue_appears_stalled|show_includes_operator_signal_when_plan_generation_appears_stalled|show_marks_operational_status_plan_as_not_meaningful"` (4 passed)
  - `php artisan test tests/Unit/ExecuteInterrogationRoundJobTest.php --filter="repairs_semantic_duplicate_when_prior_answer_selected_multiple_options|repairs_duplicate_question_against_answered_history|retries_duplicate_repair_without_resume_for_codex_session|does_not_fail_when_duplicate_question_persists_after_repairs"` (4 passed)
  - `php artisan test tests/Feature/AgentRunnerLifecycleTest.php --filter="mcp_connection_refused_output_sets_deduplicated_mcp_issue_metadata"` (1 passed)
  - `npm run build` (client + SSR build succeeded)

---

## Completed — Session Log

### 2026-03-01 — Atom-of-Thought (AoT) Reasoning Integration Discovery Brief

- [x] Review current planning, build-task generation, and run execution architecture for integration points.
- [x] Define AoT legitimacy/risk positioning and integration recommendation specific to Agent.
- [x] Produce implementation-ready discovery brief with rollout phases, goals, constraints, and acceptance criteria.

Review:
- Added discovery brief at `docs/discovery/atom-of-thought-reasoning-integration.md`.
- Recommendation is AoT-lite: planning + task generation first, conditional execution usage for compound tasks, summary unchanged.
- Proposal keeps STAR runtime primitives in place and treats AoT as additive, config-flagged, and A/B measurable.

### 2026-03-01 — Agent Memory (Session 14)

- [x] Complete Agent Memory v3 discovery brief with gap analysis.
- [x] Resolve all 14 architectural decisions (Neo4j required, Docker Compose, fixed 1536d, etc.).
- [x] Add feature flags: `memory.enabled`, `memory.api_enabled`.
- [x] Fix FeatureFlagManager integration across MemoryEnabled middleware, MemoryCapabilityResolver, MemoryServiceProvider.
- [x] Fix settings validation (add rate limit keys to whitelist).
- [x] Fix settings routes (move outside MemoryEnabled middleware gate).
- [x] Fix Vue numeric coercion (`.toFixed()` crash on string values).
- [x] Fix broadcasting auth 403 (add `Broadcast::routes()`, fix `window.userId` → `usePage().props.auth.user.id`).
- [x] Neo4j setup via Docker Compose verified.

### 2026-02-28 — Agent Gaps (Session 13)

- [x] Resolve 11 production gaps across messenger, delegation, and compliance.
- [x] Consolidate stub handlers from parallel implementations.

### 2026-02-28 — Messenger Control Plane Gaps (Session 13)

- [x] Processor attachment handling fixes.
- [x] Socket worker graceful drain.

### 2026-02-28 — Messenger CP Local-First & Provider Parity (Session 11)

- [x] ReactPHP/Amp gateway supervisor with reconnection strategy.
- [x] All 4 providers with threading, signature verification, attachment handling.

### 2026-02-28 — Stabilize PHPUnit Suite

- [x] Resolve 33 failing tests (webhook aliases, delegation naming, DB safety).
- [x] Resolve 13 remaining warnings/errors (deprecated metadata, migration state).
- [x] Final result: 1651 passed, 7 skipped, 0 failed.

### 2026-02-28 — Discord + WhatsApp Connector Activation

- [x] Register Discord and WhatsApp adapters in `config/messenger.php`.
- [x] Replace webhook stubs with real interaction/message handling.
- [x] Fix middleware timestamp verification for Discord headers.
- [x] Add feature tests for webhook dispatch and schema exposure.

### 2026-02-27 — Natural Language Scheduling (Session 5) ✅

- [x] Hybrid parser: rule-based + LLM fallback at <75% confidence.
- [x] Active-hours scheduling with ISO-8601 day indexing.
- [x] Parse attempt tracking with 90-day retention.
- [x] Rate limits: 10/min, 60/hour on LLM path.

### 2026-02-27 — Workflow Orchestration Instruction (Session 5) ✅

- [x] Phase 1: Shared Policy Layer — ComplexityClassifier, OrchestrationPolicyService, ComplianceFlagResolver.
- [x] Phase 2: Plan Mode Default — pre-execution policy evaluation, mandatory planning for non-trivial work.
- [x] Phase 3: Subagent Strategy — build-task generation prompt requirements, event evidence parsing.
- [x] Phase 4: Self-Improvement Loop — LessonsManager with file-based storage, trigger signals, context injection.
- [x] Phase 5: Verification Before Done — VerificationEvidenceEvaluator, evidence by task category.
- [x] Phase 6: Demand Elegance + Autonomous Bug Fixing — elegance checkpoint, root-cause evidence gate.
- [x] Phase 7: Task Management Workflow Enforcement — task-file policy checks.
- [x] Phase 8: API/UI/Observability — compliance summary fields, metrics dimensions.
- [x] Phase 9: Testing + Rollout — unit + feature tests, progressive rollout.
- [x] Feature flags: `compliance.enabled`, `compliance.enforcement_mode`, and 4 gate flags.

### 2026-02-27 — Figma Make UI Parity (Session 6) ✅

- [x] Class-based dark mode with system preference detection.
- [x] Design tokens with RGB-channel CSS variables and alpha support.
- [x] 20+ base UI components, DM Sans + JetBrains Mono typography.
- [x] Lucide Vue Next icons, 1440px content max-width.
- [x] Discovery wizard and build step parity alignment.
- [x] Dark mode form field regression fixes.
- [x] Phase stepper and monitor metrics polish.

### 2026-02-27 — STAR Reasoning & Delegation Integration (Session 10) ✅

- [x] STAR Preamble Generator (Situation/Task/Action/Result).
- [x] A/B testing infrastructure with configurable percentage split.
- [x] Targeted retry service with trust calibration.

### 2026-02-26 — Adversarial Reviewer (Session 4) ✅

- [x] AdversarialReviewerService via Claude CLI subprocess.
- [x] Verdict types: pass, revise, needs_clarification.
- [x] Shadow mode for safe rollout.

### 2026-02-26 — Feature Flags Management ✅

- [x] DB-backed FeatureFlagManager with config/env fallbacks.
- [x] Authenticated API endpoints for read/update.
- [x] Tools settings page UI.
- [x] Runtime gates rewired (delegation, reviewer, memory, compliance).

### 2026-02-25 — Consolidated Implementation / Delegation v1 (Session 2) ✅

- [x] 9 models, 17 services, 3 controllers, 2 policies, 7 Vue pages.
- [x] DAG execution with verification pipeline (Automated, AI Critic, Human Approval).
- [x] Trust scoring, contract enforcement, recovery chain.

### 2026-02-21 — Messenger Control Plane (Session 9) ✅

- [x] 4 provider integrations (Slack, Telegram, Discord, WhatsApp).
- [x] 7 chat-driven user flows.
- [x] Circuit breaker, rate limiting, replay deduplication, dead-letter queue.

---

## Historical Fix Log

### 2026-02-28 — False Positive Detection Hardening

- [x] ASCII arrow snippet guard (`N->`, `N=>`).
- [x] Escaped newline snippet guard (`\n`, `\\n`).
- [x] Double-escaped snippet guard (`\\\\n`).
- [x] Inline source-snippet heuristic for Vue/JS template payloads.
- [x] Structured output handling guards for non-runtime events.
- [x] Final lifecycle test count: 23 passed.

### 2026-02-25 — Build Execution Fixes

- [x] Queue worker management for `interrogation` supervisor.
- [x] Reconcile `process_not_found` runs into build progression.
- [x] DB safety guardrail (pgsql_testing enforcement).
- [x] Unified build AI log with duplicate suppression.
- [x] Monitor log formatting for fragmented JSON envelopes.

### 2026-02-27 — UI Polish

- [x] Dark mode form field regression (discovery wizard).
- [x] Build step (phase 9) parity alignment.
- [x] Phase stepper active circle clipping.
- [x] Monitor metrics card spacing.
- [x] Planning panel top spacing.

### 2026-03-02 — Codex Build False-Completion Guard

- [x] Added explicit non-interactive runner-mode instructions to interrogation build task markdown to prevent “plan then stop” behavior.
- [x] Added Codex-only execution-evidence gate in build finalization: successful run must show actionable mutation/verification command evidence.
- [x] Added explicit `BUILD_TASK_NO_EXECUTION_EVIDENCE` error path for successful-but-no-op runs.
- [x] Added unit coverage for no-evidence Codex success path and runner-mode markdown contract.
- [x] Verification: `php artisan test tests/Unit/BuildTaskRunFactoryTest.php tests/Unit/ExecuteInterrogationBuildJobTest.php` (12 passed).

### 2026-03-02 — Codex Build Evidence Tightening

- [x] Tightened Codex build evidence gate to require implementation-path mutation evidence plus verification command evidence.
- [x] Added regression coverage for checklist-only/meta-file mutation runs (must fail) and implementation+verification runs (must pass).
- [x] Verification: `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php tests/Unit/BuildTaskRunFactoryTest.php tests/Unit/InterrogationCodexAdapterCommandTest.php` (26 passed).

### 2026-03-02 — Session 15 Task 6: Authenticated Read-Only Docs Contracts

- [x] Write failing feature tests in `tests/Feature/Documentation/DocsAuthorizationTest.php` for:
- [x] Authenticated access to docs read routes (`/docs`, `/docs/{slug}`, docs API reads).
- [x] Unauthenticated denial behavior (web redirect + API 401).
- [x] No docs write route surface (no POST/PUT/PATCH/DELETE routes under docs paths).
- [x] Unauthorized role is forbidden from docs coverage endpoint.
- [x] Unknown `slug` / `uiKey` and malformed search query validation paths.
- [x] Run `php artisan test --filter=DocsAuthorizationTest` and capture expected failures.
- [x] Wire read-only routes in `routes/web.php` and `routes/api.php` with auth middleware.
- [x] Implement docs web/API controllers and docs search request validation.
- [x] Add coverage authorization ability wiring and enforce it on coverage endpoint.
- [x] Re-run `php artisan test --filter=DocsAuthorizationTest` until green.
- [x] Verify route contracts with `php artisan route:list --path=docs`.
- [x] Verify there are no docs write routes via route inspection command.
- [x] Add review notes with verification evidence and known limitations.

Review (to complete after implementation):
- [x] Evidence summary: `php artisan test --filter=DocsAuthorizationTest` passed (9 tests, 18 assertions); `php artisan route:list --path=docs` shows 5 `GET|HEAD` docs routes; `php artisan route:list | rg "docs" | rg "POST|PUT|PATCH|DELETE"` returned no matches.
- [x] Conditions for correctness: existing auth middleware (`auth:sanctum` + verified web group) remains active; role-based gate checks continue to use `User::hasRole(['admin','analytics'])`; docs catalog provides at least one valid slug (`overview`) and fragment key (`docs.overview`).
- [x] Explicit non-goals / limitations: this task only adds read-contract wiring and minimal in-memory catalog data; no persistence/search-index integration, no authoring/write endpoints, and no dynamic coverage computation from full docs ingestion pipeline.

### 2026-03-02 — Session 15 Task 2: Define Repo Content Contract and Validators

Pre-Execution Goal Articulation (STAR):
- Situation: Runtime docs tables/models and read routes already exist, but there is no canonical repo contract config, no strict parser/validator for markdown front matter or tooltip YAML fragments, and no `docs:validate` command. Current docs root contains many files outside the new contract layout, so validation must scope to explicit configured directories only.
- Task: Introduce strict contract enforcement for repo-based docs content under `docs/product/**`, `docs/api/**`, and `docs/tooltips/**`, with deterministic validation errors for malformed front matter and tooltip schema violations, and with test-backed behavior.
- Action: Add failing unit tests first; add `config/documentation.php` and `docs/README.md`; implement parser/validator classes under `app/Support/Documentation/Ingestion` and `app/Support/Documentation/Schemas`; add `app/Console/Commands/DocsValidateCommand.php`; add minimal conforming fixture content under contract directories; run required tests and command.
- Result: `php artisan test --filter=DocsContractValidationTest` and `php artisan docs:validate` pass with no validation errors on in-repo contract files; tests prove strict rejection of malformed/invalid cases.

Assumptions and Scope Boundaries:
- [x] English-only locale enforcement is phase-1 strict (`en` only).
- [x] Validation is file-contract-only; no runtime upsert/model persistence changes in this task.
- [x] Existing non-contract docs outside configured directories are out of scope for this validator.
- [x] Contract directories are the only source scanned by validator.

Failure Modes to Defend:
- [x] Malicious-caller: malformed YAML front matter, invalid types (e.g. scalar where array required), unknown severity, unsupported locale/domain, invalid/unsafe links, non-http(s) URLs.
- [x] Tired-maintainer: missing required metadata (`owner`, review fields), accidental typo in keys, linkage arrays provided as strings, overly long tooltip short text.

Implementation Checklist:
- [x] Add unit test file `tests/Unit/Documentation/DocsContractValidationTest.php` with failing cases first.
- [x] Run `php artisan test --filter=DocsContractValidationTest` and capture failing output.
- [x] Add `config/documentation.php` (paths, required metadata, allowed domains/severity, locale defaults, allowed link hosts).
- [x] Add canonical contract documentation in `docs/README.md`.
- [x] Implement markdown front matter parser + validation under `app/Support/Documentation/Ingestion/` and `app/Support/Documentation/Schemas/`.
- [x] Implement tooltip YAML parser + schema validation under `app/Support/Documentation/Ingestion/` and `app/Support/Documentation/Schemas/`.
- [x] Add `app/Console/Commands/DocsValidateCommand.php`.
- [x] Add minimal conforming files under `docs/product/**`, `docs/api/**`, `docs/tooltips/**` so command can pass in this repo.
- [x] Re-run `php artisan test --filter=DocsContractValidationTest` until green.
- [x] Run `php artisan docs:validate` and confirm success output.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - `php artisan test --filter=DocsContractValidationTest` initially failed with missing class (`DocsContractValidator`), then passed after implementation (`6 passed, 14 assertions`).
  - `php artisan docs:validate` passed with summary: `Validated markdown files: 2, tooltip files: 1, tooltip fragments: 1`.
  - `php artisan test --filter=Documentation` passed (`27 passed, 78 assertions`) to confirm no regressions in existing docs feature/schema tests.
- Conditions where this works:
  - Contract source files live only under configured directories: `docs/product/**`, `docs/api/**`, `docs/tooltips/**`.
  - Markdown files start with valid YAML front matter and include all required fields.
  - Tooltip YAML fragments follow the required shape with approved severities/domains and `metadata.locale=en`.
- Explicit non-goals / limitations:
  - No runtime upsert/sync into `DocumentationEntry`/`DocumentationFragment` models in this task.
  - No localization support beyond strict phase-1 `en`.
  - Validator does not scan legacy docs outside configured contract directories.

### 2026-03-02 — Codex Build Evidence False Negative (Task 2 run #2099)

- [x] Reproduce and document the evidence mismatch using run #2099 event shape (`file_change` + verification commands).
- [x] Update Codex evidence collector to treat `item.type=file_change` paths in implementation roots as valid implementation mutations.
- [x] Keep strict gate requiring both implementation evidence and verification command evidence.
- [x] Add regression test for `file_change`-driven edits + `php artisan test` verification to ensure task completes.
- [x] Run targeted tests and capture exact pass output.

Review (to complete after implementation):
- Root cause: `ExecuteInterrogationBuildJob::collectCodexExecutionEvidence()` only counted mutation *commands* and ignored `item.type=file_change` events. Run `#2099` performed real edits via `file_change` plus verification commands, so implementation mutation count stayed zero and the task was falsely failed.
- What changed: Added `file_change` path extraction and implementation-path classification to evidence collection. `has_actionable_execution` now requires `(implementation mutation commands + implementation file changes) > 0` plus verification commands. Added regression test `test_codex_success_with_file_change_events_and_verification_evidence_completes_task`.
- Verification commands/results: `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php` passed (`12 passed, 81 assertions`), including the new file-change regression.
- Conditions for correctness: Run events must include either command mutation evidence or `file_change` entries touching implementation roots (`app`, `database`, `config`, `routes`, `resources`, `tests`, `scripts`, `docs`) and at least one recognized verification command (`php artisan test`, `composer test`, `phpunit`, `pest`, `npm test`, `vitest`, `jest`, `pytest`).
- Explicit non-goals: This fix does not loosen evidence policy for planning-only runs and does not infer verification from narrative text alone.

### 2026-03-02 — Codex Build Revalidation False Negative (Task 2 run #2102)

- [x] Reproduce the rerun edge case where task implementation already exists and run performs verification-only checks.
- [x] Extend evidence policy with strict revalidation path: no new mutations + successful verification commands + explicit already-implemented signal.
- [x] Persist execution evidence on successful codex runs for observability/debugging parity with failure paths.
- [x] Add regression tests for both positive revalidation and negative verification-only-without-signal behavior.
- [x] Run targeted unit suite and capture output.

Review:
- Root cause: Evidence gate required per-run implementation mutations, so reruns that correctly verified already-implemented work were incorrectly failed.
- What changed:
  - `ExecuteInterrogationBuildJob` now accepts a revalidation path when:
    - implementation mutation count is zero,
    - verification evidence is present and successful (with fallback when completion telemetry is absent),
    - agent output explicitly signals already-implemented/existing state.
  - Execution evidence is now persisted on both success and failure outcomes.
- Verification:
  - `php artisan test tests/Unit/ExecuteInterrogationBuildJobTest.php` => `14 passed (87 assertions)`.
- Conditions for correctness:
  - Still requires verification commands for codex build success.
  - Planning-only runs with no implementation/revalidation evidence continue to fail.
- Explicit non-goals:
  - No retroactive reclassification of previously failed tasks was implemented here.

### 2026-03-02 — Session 15 Task 2 Revalidation (Task run #2103)

- [x] Re-audit docs contract implementation against task scope (contract config, parser/schema validators, docs command, contract docs).
- [x] Re-run required verification commands from task instructions.

Review:
- Verification:
  - `php artisan test --filter=DocsContractValidationTest` => passed (`6 passed, 14 assertions`).
  - `php artisan docs:validate` => passed (`Validated markdown files: 2, tooltip files: 1, tooltip fragments: 1`).
- Scope confirmation:
  - Validation enforcement remains limited to `docs/product/**`, `docs/api/**`, and `docs/tooltips/**`.
  - Runtime model upsert/sync remains intentionally out of scope for this task.

### 2026-03-02 — Session 15 Task 7: Ship Docs Center Pages and Navigation Discoverability

Pre-Execution Goal Articulation (STAR):
- Situation: Docs web routes (`/docs`, `/docs/{slug}`), `DocsPageController`, docs Inertia pages (`Docs/Index`, `Docs/Show`), and authenticated nav links in `AppLayout.vue` already exist in the repository. A dedicated feature test file (`DocsNavigationTest`) also exists and currently passes. Remaining risk is proving discoverability/reachability with explicit in-app link target assertions and producing fresh verification evidence for this task run.
- Task: Ensure authenticated users can discover and reach docs in-app without direct URL, `/docs/{slug}` resolves from a docs index link target, and edge paths (empty dataset, unknown slug, guest access) remain correct, with command-level verification evidence.
- Action:
  1. Update `tests/Feature/Documentation/DocsNavigationTest.php` first to explicitly prove in-app link target reachability and empty dataset behavior.
  2. Run `php artisan test --filter=DocsNavigationTest` to verify expected behavior for the updated suite.
  3. Confirm docs UI shell + nav wiring remains correct in `DocsPageController`, `resources/js/Pages/Docs/{Index,Show}.vue`, and `resources/js/Layouts/AppLayout.vue`.
  4. Run `npm run build` for frontend validation.
  5. Record evidence, conditions for correctness, and explicit non-goals.
- Result: Task is complete when updated docs navigation tests pass, frontend build passes, nav discoverability is explicitly covered by test assertions, and review notes capture exact command outputs and boundaries.

Assumptions and Scope Boundaries:
- [x] Existing authenticated layout (`AppLayout.vue`) is the shared shell for docs-visible users.
- [x] Task scope is limited to docs center UI shell and authenticated nav discoverability; no tooltip integration.
- [x] Docs catalog may return empty results and index page must still render successfully.

Failure Modes to Defend:
- [x] Malicious-caller: unauthenticated access to docs routes (must redirect to login).
- [x] Tired-maintainer: accidental nav link removal or mismatch between in-app link target and docs show route.
- [x] Runtime edge: unknown slug should return 404, and empty dataset should not crash docs index.

Implementation Checklist:
- [x] Update `tests/Feature/Documentation/DocsNavigationTest.php` first with explicit link-target reachability and empty dataset coverage.
- [x] Run `php artisan test --filter=DocsNavigationTest` and capture result.
- [x] Validate docs controller/page/layout wiring still matches task scope.
- [x] Run `npm run build` and capture result.
- [x] Add review section with verification evidence, conditions where this works, and explicit non-goals.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - `php artisan test --filter=DocsNavigationTest` => passed (`6 passed, 47 assertions`), including new coverage for empty docs dataset and docs show link-target contract in `Docs/Index.vue`.
  - `npm run build` => passed for both client and SSR builds; only non-blocking bundle-size/import warnings were emitted.
- Conditions where this works:
  - Authenticated users render the shared `AppLayout.vue` navigation shell (desktop + responsive) where `route('docs.index')` is present.
  - Docs index/show routes remain named `docs.index` and `docs.show`, and `DocsPageController` continues to render Inertia `Docs/Index` + `Docs/Show`.
  - Docs catalog entries expose valid `slug` values for index-to-show navigation.
- Explicit non-goals / limitations:
  - No tooltip component/runtime integration was added in this task.
  - This task does not introduce docs authoring, sync orchestration, or search UX changes beyond existing behavior.
  - Frontend verification is build-level; no browser E2E/visual verification was added in this run.

### 2026-03-02 — Session 15 Task 8: Implement reusable HelpHint component and tooltip registry resolution

Pre-Execution Goal Articulation (STAR):
- Situation: Docs fragment read API route exists at `GET /agent/api/v1/docs/fragments/{uiKey}` but currently resolves from static `DocsCatalog` data and has no reusable lookup service, no structured telemetry for missing tooltip keys, and no shared `HelpHint` component or frontend component-test harness.
- Task: Deliver a reusable `HelpHint` component plus backend `TooltipRegistryService` resolution path that supports API fallback, silent miss behavior for users, feature-flag-aware fragment availability, and structured telemetry for missing keys.
- Action:
  1. Add/adjust backend feature tests for fragment API + registry miss/disabled paths and telemetry assertions.
  2. Add frontend component tests (keyboard focus/dismiss, ARIA labels, mobile tap fallback, timeout/missing-key silent behavior) using a JS unit-test harness.
  3. Implement `TooltipRegistryService`, telemetry service hook, and controller wiring.
  4. Implement `resources/js/Components/HelpHint.vue` with short/long text, severity variants (`info|warning|risk`), optional learn-more link, and graceful empty render behavior.
  5. Run targeted PHP tests, JS tests, and `npm run build`; capture exact results.
- Result: Task is complete when tests prove accessible interactions and silent fallback paths, API lookup behavior is service-based with telemetry on misses, and verification commands pass.

Assumptions and Scope Boundaries:
- [x] Stable `ui_key` values are passed to the component.
- [x] Fragment API endpoint remains authenticated and route shape remains `/agent/api/v1/docs/fragments/{uiKey}`.
- [x] Scope is limited to shared component + backend lookup service + telemetry hook; no broad page rollout.

Failure Modes to Defend:
- [x] Malicious-caller: malformed or unknown `ui_key` probes should not surface internal errors.
- [x] Tired-maintainer: missing tooltip key should not break UI and should still emit structured telemetry for follow-up.
- [x] Runtime edge: feature-flag-disabled fragments and API timeouts must degrade silently (no user-visible crash/error message).
- [x] Accessibility edge: keyboard-only users must be able to focus/open and dismiss tooltip content.

Implementation Checklist:
- [x] Add failing backend tests in `tests/Feature/Documentation/TooltipFragmentApiTest.php`.
- [x] Add failing frontend tests in `resources/js/Components/__tests__/HelpHint.spec.ts`.
- [x] Run tests and confirm initial failures for intended reasons.
- [x] Implement `app/Support/Documentation/TooltipRegistryService.php` + telemetry hook and wire controller.
- [x] Implement `resources/js/Components/HelpHint.vue`.
- [x] Re-run relevant PHP and JS tests, then run `npm run build`.
- [x] Document review with exact evidence, conditions for correctness, non-goals, and limitations.

Review (to complete after implementation):
- [x] Evidence summary with exact command results.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.

Review:
- Evidence summary:
  - Red phase:
    - `php artisan test --filter=TooltipFragmentApiTest` initially failed (`Expected 200 got 404` for DB fragment lookup and missing telemetry mock expectations unmet), proving the old controller/static catalog path did not satisfy requirements.
    - `npm run test:unit -- HelpHint` initially failed because `HelpHint.vue` did not exist.
  - Green phase:
    - `php artisan test --filter=TooltipFragmentApiTest` => passed (`3 passed, 13 assertions`).
    - `php artisan test --filter=DocsAuthorizationTest` => passed (`9 passed, 18 assertions`), confirming docs web/API auth and unknown-key contract remain valid.
    - `php artisan test --filter=DocsSearchApiTest` => passed (`4 passed, 15 assertions`), confirming no docs search regression.
    - `npm run test:unit -- HelpHint` => passed (`5 passed`).
    - `npm run build` => passed for client + SSR builds.
- Conditions where this works:
  - Tooltip fragments exist in `documentation_fragments` with `status='published'` and a non-empty `short_text`, or are available in the fallback `DocsCatalog`.
  - If a fragment defines `feature_flag`, resolution depends on `FeatureFlagManager::enabled(...)`; disabled flags are treated as silent misses.
  - `HelpHint` receives either inline content (`shortText`) or a stable `uiKey` where `/agent/api/v1/docs/fragments/{uiKey}` is reachable for authenticated users.
  - Silent miss behavior applies to missing keys, disabled fragments, API non-OK responses, and network/timeout errors.
- Explicit non-goals / limitations:
  - No mass rollout across pages was performed in this task; only the reusable component and backend lookup path were implemented.
  - API miss contract remains `404 NOT_FOUND`; silent behavior is enforced in component rendering and telemetry, not by changing HTTP semantics.
  - No end-to-end browser flow or visual regression suite was added; verification is feature tests + component unit tests + production build.

### 2026-03-02 — Session 15 Task 9: Integrate HelpHint across required product surfaces

Pre-Execution Goal Articulation (Required):
- SITUATION: `HelpHint` component + fragment API exist, but required product surfaces do not yet consistently expose a discoverable docs entry point. There is no dedicated feature test enforcing route-by-route discoverability coverage for the required surfaces list.
- TASK: Every mandatory surface route in scope (Dashboard, Jobs, Monitor, Messenger, Discovery, Backups, Feature Flags, Memory, Delegation, Org, Profile/Security/Account, API/token/integration flows) must render at least one discoverable docs entry point (`HelpHint`, helper/docs link), and this must be enforced by a feature test.
- ACTION:
  1. Add `tests/Feature/Documentation/HelpHintSurfaceCoverageTest.php` first with route/component coverage assertions and docs-entry-point checks.
  2. Run the test and confirm failures for missing surfaces.
  3. Add `HelpHint` to required page headers/sections in `resources/js/Pages/**` with stable `ui_key` values and learn-more links.
  4. Remove duplicated inline helper strings only where directly replaced by HelpHint.
  5. Re-run the coverage test and `npm run build`.
- RESULT: Task is complete when coverage assertions pass for all required surfaces and frontend build succeeds, with explicit evidence captured below.

Assumptions and Scope Boundaries:
- [x] Scope is integration coverage for required surfaces only; no new docs ingestion architecture changes.
- [x] Route coverage includes feature-flagged surfaces by enabling required flags in test setup.
- [x] Discoverability assertion accepts `HelpHint` or explicit docs link/helper marker in each mapped page.

Failure Modes to Defend:
- [x] Stale route names causing false coverage confidence.
- [x] Feature-flag-gated surfaces hidden unexpectedly during tests.
- [x] Missing tooltip fragment content causing HelpHint to disappear (mitigated via inline short text + ui_key binding).
- [x] Header helper text causing layout overflow on narrow/mobile viewports.

Implementation Checklist:
- [x] Add failing coverage test in `tests/Feature/Documentation/HelpHintSurfaceCoverageTest.php`.
- [x] Run `php artisan test --filter=HelpHintSurfaceCoverageTest` and confirm intended failures.
- [x] Integrate `HelpHint` into required Vue pages with stable `ui_key` and docs learn-more links.
- [x] Re-run `php artisan test --filter=HelpHintSurfaceCoverageTest` and confirm pass.
- [x] Run `npm run build` and confirm pass.
- [x] Add review evidence, correctness conditions, and explicit non-goals.

Review:
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals and known limitations.
- Evidence summary:
  - Red phase: `php artisan test --filter=HelpHintSurfaceCoverageTest` failed (`1 failed`) with missing discoverable docs entry point on `Dashboard.vue`.
  - Green phase: `php artisan test --filter=HelpHintSurfaceCoverageTest` passed (`1 passed, 156 assertions`).
  - Frontend verification: `npm run build` passed for client + SSR builds (non-blocking bundle-size warnings only).
- Conditions where this works:
  - Required route map in `HelpHintSurfaceCoverageTest` remains aligned with actual named routes and page components.
  - Feature-gated surfaces are enabled in test context (`delegation.ui_enabled=true`, `agent.org.enabled=true`).
  - Each covered page continues to include either `<HelpHint` or a docs-link marker and avoids removing those entry points during refactors.
  - Inline `short-text` + `ui-key` bindings are kept so discoverability does not depend on fragment API availability.
- Explicit non-goals and known limitations:
  - This task does not add full tooltip fragment YAML coverage for every new `ui_key`; hints currently rely on inline short text with docs links.
  - Coverage is route/page-level for required surfaces; it does not assert every nested subview/form field within each area.
  - No end-to-end mobile visual regression suite was added; layout safety is validated by build + component placement patterns.

### Session 15 Task 11 — Implement docs telemetry, auditing, and diagnostics endpoint (Completed)

Pre-Execution Goal Articulation

SITUATION
- Docs telemetry currently only logs tooltip misses directly via `DocsTelemetryService::recordTooltipMiss` and does not emit domain events.
- Search-unavailable paths return a stable API error contract but do not emit dedicated telemetry or counters.
- Sync command reports console output but does not record success/failure counters for operator diagnostics.
- No docs diagnostics endpoint exists for operators to inspect telemetry counters/recent failure signals.
- No dedicated `docs` logging channel exists yet in `config/logging.php`.

TASK
- Add structured docs observability for tooltip missing-key events, search-unavailable events, and sync outcomes with counters.
- Add event/listener/job wiring under documentation namespaces for non-blocking telemetry persistence.
- Add docs diagnostics endpoint with operator-only authorization restrictions.
- Add docs logging channel and docs audit writes aligned with current `AgentAuditLog` conventions.

ACTION
- [x] Add failing tests first:
  - `tests/Unit/Documentation/DocsTelemetryServiceTest.php`
  - `tests/Feature/Documentation/DocsDiagnosticsTest.php`
- [x] Verify red state via targeted PHPUnit filter runs.
- [x] Implement telemetry infrastructure:
  - extend `DocsTelemetryService` for missing-key/search-unavailable/sync-outcome instrumentation and counters,
  - add event classes under `app/Events/Documentation/`,
  - add listener(s) under `app/Listeners/Documentation/`,
  - add telemetry persistence job(s) under `app/Jobs/Documentation/`.
- [x] Add diagnostics endpoint controller at `app/Http/Controllers/Docs/DiagnosticsController.php` and register API route with authorization gate.
- [x] Add docs logging channel in `config/logging.php`.
- [x] Wire audit writes for telemetry events using existing audit conventions.
- [x] Run verification commands:
  - `php artisan test --filter=DocsTelemetryServiceTest`
  - `php artisan test --filter=DocsDiagnosticsTest`
  - `php artisan test --filter=Documentation`
- [x] Confirm structured log output for simulated missing-key and search-outage scenarios.

RESULT
- Completion is proven by fail-then-pass evidence for new tests, passing documentation test slice, diagnostics auth enforcement, and structured docs telemetry logs with corresponding counters/audit records.

Assumptions and scope boundaries
- Assumption: existing docs auth policy (`admin` / `analytics`) remains the operator role baseline for diagnostics.
- Assumption: telemetry persistence may rely on queue workers in runtime; request-path instrumentation must remain resilient if queue/persistence is degraded.
- Scope boundary: telemetry + diagnostics only; no changes to docs sync semantics beyond telemetry emission.

Failure modes to guard
- Malicious-caller mode: unauthorized access to diagnostics endpoint, high-volume repeated missing-key spam.
- Tired-maintainer mode: telemetry backend/storage failure causing request crashes, queue listener failures silently dropping observability.

Review
- [x] Evidence summary with exact command outputs.
- [x] Conditions where this works.
- [x] Explicit non-goals / limitations.
- Evidence summary:
  - Red state:
    - `php artisan test --filter=DocsTelemetryServiceTest` failed with missing event dispatches and undefined telemetry methods (`recordSearchUnavailable`, `recordSyncOutcome`).
    - `php artisan test --filter=DocsDiagnosticsTest` failed with missing route (`404`) and undefined telemetry methods.
  - Green state:
    - `php artisan test --filter=DocsTelemetryServiceTest` passed (`3 passed, 7 assertions`).
    - `php artisan test --filter=DocsDiagnosticsTest` passed (`3 passed, 11 assertions`).
    - `php artisan test --filter=Documentation` passed (`58 passed, 370 assertions`).
  - Structured log verification:
    - `storage/logs/docs-2026-03-02.log` contains structured `documentation.telemetry` warning entries for:
      - `documentation.tooltip.miss` (missing key),
      - `documentation.search.unavailable` (search outage),
      - `documentation.sync.outcome` (success/failure outcomes).
- Conditions where this works:
  - API diagnostics endpoint requires authenticated user plus `admin` or `analytics` role via `view-docs-diagnostics` gate.
  - Telemetry counters/recent failures persist through `AgentSystemState` and diagnostics snapshot reads from that store.
  - Event listeners dispatch persistence jobs on `agent` queue; request-path counters still record even if queued persistence is degraded.
  - Docs logging channel (`docs`) is enabled and writable in target environment.
- Explicit non-goals / limitations:
  - No changes were made to docs sync semantics beyond telemetry emission.
  - No additional retry/backoff orchestration was added for queue execution beyond existing queue behavior.
  - Existing framework-level duplicate event listener registration in this app is mitigated for docs telemetry via listener-level dedupe rather than global event-system reconfiguration.

### 2026-03-02 — Docs UI Runtime Source Fix

- [x] Diagnose why `/docs` only showed two static cards after docs sync.
- [x] Switch docs web pages to prefer runtime DB entries (`documentation_entries`) with fallback to static catalog only when DB is empty.
- [x] Render `entry.body_html` in docs show page for actual document content visibility.
- [x] Sync docs to runtime DB and verify counts.
- [x] Verify docs navigation/auth tests after controller changes.

Review:
- Root cause: `DocsPageController` was hardwired to `DocsCatalog` (2 static entries), so UI never reflected synced runtime docs records.
- Verification:
  - `php artisan docs:sync --mode=commit --source=repo` => `Entries: 12 | Fragments: 14 | Links: 61`
  - Runtime counts: `entries=12, fragments=14, links=61`
  - `php artisan test tests/Feature/Documentation/DocsNavigationTest.php tests/Feature/Documentation/DocsAuthorizationTest.php` => `16 passed`.
- Note: `php artisan docs:openapi:ingest` still fails until OpenAPI operations include `x-linked-doc-slugs` metadata for each operation.

### 2026-03-02 — Docs UX and Coverage Remediation (User Correction)

Pre-Execution Goal Articulation (STAR):
- Situation: Docs runtime contract existed, but user-facing docs experience remained poor (minimal content, no practical search/sidebar workflow, broken Learn More behavior in helper tooltips).
- Task: Deliver a usable internal docs center with rich product/API docs coverage, sidebar navigation + right-hand markdown reader, working search/filter flow, and reliable Learn More navigation.
- Action:
  1. Upgrade docs pages (`Docs/Index`, `Docs/Show`) to sidebar/search/filter + full markdown reading pane.
  2. Fix `HelpHint` Learn More interaction and focus/blur behavior.
  3. Add runtime docs bootstrap to auto-sync docs when DB docs/fragments are missing.
  4. Expand product/API docs content and add comprehensive API route inventory/reference docs.
  5. Re-run docs validation, sync, coverage, docs feature/unit tests, component tests, and build.
- Result: Docs center now renders rich markdown content from runtime docs, supports search/filter workflows, covers major product surfaces + API inventory, and tooltip Learn More links resolve reliably.

Implementation Checklist:
- [x] Add runtime bootstrap service for missing docs datasets.
- [x] Integrate bootstrap into docs page controller and tooltip registry resolution.
- [x] Replace docs index/show UI with sidebar + right-pane markdown view + search/filter controls.
- [x] Harden `HelpHint` interaction for Learn More navigation.
- [x] Add missing jobs/product docs and expanded app/API references.
- [x] Validate and sync docs (`docs:validate`, `docs:sync`, `docs:coverage`).
- [x] Re-run docs feature/unit tests + HelpHint component test + production build.

Review:
- Evidence summary:
  - `php artisan docs:validate` => passed (`Validated markdown files: 17, tooltip files: 2, tooltip fragments: 14`).
  - `php artisan docs:sync --mode=commit --source=repo` => passed (`Entries: 17 | Fragments: 14 | Links: 109`).
  - `php artisan docs:coverage --fail-on-missing` => passed (`Coverage: 100.00% (12/12 surfaces)`).
  - `php artisan test tests/Feature/Documentation tests/Unit/Documentation` => passed (`59 passed, 398 assertions`).
  - `npm run test:unit -- resources/js/Components/__tests__/HelpHint.spec.ts` => passed (`5 passed`).
  - `npm run build` => passed (client + SSR).
- Conditions where this works:
  - Docs markdown and tooltip YAML remain contract-compliant and synced to runtime (`docs:sync`).
  - Runtime docs tables are writable so first-load bootstrap can hydrate missing datasets.
  - Authenticated users can access `/docs` and `/docs/{slug}` with Inertia rendering.
- Explicit non-goals / limitations:
  - This pass does not add WYSIWYG authoring workflows.
  - Search in docs UI currently uses docs page query/filter flow rather than a dedicated live-search component.
  - API route inventory is generated from current route registration snapshot and should be regenerated when API surface changes.
