# Requirements Discovery Summary

Session: 6

## AgentOps Full Discovery Audit — Implementation Summary

### Scope

Exhaustive, all-8-phase discovery audit of the AgentOps codebase covering architecture mapping, code quality, security, performance, testing, coding standards, AgentOps-specific architecture gaps (Tomašev et al. intelligent delegation framework), and DevOps/infrastructure. The codebase is a **PHP/Laravel 12 + Vue/Inertia monolith** — no Python/FastAPI layer exists. The memory system is implemented entirely in PHP/Laravel with Neo4j and Typesense as Docker services.

### Resolved Decisions

| Decision | Resolution |
|---|---|
| Severity threshold | All severities (CRITICAL, HIGH, MEDIUM, LOW) — exhaustive |
| Engineering rules v2.0 authority | Absolute compliance — every deviation from `/Users/garethdaine/Code/agent/docs/refactoring/agent-ops-engineering-rules.md` v2.0 is a finding |
| AI security posture | Separate dedicated section with own independent priority scale (OWASP LLM 2025 + Agentic 2026) |
| AI security internal scope | Audit internal AI integration points equally — prompt construction, Guzzle provider calls, job-based AI invocations, soul_json handling all in scope even if no HTTP-exposed AI endpoints exist |
| Linear ticket creation | Auto-create in **Agent Orchestration** team / **AgentOps** project for all P0 and P1 findings |
| Linear lookup fallback | If team/project not found via API, skip Linear creation and flag as a blocker in the report for manual resolution |
| Python/FastAPI layer | Does not exist — all Python audit items (Ruff, mypy, pytest, bandit) dropped from scope |
| Test coverage targets | 80% min / 90% target line; 75% min / 85% target branch; 60% min / 80% target mutation; 90% min / 100% target PHP type coverage |
| Tomašev et al. framework | Core requirement — all gaps are P1 findings |
| Evidence format | Exact `file:line` references for all findings regardless of severity |
| Audit command execution | Run `composer audit`, `npm audit`, `php artisan test --coverage`, `./vendor/bin/pest --mutate` for live metrics |
| Refactoring backlog | All identified candidates — no cap on count |
| Static analysis tooling | PHPStan + ESLint installation as P0 (blocking prerequisite) |
| Coverage driver | Install PCOV (preferred over Xdebug) and retry if `php artisan test --coverage` fails |
| Vulnerability remediation | Document only — no `composer update` or `npm update` during discovery |
| AI token cost metric | Record "Not tracked" and flag missing tracking as P1 if no implementation exists |
| DelegateeProfile capability_profile | Pivot-based `capabilities()` not compliant with Tomašev — flag missing queryable `capability_profile` JSON field as P1 gap at `app/Models/DelegateeProfile.php` |
| $guarded = [] grouping | Single grouped P0 finding listing all affected models with file:line references in the description — not separate findings per model |
| CI/CD findings granularity | Separate findings per missing CI stage (PHP tests, PHPStan, Pint, ESLint, Vitest, composer audit, npm audit, npm build, SHA pinning) |
| Observability findings | Separate and tiered — Sentry P0, OpenTelemetry P1, OpenLLMetry P1 |
| Runtime metrics capture | Auto-capture PHPStan, ESLint, mutation testing results during execution — install tools, run, inject into report |
| Bundle size analysis | Install `rollup-plugin-visualizer` as dev dependency, run analysis, capture results |
| Horizon timeout compliance | Any Horizon supervisor timeout deviation from engineering rules v2.0 (e.g., supervisor-long-running below 600s) is a P1 compliance finding |
| laravel/ai, laravel/mcp, laravel/boost | All three confirmed absent — `laravel/ai` not on Packagist (Guzzle fallback used), `laravel/mcp` and `laravel/boost` not installed. Document as P2 informational gaps |
| Route auth classification | Use best judgment to distinguish legitimate public routes from missing-auth findings; flag uncertain cases in the report |
| Cache strategy classification | P1 finding for missing caching strategy + grouped P2 findings for specific cacheable opportunities listed in the description |

### Codebase Metrics (Discovery Scan)

