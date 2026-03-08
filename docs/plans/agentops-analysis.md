# Implementation Plan

Derived from discovery session 6.

# AgentOps Full Discovery Audit — Implementation Plan

## Phase 0: Prerequisite Tooling Installation

### 0.1 PCOV PHP Extension
- Check if PCOV is already installed via `php -m | grep pcov`
- If missing, install via `pecl install pcov` or OS package manager
- Verify `php artisan test --coverage` succeeds after installation
- Dependency: Blocks all coverage metric capture in Phase 5

### 0.2 PHPStan/Larastan Installation
- `composer require --dev phpstan/phpstan larastan/larastan`
- Create `phpstan.neon` at project root configured for level 5
- Include paths: `app/`, `config/`, `database/`, `routes/`
- Run `./vendor/bin/phpstan analyse` and capture violation count
- Dependency: Blocks Phase 6.1 compliance checks

### 0.3 ESLint v10 Flat Config
- `npm install --save-dev eslint@latest @eslint/js`
- Create `eslint.config.js` at project root with Vue + TypeScript support
- Run `npx eslint resources/` and capture violation count
- Dependency: Blocks Phase 6.2 compliance checks

### 0.4 Bundle Size Analysis Tooling
- `npm install --save-dev rollup-plugin-visualizer`
- Configure in `vite.config.js` (or `.ts`) as a plugin
- Run `npm run build` with visualizer enabled, capture output
- Dependency: Blocks Phase 4.4 frontend performance metrics

---

## Phase 1: Architecture Mapping

### 1.1 Directory Structure Audit
- Read and document top-level directory layout via filesystem listing
- Map app layer separation: enumerate `app/Models/`, `app/Services/`, `app/Actions/`, `app/Jobs/`, `app/Events/`, `app/Listeners/`, `app/Http/Controllers/`
- Document frontend structure: `resources/js/Components/`, `resources/js/Pages/`, `resources/js/Layouts/`, `resources/js/Composables/`
- Enumerate config files: `config/agent.php`, `config/horizon.php`, `config/memory.php`, `config/database.php`, and all others
- Document queue worker setup from `config/horizon.php` — all 4 supervisors
- Read `app/Console/Kernel.php` or `routes/console.php` for scheduled task registration
- Document memory layer structure: `MemoryFormationPipeline.php`, `MemoryFormationJob.php`, `Neo4jGraphStore.php`, `MemoryContextBuilder.php`, `MemoryFormationResult.php`
- Confirm no Python/FastAPI layer exists (grep for `.py` files, `requirements.txt`, `pyproject.toml`)

### 1.2 Dependency Graph
- Read `app/Providers/AppServiceProvider.php` and all other service providers in `app/Providers/`
- Map Job → Queue channel assignments by grepping `$queue` property and `onQueue()` calls across all 44 jobs
- Document Event → Listener relationships from `EventServiceProvider` or event discovery
- Map external API integrations: grep for Guzzle HTTP client instantiations, API base URLs, provider adapter classes
- Read `composer.json` and `package.json` — list all Spatie and third-party packages with versions
- Verify `composer.lock` and `package-lock.json` are committed (confirmed present from discovery scan)
- Run `composer audit` — capture output verbatim, flag any high/critical vulnerabilities as findings
- Run `npm audit` — capture output verbatim, flag any high/critical vulnerabilities as findings

### 1.3 Data Model Map
- Enumerate all 78 Eloquent models from `app/Models/`
- For each model: read file, document relationships (`hasMany`, `belongsTo`, `morphTo`, `belongsToMany`, etc.)
- Identify all polymorphic relationships and their morph maps
- Cross-reference with migrations in `database/migrations/` for missing FK indexes
- Scan every model for `$guarded` vs `$fillable` usage — collect all `$guarded = []` instances with exact file:line
- Verify DelegateeProfile: confirm `trust_score` (decimal:2), `trust_updated_at` (datetime), missing `capability_profile` JSON field, `soul_json` field present

### 1.4 API Surface Audit
- Run `php artisan route:list --json` — capture full route table
- Identify versioning strategy: grep for `/api/v1/`, `/api/v2/` patterns in `routes/api.php`
- Flag any unversioned API endpoints
- For each route, check middleware stack for auth middleware (`auth`, `auth:sanctum`, etc.)
- Classify routes as legitimately public vs potentially missing auth — flag uncertain cases explicitly
- Check for rate limiting middleware (`throttle:`) on all routes, especially AI-facing endpoints
- Grep for OpenAPI/Swagger documentation files or annotations