| Entity | Count |
|---|---|
| Eloquent Models | 78 |
| Controllers | 77 |
| Jobs | 44 |
| Services | 95+ |
| Support classes | 90+ |
| Events | 29 |
| Listeners | 17 |
| Database Factories | 37 |
| API Resources | 7 |
| Vue Composables | 12 (11 JS, 1 TS) |
| Vue Component Tests | 4 |
| Layouts | 1 |
| Lock files | Both present (composer.lock + package-lock.json) |
| PHPStan config | Missing |
| ESLint config at root | Missing |
| Playwright config | Present |
| Vitest config | Present |
| GitHub Actions workflows | 1 (docs-deploy-sync.yml only) |
| CI quality gate workflows | 0 |
| Sentry | Not installed |
| OpenTelemetry | Not installed |
| OpenLLMetry | Not installed |

### Architecture Context

- **Backend**: Laravel 12 / PHP 8.3, PostgreSQL (pgsql + pgsql_testing), Redis (DB 0 default, DB 1 cache, DB 2 memory)
- **Queue**: Horizon with supervisors: `supervisor-default`, `supervisor-long-running`, `supervisor-memory-working`, `supervisor-memory-formation`
- **Memory Layer**: PHP/Laravel only — `MemoryFormationPipeline.php`, `MemoryFormationJob.php`, `Neo4jGraphStore.php`, `MemoryContextBuilder.php`, `MemoryFormationResult.php`; token budget: 5% of context window, 10% margin, clamp [1000..8000], 4 chars/token
- **Docker services**: Neo4j 5.x Community (with APOC plugin), Typesense 27.1
- **Frontend**: Vue/Inertia, Tailwind, Vite, Playwright (E2E), Vitest (unit)
- **Config files**: `config/agent.php` (execution settings, allowed paths), `config/horizon.php` (queue supervisors), `config/memory.php` (rate limits, pricing, entity types), `config/database.php` (Redis DB assignments)
- **Known constraints**: `laravel/ai` not on Packagist (provider adapters use Guzzle); pgvector may be unavailable (graceful degradation to BM25 keyword search); `laudis/neo4j-php-client:^3.0` installed; `laravel/reverb` present; `laravel/mcp` and `laravel/boost` not installed

### DelegateeProfile Model (Inspected)

- **Location**: `app/Models/DelegateeProfile.php`
- **trust_score**: Present, cast as `decimal:2` — Tomašev compliant
- **trust_updated_at**: Present, cast as `datetime`
- **capability_profile**: Missing — capabilities via `BelongsToMany` pivot to `DelegationCapability` through `delegatee_capabilities_pivot`. P1 gap.
- **$guarded = []**: Line 21 — security finding, engineering rules prohibit in production
- **soul_json**: JSON field for agent personality/system_prompt/user_context — prompt injection attack surface (OWASP LLM07:2025)
- **Related entities**: `DelegateeCapabilityPivot`, `DelegationCapability`, `DelegateeMetric` (HasOne), `DelegateeMetricsRecomputer`, `DelegateeAssigner`, `TrustScoreCalculator`, `RecalculateTrustScoresJob`
- **Tests**: `DelegateeProfileTest`, `DelegateeProfileSoulTest`, `DelegateeProfileControllerTest`, `DelegateeProfileControllerTrustTest`, `DelegateeMetricTest`, `DelegateeMetricsRecomputerTest`, `DelegateeAssignerTest`

### GitHub Actions State (Inspected)

- **Only workflow**: `.github/workflows/docs-deploy-sync.yml` — docs deploy sync gate
- **SHA pinning**: Non-compliant — `actions/checkout@v4` (line 25), `shivammathur/setup-php@v2` (line 28) use version tags
- **Missing CI workflows**: PHP tests, PHPStan/Larastan, Pint, ESLint, Vitest, Playwright, composer audit, npm audit, npm build

### Pre-Identified Findings (from discovery inspection)