### 1.5 AI Integration Mapping
- Identify AI provider integrations by searching for Guzzle calls to Anthropic, OpenAI, Gemini API endpoints
- Confirm `laravel/ai` is not installed (not on Packagist — document as P2)
- Confirm `laravel/mcp` is not installed (document as P2)
- Confirm `laravel/boost` is not installed (document as P2)
- Map AI-facing endpoints and their rate limiting configuration
- Search for SSE streaming endpoints: grep for `eventStream`, `StreamedResponse`, `text/event-stream`
- Document MCP server configurations if any exist in config files

---

## Phase 2: Code Quality Analysis

### 2.1 SOLID Violations
- Identify God classes: scan all controllers and services for files exceeding 300 lines; read and assess responsibility count
- Check for direct concrete class instantiation vs interface injection: grep for `new ClassName()` in controllers and services where DI should be used
- Scan inheritance hierarchies for Liskov violations: check any abstract class extensions for contract violations
- Check for fat interfaces: identify interfaces with 5+ methods where implementors leave methods empty
- Assess coupling between modules: check for cross-domain service imports (e.g., Job services importing Controller-layer classes)
- Scan all 77 controllers for business logic that should be in Actions/Services: any controller method with DB queries, complex conditionals, or multi-step operations

### 2.2 DRY Violations
- Search for duplicate validation rules across Form Requests and controllers: grep for repeated `'required|string'` patterns
- Identify repeated query patterns: look for identical `where()` chains that should be Eloquent scopes
- Check Vue components for copy-pasted markup/logic that should be shared composables or components
- Scan for duplicated configuration values across config files
- Check for repeated error handling boilerplate in jobs and services
- Search for duplicate prompt templates or AI interaction patterns across jobs and services

### 2.3 Clean Code Issues
- Grep for `dd(`, `var_dump(`, `dump(`, `console.log(` — flag any found with file:line
- Grep for commented-out code blocks (lines starting with `//` followed by code patterns, or `/* */` blocks)
- Identify functions > 30 lines: use line counting on all methods in services, jobs, controllers
- Check for deeply nested conditionals (> 3 levels of indentation in conditional blocks)
- Grep for magic numbers and strings not extracted to constants or enums
- Check for methods with > 5 parameters across all PHP classes
- Count all `TODO` and `FIXME` comments with file:line references; cross-reference with Linear tickets

### 2.4 Refactoring Candidates (Refactoring Guru Taxonomy)
- **Long Method → Extract Method**: Identify all methods > 30 lines with multiple logical sections
- **Large Class → Extract Class**: Identify all classes > 300 lines with multiple responsibility groups
- **Feature Envy → Move Method**: Find methods that primarily access data from another class
- **Shotgun Surgery → Consolidate**: Find changes that require touching multiple files for a single concept
- **Data Clumps → Extract Value Object/DTO**: Find groups of parameters/fields that travel together
- **Primitive Obsession → Typed Classes**: Find string/int usage where enums or value objects are appropriate
- **Missing Action Pattern → Extract to Actions**: Find controller methods with inline business logic
- Document each candidate with before/after description and exact file:line

---

## Phase 3: Security Audit

### 3.1 Authentication & Authorization
- Cross-reference route list with auth middleware — flag all unprotected non-public routes with file:line
- Check role/permission implementation: search for policy classes in `app/Policies/`, Gate definitions, middleware-based checks
- Review password reset flow: check token expiry, single-use enforcement in auth controllers
- Read `config/session.php` — verify hardened configuration (secure, httponly, samesite)
- Check Sanctum/Jetstream configuration: token scoping, revocation support
- Identify any routes with inconsistent auth approaches (middleware vs manual vs policy)

### 3.2 Input & Output
- Grep for `$request->all()` without `->only()` or `->validated()` — flag each with file:line
- Search for raw SQL queries with user input: `DB::raw(`, `DB::select(` with variable interpolation
- Check Blade templates for unescaped output: `{!!` syntax
- Check Vue templates for `v-html` usage without sanitization
- Review file upload handling: validation rules (type, size), storage paths (must not be public web root)
- Verify CSRF protection: check middleware stack for `VerifyCsrfToken` on all state-changing routes