| Finding | Location | Severity | Priority |
|---|---|---|---|
| Missing Sentry error tracking | composer.json (absent) | CRITICAL | P0 |
| Missing PHPStan config | project root | HIGH | P0 |
| Missing ESLint config | project root | HIGH | P0 |
| Missing CI: PHP test workflow | .github/workflows/ | HIGH | P0 |
| Missing CI: PHPStan workflow | .github/workflows/ | HIGH | P0 |
| Missing CI: Pint workflow | .github/workflows/ | HIGH | P0 |
| Missing CI: ESLint workflow | .github/workflows/ | HIGH | P0 |
| Missing CI: Vitest workflow | .github/workflows/ | HIGH | P0 |
| Missing CI: composer audit | .github/workflows/ | HIGH | P0 |
| Missing CI: npm audit | .github/workflows/ | HIGH | P0 |
| Missing CI: npm build | .github/workflows/ | HIGH | P0 |
| $guarded = [] mass-assignment (grouped) | app/Models/DelegateeProfile.php:21 + all other affected models | HIGH | P0 |
| SHA pinning non-compliance | .github/workflows/docs-deploy-sync.yml:25,28 | MEDIUM | P1 |
| Missing capability_profile field | app/Models/DelegateeProfile.php | HIGH | P1 |
| Missing OpenTelemetry | composer.json (absent) | HIGH | P1 |
| Missing OpenLLMetry | composer.json (absent) | HIGH | P1 |
| Missing AI token cost tracking | codebase-wide | HIGH | P1 |
| Horizon timeout deviation (if confirmed) | config/horizon.php | HIGH | P1 |
| Missing caching strategy (if confirmed) | codebase-wide | HIGH | P1 |
| laravel/ai, laravel/mcp, laravel/boost absent | composer.json | MEDIUM | P2 |
| Specific cacheable opportunities (if identified) | grouped in description | MEDIUM | P2 |

### Output Specification

**Primary deliverable**: `tasks/DISCOVERY_REPORT.md` with sections: (1) Executive summary, (2) Architecture overview (PHP-only memory layer, no FastAPI), (3) Issue register table (all severities, file:line, severity, effort, priority), (4) All refactoring candidates with before/after using Refactoring Guru taxonomy, (5) Security vulnerabilities by OWASP 2025 category, (6) AI-specific security section by OWASP LLM 2025 / Agentic 2026 covering both HTTP-exposed and internal AI integration points (prompt construction, Guzzle provider calls, job dispatchers, soul_json handling), (7) Gaps vs engineering rules v2.0, (8) Gaps vs Tomašev et al. delegation framework (all as P1), (9) Action plan (P0/P1/P2), (10) Metrics baseline (auto-captured live values for coverage, mutation, linter violations, bundle size; "Not tracked" for AI token cost).

**Secondary deliverable**: Linear issues auto-created for every P0 and P1 finding in **Agent Orchestration** team / **AgentOps** project. If team/project lookup fails, skip creation and flag as blocker in report.

### Tooling Installation Sequence (P0 Prerequisites)

1. Install PCOV PHP extension (preferred coverage driver) — retry `php artisan test --coverage` after installation
2. Install PHPStan/Larastan at level 5+ with `phpstan.neon` at project root
3. Install ESLint v10 flat config at project root
4. Install `rollup-plugin-visualizer` as npm dev dependency for bundle size analysis
5. Run all tools, auto-capture results, inject into report metrics section

### Discovery Run Boundaries

- **Read-only for dependencies**: No `composer update`, `npm update`, or package version changes
- **Tooling installs allowed**: PCOV, PHPStan/Larastan, ESLint, rollup-plugin-visualizer — additive dev dependencies for measurement only
- **No code changes**: Discovery produces report and Linear tickets only
- **No Python audit**: No Python codebase exists in this repository
- **$guarded = [] scanning**: All 78 models scanned; all instances grouped into a single P0 finding with every file:line listed in the description
- **Horizon timeout audit**: Verify all supervisor timeout values against engineering rules v2.0; any deviation is P1
- **AI security scope**: Internal AI integration points (prompt construction in jobs, Guzzle calls to AI providers, soul_json handling, delegation chains) are fully in scope for OWASP LLM 2025 / Agentic 2026 assessment regardless of HTTP endpoint exposure
- **Route auth classification**: Use best judgment for legitimate public routes; flag uncertain cases in report
- **Cache strategy**: If minimal/no caching exists, file P1 for missing strategy + grouped P2 for specific cacheable opportunities

## Goals

- Execute exhaustive all-8-phase discovery audit of the AgentOps codebase (Laravel 12 / PHP 8.3 + Vue/Inertia monolith) covering architecture mapping, code quality, security, performance, testing, coding standards, AgentOps-specific architecture gaps, and DevOps/infrastructure
- Produce `tasks/DISCOVERY_REPORT.md` following the brief template with 10 sections: executive summary, architecture overview, issue register table (all severities with exact file:line references), refactoring candidates (Refactoring Guru taxonomy), security vulnerabilities (OWASP 2025), AI-specific security section (OWASP LLM 2025 / Agentic 2026 — separate section with own priority scale), gaps vs engineering rules v2.0, gaps vs Tomašev et al. delegation framework (all as P1), action plan (P0/P1/P2), metrics baseline
- Auto-create Linear issues in Agent Orchestration team / AgentOps project for every P0 and P1 finding; if team/project lookup fails, skip creation and flag as blocker in report for manual resolution
- Install P0 prerequisite tooling before quality analysis: (1) PCOV PHP extension for coverage, (2) PHPStan/Larastan level 5+ with phpstan.neon, (3) ESLint v10 flat config, (4) rollup-plugin-visualizer for bundle size analysis
- Run live audit commands and auto-capture results: composer audit, npm audit, php artisan test --coverage, ./vendor/bin/pest --mutate, PHPStan, ESLint, Vite bundle analysis — inject all results directly into report metrics section
- Scan all 78 Eloquent models for $guarded = [] usage and group all instances into a single P0 finding with every file:line listed in the description
- Verify all Horizon supervisor timeout values against engineering rules v2.0; flag any deviation (e.g., supervisor-long-running below 600s) as P1 compliance finding
- Audit internal AI integration points for OWASP LLM 2025 / Agentic 2026 compliance: prompt construction in jobs, Guzzle HTTP calls to AI providers, soul_json handling in DelegateeProfile, delegation chains — regardless of HTTP endpoint exposure
- Assess DelegateeProfile model against Tomašev et al. framework: flag missing queryable capability_profile JSON field as P1 gap (pivot-based capabilities() is non-compliant), document trust_score compliance, identify soul_json as prompt injection attack surface (LLM07:2025)
- Establish metrics baseline: test coverage (line/branch/mutation/type), linter violations (PHPStan + ESLint), TODO/FIXME count, zero-test files, dependency vulnerabilities, bundle size; record AI token cost as 'Not tracked' and flag missing tracking as P1
- Audit caching strategy: if minimal/no caching exists, file P1 for missing strategy plus grouped P2 for specific cacheable query/computation opportunities identified during the audit
- Enumerate all web + API routes via php artisan route:list; identify versioning strategy, missing auth middleware (using best judgment for legitimate public routes, flagging uncertain cases), and rate limiting presence


## Constraints

- Engineering rules v2.0 (`/Users/garethdaine/Code/agent/docs/refactoring/agent-ops-engineering-rules.md`) is the absolute compliance standard — every deviation is a finding, no pragmatic exceptions
- All findings must include exact file:line references regardless of severity level
- No dependency version changes during discovery — no composer update, npm update, or package version modifications; vulnerabilities documented only
- Tooling installs limited to additive dev dependencies for measurement: PCOV, PHPStan/Larastan, ESLint, rollup-plugin-visualizer
- No application source code changes — discovery produces report and Linear tickets only
- No Python/FastAPI audit items — no Python codebase exists in this repository; memory layer is PHP/Laravel only
- $guarded = [] findings across all models must be grouped into a single P0 finding (not separate findings per model)
- CI/CD findings must be separate per missing stage — individual findings for PHP tests, PHPStan, Pint, ESLint, Vitest, composer audit, npm audit, npm build, and SHA pinning
- Observability findings must be separate and tiered: Sentry as P0, OpenTelemetry as P1, OpenLLMetry as P1
- AI-specific security findings must be in a separate dedicated section with own independent priority scale, not merged with traditional OWASP 2025 web security findings
- AI security scope includes internal integration points (prompt construction, Guzzle provider calls, job-based AI invocations, soul_json handling) even if no HTTP-exposed AI endpoints exist
- Tomašev et al. intelligent delegation framework compliance is a core requirement — all gaps are P1 findings
- Test coverage targets: 80% min / 90% target line coverage; 75% min / 85% target branch coverage; 60% min / 80% target mutation score; 90% min / 100% target PHP type coverage
- If PCOV is not installed and php artisan test --coverage fails, install PCOV (preferred over Xdebug) and retry
- Linear issues auto-created for P0 and P1 findings only; if Agent Orchestration team or AgentOps project cannot be found via API, skip Linear creation entirely and flag as blocker in report
- laravel/ai not available on Packagist — provider adapters use direct Guzzle HTTP clients; laravel/mcp and laravel/boost not installed; all three documented as P2 informational gaps
- pgvector extension may not be available — system gracefully degrades to BM25 keyword search
- Horizon supervisor timeout deviations from engineering rules v2.0 are P1 compliance findings
- Refactoring backlog includes all identified candidates with no cap on count, classified using Refactoring Guru taxonomy
- Cache strategy findings: P1 for missing overall strategy, grouped P2 for specific cacheable opportunities
- Route auth middleware classification uses best judgment for legitimate public routes; uncertain cases flagged explicitly in the report