### 3.3 AI-Specific Security (Separate Section — OWASP LLM 2025 + Agentic 2026)
- **LLM01:2025 Prompt Injection**: Audit all prompt construction in jobs — check for user input concatenation without sanitization
- **LLM02:2025 Sensitive Information Disclosure**: Check if AI responses are logged with PII; check if system prompts contain secrets
- **LLM07:2025 System Prompt Leakage**: Review `soul_json` handling in DelegateeProfile — check if system prompts are exposed via API responses
- **LLM08:2025 Vector/Embedding Poisoning**: Check if vector/embedding inputs are validated before storage
- **Agentic: Tool Misuse**: Check if agents confirm before irreversible actions (job deletions, data mutations)
- **Agentic: Privilege Escalation**: Verify no agent can escalate its own permission level in delegation chains
- **Agentic: Memory Poisoning**: Check memory formation pipeline for input validation on stored context
- **Agentic: Permission Attenuation**: Verify delegation chains validate permissions at each sub-delegation step
- Audit all Guzzle HTTP calls to AI providers: check for API key exposure, response validation, error handling
- Review `soul_json` attack surface: check if user-controllable fields can inject into system prompts

### 3.4 Supply Chain Security (OWASP A03:2025)
- Capture `composer audit` output — document all vulnerabilities by severity
- Capture `npm audit` output — document all vulnerabilities by severity
- Check GitHub Actions for SHA pinning: read `.github/workflows/docs-deploy-sync.yml` lines 25 and 28
- Scan `composer.json` and `package.json` for deprecated or unmaintained dependencies
- Verify lock files are committed and match installed packages

### 3.5 Infrastructure & Secrets
- Grep entire `app/` and `config/` for hardcoded secrets: `API_KEY`, `SECRET`, `PASSWORD`, `TOKEN` as string literals
- Read `.env.example` — verify completeness against all `env()` calls in config files
- Read CORS configuration in `config/cors.php` — verify restrictive settings
- Check for security headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options in middleware or server config
- Verify error handling in production: check `APP_DEBUG` default, exception handler configuration

---

## Phase 4: Performance & Scalability

### 4.1 Database
- Grep for `->get()` inside loops and `foreach` blocks without preceding `->with()` — N+1 query candidates
- Check if Laravel 12.8 automatic eager loading is enabled in config
- Cross-reference migration indexes with FK columns and frequently filtered columns
- Search for `->all()` on models with potentially large datasets
- Check pgvector index configuration in migrations (HNSW preferred)

### 4.2 Queue & Jobs
- Read all 44 job files: check for `$timeout`, `$tries`, `$backoff`, `$maxExceptions` properties
- Identify jobs missing `uniqueId()` that perform operations which shouldn't run concurrently
- Read `config/horizon.php` — verify all supervisor timeout values against engineering rules v2.0
- Specifically check `supervisor-long-running` timeout (must be 600s+ per engineering rules)
- Check for STAR preamble generation in dispatch pipeline
- Verify Horizon supervisor config has separate `supervisor-default` and `supervisor-long-running`
- Check for chunking on jobs that process large datasets

### 4.3 Cache
- Search for cache usage: grep for `Cache::`, `cache()`, `remember(`, `rememberForever(`
- Identify expensive queries or computations that lack caching
- Check Redis TTL configuration: verify TTLs set on all cache operations
- Verify Redis database separation: DB 0 (default), DB 1 (cache), DB 2 (memory)
- If minimal caching found: document P1 for missing strategy, P2 for specific opportunities

### 4.4 Frontend
- Run Vite build with rollup-plugin-visualizer — capture bundle size breakdown
- Check for lazy-loaded routes and components in Vue router
- Search for large dependency imports that could be tree-shaken
- Check for Tailwind v4 CSS-first configuration vs legacy `tailwind.config.js`
- Verify Vite configuration version and settings

### 4.5 AI API Costs
- Search for token usage tracking implementation: grep for token counting, cost calculation
- Check for cost anomaly alerting configuration
- Review model selection in AI provider calls — flag inappropriate model choices
- Check for batch processing usage where applicable
- Search for OpenLLMetry or equivalent LLM observability instrumentation
- If no token tracking found: record "Not tracked" in metrics, flag as P1

---

## Phase 5: Testing Coverage

### 5.1 Coverage Inventory
- Run `php artisan test --coverage` — capture line and branch coverage percentages
- Run `./vendor/bin/pest --mutate` — capture mutation score
- Identify zero-coverage files in `app/Services/`, `app/Jobs/`, `app/Actions/`
- Check for feature tests on all API endpoints by cross-referencing route list with test files
- Run Vitest coverage for Vue components — capture results
- Count test files by directory to map coverage distribution

### 5.2 Test Quality
- Sample test files for assertion quality: check for behavior-based assertions vs implementation-mocking
- Verify database factories exist for all 78 models (37 factories found — identify 41 missing)
- Check test configuration: `RefreshDatabase` or transaction usage
- Verify test database engine matches production (PostgreSQL, not SQLite)
- Check for Pest architecture testing presets (`php`, `security`, `laravel`)
- Check for co-located Vue component tests (`ComponentName.test.ts` next to `ComponentName.vue`)
- Review Playwright configuration for role-based locators vs CSS selectors

### 5.3 Testing Gaps vs Targets
- Compile results into target comparison table:
  - Line coverage: captured value vs 80% min / 90% target
  - Branch coverage: captured value vs 75% min / 85% target
  - Mutation score: captured value vs 60% min / 80% target
  - Type coverage: PHPStan level 5 result vs 90% min / 100% target

---

## Phase 6: Coding Standards Compliance

### 6.1 PHP / Laravel
- Grep all PHP files for `declare(strict_types=1)` — flag missing with file:line
- Run `./vendor/bin/pint --test` — capture PSR-12 violations
- Run PHPStan at level 5 — capture violation count and specific errors
- Search for string constants that should be enums
- Check for return types on all public methods (PHPStan will partially cover this)
- Search for empty constructors, unused `use` statements
- Check for PHP 8.4+ feature usage opportunities (property hooks, asymmetric visibility)

### 6.2 JavaScript / TypeScript / Vue
- Run ESLint — capture violation count and specific errors
- Check for Prettier configuration and formatting consistency
- Grep for `any` type usage in TypeScript files
- Verify Vue SFCs use `<script setup>` syntax
- Check for inline styles vs Tailwind/CSS variables
- Verify component prop type definitions

### 6.3 General Standards
- Count all `TODO` and `FIXME` comments — cross-reference each with Linear tickets
- Check README accuracy and currency
- Verify `.env.example` completeness
- Check for conventional commit enforcement (`@commitlint/cli` or equivalent)
- Check for pre-commit hooks (Husky + lint-staged)
- Verify all config is centralized in `bootstrap/app.php` (not legacy Http/Kernel)

---

## Phase 7: Architecture Gaps (AgentOps-Specific — Tomašev et al.)

### 7.1 Agent Orchestration Layer
- Verify DelegateeProfile has queryable `capability_profile` JSON field — flag missing as P1
- Confirm trust scores are persisted (`trust_score` decimal:2) and updated per-task (`trust_updated_at`)
- Check for agent registry queryability: search for registry service or query endpoints
- Audit job delegation chains for auditability: check logging of who delegated what to whom
- Check for permission attenuation at sub-delegation boundaries
- Search for delegation contracts with verification mechanisms

### 7.2 Memory Layer
- Verify core memory (editable agent context blocks) implementation
- Verify working memory (session-scoped buffers) implementation
- Check long-term memory store: pgvector availability, fallback to BM25
- Check delegation memory (multi-agent coordination state)
- Verify memory formation is async and non-blocking (MemoryFormationJob)
- Check privacy-aware access control on memory

### 7.3 STAR Preamble Pipeline
- Verify STAR generation in job dispatch pipeline
- Check if STAR output is logged and inspectable
- Verify recovery logic uses targeted re-prompting (not blind retry)
- Check if jobs declare `capabilities_required`, `estimated_duration`, `reversibility`

### 7.4 Monitoring & Observability
- Verify structured log events from jobs
- Check for OpenTelemetry agent execution traces
- Check for real-time job status via Reverb channels
- Verify Horizon failure rate monitoring
- Check for token usage/cost tracking via OpenLLMetry
- Check for Laravel Pulse configuration
- Check for Sentry error tracking

### 7.5 Laravel AI Integration
- Document `laravel/ai` absence (not on Packagist, Guzzle fallback) — P2
- Document `laravel/mcp` absence — P2
- Document `laravel/boost` absence — P2
- Check SSE streaming configuration for AI response delivery
- Review AI model selection per task criticality

---

## Phase 8: DevOps & Infrastructure

### 8.1 CI/CD Pipeline
- Read all files in `.github/workflows/` — document existing workflows
- Flag each missing CI stage as separate finding:
  - PHP tests workflow (P0)
  - PHPStan/Larastan workflow (P0)
  - Pint workflow (P0)
  - ESLint workflow (P0)
  - Vitest workflow (P0)
  - composer audit workflow (P0)
  - npm audit workflow (P0)
  - npm build workflow (P0)