## Acceptance Criteria

- tasks/DISCOVERY_REPORT.md exists and contains all 10 sections specified in the output specification
- Executive summary is 3-5 sentences covering overall health, top 3 risks, and recommended first action
- Architecture overview accurately reflects PHP-only memory layer (no FastAPI) with Neo4j and Typesense Docker services
- Issue register table includes every finding at all severity levels (CRITICAL, HIGH, MEDIUM, LOW) with columns: #, Category, File/Location (exact file:line), Issue, Severity, Effort, Priority
- Every finding in the issue register has an exact file:line reference — no file-level-only references permitted
- Refactoring candidates section lists all identified candidates (no cap) with before/after descriptions using Refactoring Guru taxonomy (Long Method, Large Class, Feature Envy, Shotgun Surgery, Data Clumps, Primitive Obsession, Missing Action Pattern)
- Security vulnerabilities section groups findings by OWASP 2025 category with file:line evidence
- AI-specific security section exists as a separate section (not merged with traditional security) grouped by OWASP LLM Top 10 2025 and Agentic Top 10 2026 categories, covering internal AI integration points including prompt construction, Guzzle provider calls, soul_json handling, and delegation chains
- Gaps vs engineering rules v2.0 section lists every deviation found from `/Users/garethdaine/Code/agent/docs/refactoring/agent-ops-engineering-rules.md` v2.0
- Gaps vs Tomašev et al. delegation framework section exists with all gaps classified as P1, including: missing capability_profile JSON field on DelegateeProfile, and any other delegation/trust/verification/memory gaps identified during execution
- DelegateeProfile $guarded = [] at line 21 plus all other models with $guarded = [] are consolidated into a single grouped P0 finding with every affected file:line in the description
- Missing CI stages are separate findings: PHP test workflow (P0), PHPStan workflow (P0), Pint workflow (P0), ESLint workflow (P0), Vitest workflow (P0), composer audit workflow (P0), npm audit workflow (P0), npm build workflow (P0)
- SHA pinning non-compliance on docs-deploy-sync.yml lines 25 and 28 is a P1 finding
- Missing Sentry is a P0/CRITICAL finding; missing OpenTelemetry is P1; missing OpenLLMetry is P1 — all as separate findings
- Missing PHPStan config and missing ESLint config are each separate P0 findings
- AI token cost metric in baseline section shows 'Not tracked' if no implementation exists, with missing tracking flagged as P1
- Metrics baseline section contains auto-captured live values for: test line coverage %, branch coverage %, mutation score %, PHP type coverage %, PHPStan violation count, ESLint violation count, TODO/FIXME count, zero-test file count, dependency vulnerability count (composer audit + npm audit), and bundle size data from rollup-plugin-visualizer
- Horizon supervisor timeouts verified against engineering rules v2.0; any deviation documented as P1 compliance finding with exact config/horizon.php line reference
- laravel/ai, laravel/mcp, and laravel/boost absence documented as P2 informational gaps
- Action plan section organized into P0 (immediate), P1 (next sprint), P2 (next quarter) with all findings from the issue register mapped to the appropriate tier
- Linear issues created for every P0 and P1 finding in Agent Orchestration team / AgentOps project, OR a blocker flag in the report explains why Linear creation was skipped
- composer audit and npm audit results included with zero high/critical vulnerability target noted; any vulnerabilities documented as findings (no dependency changes made)
- All 78 Eloquent models scanned for $guarded usage, relationship mapping, and $fillable compliance
- Cache strategy audit completed: P1 finding for missing overall strategy if applicable, grouped P2 for specific cacheable opportunities with file:line references
- Route enumeration completed via php artisan route:list; missing auth middleware on non-public routes flagged with file:line; uncertain public/protected classifications explicitly noted in the report
- Bundle size analysis captured via rollup-plugin-visualizer and included in metrics baseline