- Check SHA pinning on `docs-deploy-sync.yml` lines 25 and 28 (P1)

### 8.2 Docker Configuration
- Read `docker-compose.yml` — document services (Neo4j, Typesense, etc.)
- Check for multi-stage production builds in any Dockerfiles
- Verify non-root user configuration
- Check health check definitions
- Search for Trivy scanning configuration

### 8.3 Deployment
- Check for zero-downtime deployment configuration (Forge, Laravel Cloud, Envoyer)
- Verify Horizon worker supervision with auto-restart
- Check for `horizon:terminate` in deploy scripts
- Verify database migration backward-compatibility strategy
- Check for Laravel Pennant feature flag configuration

### 8.4 Secrets Management
- Verify `.env` is in `.gitignore`
- Check for `php artisan env:encrypt` usage
- Scan GitHub Actions for secret handling patterns
- Verify no long-lived API keys in CI configuration

### 8.5 Monitoring & Alerting
- Check logging configuration: structured JSON logging for production (Monolog JsonFormatter)
- Check Sentry integration — flag as P0 if missing
- Check OpenTelemetry installation — flag as P1 if missing
- Check OpenLLMetry installation — flag as P1 if missing
- Check Laravel Pulse configuration
- Review alert design for noise filtering (422/404 exclusions, p95 latency triggers)

### 8.6 Service Communication
- Document inter-service communication patterns (Laravel ↔ Neo4j, Laravel ↔ Typesense)
- Check for gRPC configuration or viability assessment
- Check Meilisearch/Typesense configuration for full-text search of agent logs

---

## Phase 9: Report Assembly & Linear Integration

### 9.1 Linear Team/Project Lookup
- Query Linear API for "Agent Orchestration" team
- Query Linear API for "AgentOps" project
- If either lookup fails: set flag to skip Linear creation, document as blocker in report

### 9.2 Issue Register Compilation
- Compile all findings from Phases 1-8 into unified issue register table
- Assign sequential numbering, category, exact file:line, issue description, severity, effort, priority
- Ensure `$guarded = []` is a single grouped P0 finding listing all affected model file:lines
- Ensure CI stages are separate findings (8 individual P0 findings)
- Ensure observability findings are separate and tiered (Sentry P0, OpenTelemetry P1, OpenLLMetry P1)

### 9.3 Linear Issue Creation
- For each P0 finding: create Linear issue in Agent Orchestration / AgentOps with title, description (including file:line), priority mapping
- For each P1 finding: create Linear issue in Agent Orchestration / AgentOps with title, description (including file:line), priority mapping
- If Linear lookup failed in 9.1: skip all creation, document blocker

### 9.4 Report Generation
- Write `tasks/DISCOVERY_REPORT.md` with all 10 sections:
  1. Executive Summary (3-5 sentences)
  2. Architecture Overview (PHP-only memory layer, Neo4j, Typesense)
  3. Issue Register Table (all severities, file:line, severity, effort, priority)
  4. Refactoring Candidates (Refactoring Guru taxonomy, before/after)
  5. Security Vulnerabilities (OWASP 2025 categories)
  6. AI-Specific Security (OWASP LLM 2025 + Agentic 2026 — separate section)
  7. Gaps vs Engineering Rules v2.0
  8. Gaps vs Tomašev et al. Delegation Framework (all P1)
  9. Action Plan (P0/P1/P2 tiers)
  10. Metrics Baseline (auto-captured live values)
- Inject all captured metrics: coverage %, mutation score, PHPStan violations, ESLint violations, TODO/FIXME count, vulnerability counts, bundle size, AI token cost as "Not tracked"

### 9.5 Report Verification
- Verify every finding has exact file:line reference
- Verify DelegateeProfile findings: `$guarded = []` at line 21 in grouped P0, missing `capability_profile` as P1, `soul_json` as LLM07:2025 finding
- Verify all 10 sections are present and non-empty
- Verify Linear issues created (or blocker documented)
- Verify metrics baseline contains all required fields

## Sections

- Phase 0: Prerequisite Tooling Installation — PCOV, PHPStan/Larastan, ESLint v10, rollup-plugin-visualizer
- Phase 1: Architecture Mapping — Directory structure, dependency graph, data model map, API surface, AI integration mapping
- Phase 2: Code Quality Analysis — SOLID violations, DRY violations, clean code issues, refactoring candidates (Refactoring Guru taxonomy)
- Phase 3: Security Audit — Auth/authz, input/output validation, AI-specific security (OWASP LLM 2025 + Agentic 2026), supply chain (OWASP A03:2025), infrastructure/secrets
- Phase 4: Performance & Scalability — Database N+1/indexes, queue/jobs timeout compliance, cache strategy audit, frontend bundle size, AI API cost tracking
- Phase 5: Testing Coverage — Coverage inventory (line/branch/mutation/type), test quality assessment, gaps vs targets (80%/75%/60%/90% minimums)
- Phase 6: Coding Standards Compliance — PHP/Laravel (strict_types, PSR-12, PHPStan level 5), JS/TS/Vue (ESLint, Prettier, script setup), general (TODO/FIXME, commit hooks, config centralization)
- Phase 7: Architecture Gaps (Tomašev et al.) — Agent orchestration layer, memory layer, STAR preamble pipeline, monitoring/observability, Laravel AI integration gaps
- Phase 8: DevOps & Infrastructure — CI/CD pipeline (9 separate missing-stage findings), Docker configuration, deployment, secrets management, monitoring/alerting, service communication
- Phase 9: Report Assembly & Linear Integration — Linear team/project lookup, issue register compilation, P0/P1 Linear issue creation, report generation with all 10 sections, report verification


## Risks

- PCOV installation may fail if PHP extension compilation tools are missing or PHP version is incompatible — fallback to Xdebug coverage driver with degraded performance
- PHPStan at level 5 may produce an extremely high violation count on a 78-model/77-controller codebase that has never run static analysis, potentially requiring triage to identify actionable vs noise findings
- ESLint v10 flat config on an existing Vue/Inertia codebase without prior ESLint may surface hundreds of violations — initial baseline capture is for measurement, not remediation during discovery
- Mutation testing via pest --mutate on the full test suite may be prohibitively slow on a codebase with 78 models and 44 jobs — may need to scope to critical paths or sample
- Linear API lookup for 'Agent Orchestration' team or 'AgentOps' project may fail if names differ from expected — entire Linear issue creation phase would be skipped with blocker flag
- composer audit or npm audit may reveal critical vulnerabilities that cannot be remediated during discovery (read-only constraint) — creates tension between known-vulnerability documentation and no-update constraint
- rollup-plugin-visualizer installation may conflict with existing Vite configuration or require vite.config changes that technically constitute code changes — must be minimal and reversible
- The 78-model $guarded scan may find patterns beyond simple $guarded = [] (e.g., $guarded = ['id'] which is also problematic) — need clear criteria for what constitutes a finding vs acceptable usage
- Route auth classification using 'best judgment' for public vs protected routes introduces subjectivity — uncertain cases must be explicitly flagged rather than silently classified
- Neo4j and Typesense Docker services may not be running during discovery, limiting ability to verify memory layer integration points and search configuration
- The engineering rules v2.0 document may contain rules that conflict with each other or with Laravel 12 defaults — strict compliance interpretation may produce findings that are debatable
- AI-specific security audit of internal integration points (no HTTP endpoints) relies on code pattern analysis rather than runtime testing — some vulnerabilities may only be detectable at runtime


## Assumptions

- The project directory is /Users/garethdaine/Code/agent and all file paths are relative to this root
- PHP 8.3+ is installed and available in the shell with pecl/extension compilation capability for PCOV installation
- Node.js and npm are installed and functional for ESLint and rollup-plugin-visualizer installation
- Composer is installed and functional for PHPStan/Larastan installation
- The PostgreSQL database (pgsql_testing) is accessible for running php artisan test --coverage
- Redis is running and accessible for Horizon-dependent test execution
- The engineering rules v2.0 document exists at /Users/garethdaine/Code/agent/docs/refactoring/agent-ops-engineering-rules.md and is the authoritative compliance standard
- Linear API access is configured and authenticated — the MCP Linear tools are functional
- The codebase is a pure PHP/Laravel 12 + Vue/Inertia monolith with no Python/FastAPI components
- All 78 Eloquent models are in app/Models/ directory (not scattered across other namespaces)
- The Git working tree is clean and no uncommitted changes will interfere with tooling installation
- composer.lock and package-lock.json are committed and represent the current installed state
- The discovery is read-only for application code — only additive dev dependency installs are permitted
- laravel/ai is confirmed not available on Packagist — provider adapters use direct Guzzle HTTP clients
- pgvector extension availability is uncertain — the system has a BM25 keyword search fallback
- Docker Compose services (Neo4j, Typesense) configuration can be read from docker-compose.yml even if services are not running
- The Horizon configuration in config/horizon.php is the source of truth for supervisor timeout values

