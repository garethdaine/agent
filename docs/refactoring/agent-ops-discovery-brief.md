# AgentOps Requirements Discovery Brief v2.0

> A structured brief for conducting a thorough codebase analysis, gap identification, and refactor planning. Run this as a dedicated agent job before any major sprint or architectural initiative.

---

## STAR Preamble

**SITUATION**: AgentOps has grown rapidly. The codebase contains interconnected systems for job orchestration, agent lifecycle management, scheduling, monitoring, a Vue/Inertia frontend, and a Python/FastAPI memory layer — all evolving concurrently. Technical debt has accumulated and quality/standards consistency is uncertain. The engineering rules have been updated to v2.0 with current OWASP 2025 standards, Laravel 12 AI packages, and modern tooling.

**TASK**: Produce a complete, evidence-based discovery report that:
1. Maps the current architecture.
2. Identifies code quality issues, gaps, and security concerns.
3. Flags deviations from the standards defined in `agent-ops-engineering-rules.md` v2.0.
4. Prioritises refactoring candidates by impact and risk.
5. Defines a clear, actionable backlog of improvements.

**ACTION**: Execute the discovery phases below, in order.

**RESULT**: A structured `DISCOVERY_REPORT.md` in `tasks/` with a prioritised issue register and recommended action plan. Verified by: report contains evidence-backed findings, no opinions without supporting file/line references.

---

## Phase 1 — Architecture Mapping

**Goal**: Understand what exists before judging it.

### 1.1 Directory Structure Audit

List and document:

- Top-level directory layout
- App layer separation (Models / Services / Actions / Jobs / Events / Listeners)
- Frontend structure (components / pages / layouts / composables)
- Config and environment structure
- Queue worker setup
- Scheduled task registration
- Python/FastAPI memory layer structure and boundaries

### 1.2 Dependency Graph

- Identify all service provider registrations
- Map Job → Queue channel assignments
- Document Event → Listener relationships
- Map all external API integrations (AI providers, MCP servers)
- List all Spatie and third-party packages with versions
- Verify `composer.lock` and `package-lock.json` are committed
- Run `composer audit` and `npm audit` — flag any vulnerabilities

### 1.3 Data Model Map

- List all Eloquent models
- Document model relationships (hasMany, belongsTo, morphs, etc.)
- Identify polymorphic relationships and their usage
- Note any missing indexes on FK columns
- Flag `$guarded` vs `$fillable` usage (never `$guarded = []` in production)
- Check for Delegatee model: does it have `capability_profile` and `trust_score`?

### 1.4 API Surface Audit

- List all routes (web + API)
- Identify versioning strategy (or lack thereof — should be `/api/v1/`)
- Note any unversioned endpoints
- Check for missing auth middleware
- Document rate limiting presence/absence (especially on AI-facing endpoints)
- Check for OpenAPI 3.1 documentation

### 1.5 AI Integration Mapping

- Identify which AI providers are integrated (Anthropic, OpenAI, Gemini, etc.)
- Check if `laravel/ai` is installed and how it's configured
- Check if `laravel/mcp` is installed and what tools/resources are exposed
- Check if `laravel/boost` is installed for AI coding agent support
- Map AI-facing endpoints and their rate limiting
- Identify SSE streaming endpoints (`response()->eventStream()`)
- Document any MCP server configurations

---

## Phase 2 — Code Quality Analysis

**Goal**: Find systematic quality issues, not just bugs.

### 2.1 SOLID Violations

Check for:

- Classes with multiple responsibilities (God classes, fat controllers)
- Direct concrete class instantiation instead of interface injection
- Liskov violations in inheritance hierarchies
- Fat interfaces forcing unused method implementations
- High coupling between modules that should be independent
- Controllers with business logic (should be in Actions/Services)

### 2.2 DRY Violations

Check for:

- Duplicate validation logic (same rules in multiple Form Requests / controllers)
- Repeated query patterns that should be Eloquent scopes
- Copy-pasted UI components that should be shared
- Duplicated configuration values
- Repeated error handling boilerplate
- Duplicate prompt templates or AI interaction patterns

### 2.3 Clean Code Issues

Check for:

- Vague or misleading variable/method names
- Functions > 30 lines
- Deeply nested conditionals (> 3 levels)
- Commented-out code
- Magic numbers/strings (not extracted to constants or enums)
- `dd()`, `var_dump()`, `console.log()` left in codebase
- Methods with > 5 parameters (candidate for DTO/value object)

### 2.4 Refactoring Opportunities

Using the Refactoring Guru taxonomy, identify instances of:

- Long Method → Extract Method
- Large Class → Extract Class
- Feature Envy → Move Method
- Shotgun Surgery → consolidate
- Data Clumps → extract to value object/DTO
- Primitive Obsession → introduce typed classes (enums, value objects)
- Missing Action Pattern → extract controller logic to `app/Actions/`

**Reference**: https://refactoring.guru/refactoring/smells

---

## Phase 3 — Security Audit

**Goal**: Identify exploitable vulnerabilities and compliance gaps against OWASP 2025.

### 3.1 Authentication & Authorisation

- All protected routes have auth middleware
- Role/permission checks are consistent (middleware vs. policy vs. manual)
- Password reset flow is secure (token expiry, single-use)
- Session configuration is hardened
- API tokens are scoped and revocable
- Jetstream/Sanctum properly configured

### 3.2 Input & Output

- All user input goes through Form Request validation
- No `$request->all()` mass-assignment without explicit `->only()` or `->validated()`
- SQL injection: no raw queries with user input
- XSS: no unescaped output in Blade/Vue templates
- File upload validation (type, size, storage path — not public web root)
- CSRF protection active on all state-changing routes

### 3.3 AI-Specific Security (OWASP LLM Top 10 2025 + Agentic Top 10 2026)

- All AI-facing endpoints have rate limiting
- **Prompt injection mitigations** present (input sanitisation, output validation)
- **System prompt leakage** prevention (LLM07:2025)
- AI-generated content is sanitised before storage/display
- Agent delegation chains validate permissions at each step
- No agent can escalate its own privilege level
- **Memory/context poisoning** defences (Agentic Top 10)
- **Tool misuse** guardrails — agents confirm before irreversible actions
- Vector/embedding inputs validated against poisoning (LLM08:2025)
- AI output treated as untrusted external input

### 3.4 Supply Chain Security (OWASP A03:2025)

- `composer audit` returns zero high/critical vulnerabilities
- `npm audit` returns zero high/critical vulnerabilities
- GitHub Actions pinned to full SHA (not just version tags)
- No deprecated or unmaintained dependencies
- Lock files committed and integrity-verified

### 3.5 Infrastructure & Secrets

- No secrets in codebase (`grep -r "API_KEY\|SECRET\|PASSWORD" app/`)
- `.env.example` is current and complete
- CORS configuration is restrictive
- Security headers present (CSP, HSTS, X-Frame-Options, X-Content-Type)
- Error handling does not leak stack traces in production (OWASP A10:2025)

---

## Phase 4 — Performance & Scalability

**Goal**: Identify bottlenecks before they become incidents.

### 4.1 Database

- N+1 queries (look for `->get()` inside loops, missing `->with()`)
- Laravel 12.8 automatic eager loading — is it enabled?
- Missing indexes on join columns and frequently filtered columns
- Unbounded queries (`->all()` on large tables)
- Long-running migrations (no online schema change strategy)
- pgvector indexes configured (HNSW preferred for production)

### 4.2 Queue & Jobs

- Jobs that should be chunked but aren't
- Jobs with no timeout defined
- Jobs with no retry limit defined
- Missing `uniqueId()` on jobs that shouldn't run concurrently
- Queue depth monitoring in place (Horizon dashboard)?
- Horizon supervisor config: separate `supervisor-default` (standard queues) and `supervisor-long-running` (AI inference, 600s timeout)?
- STAR preamble generation in dispatch pipeline?
- Long-running AI jobs configured with appropriate timeouts (600s+)?

### 4.3 Cache

- Expensive queries / computations cached?
- Cache invalidation strategy documented?
- Redis TTLs set on all cache keys?
- Separate Redis databases for cache / queue / session?

### 4.4 Frontend

- Bundle size analysed? (`npm run build -- --analyze`)
- Images optimised and lazy-loaded?
- Large dependency imports that could be tree-shaken?
- Tailwind v4 CSS-first configuration (not legacy `tailwind.config.js`)?
- Vite 7+ configured correctly?

### 4.5 AI API Costs

- Token usage tracked per job/user?
- Cost anomaly alerting configured?
- Model selection appropriate (not using Opus/GPT-5.4 for tasks Haiku/mini could handle)?
- Batch processing used where applicable (50% cost reduction)?
- OpenLLMetry instrumentation in place for LLM observability?

---

## Phase 5 — Testing Coverage

**Goal**: Know what's tested, what isn't, and what must be.

### 5.1 Coverage Inventory

- Run `php artisan test --coverage` and record coverage %
- Identify zero-coverage files in `app/Services`, `app/Jobs`, `app/Actions`
- Check for feature tests on all API endpoints
- Verify critical paths have integration tests
- Run `./vendor/bin/pest --mutate` for mutation testing baseline
- Check Vitest coverage for Vue components
- Check pytest coverage for FastAPI memory layer

### 5.2 Test Quality

- Tests assert behaviour, not implementation details
- No tests that mock everything (test nothing real)
- Database factories are complete and realistic
- Tests clean up after themselves (uses `RefreshDatabase` or transactions)
- Same database engine in tests as production (MySQL/PostgreSQL, not SQLite)
- Pest v4 architecture testing presets applied (`php`, `security`, `laravel`)?
- E2E tests use Playwright with role-based locators (not CSS selectors)?
- Vue component tests use `userEvent` over `fireEvent` for realistic interactions?
- Test files co-located with components (`AgentCard.test.ts` next to `AgentCard.vue`)?
- Pact consumer-driven contract tests between Laravel and FastAPI services?

### 5.3 Testing Gaps vs. Target

| Metric | Current | Minimum | Target |
|---|---|---|---|
| Line coverage | ? | 80% | 90%+ |
| Branch coverage | ? | 75% | 85%+ |
| Mutation score | ? | 60% | 80%+ |
| Type coverage (PHP) | ? | 90% | 100% |

---

## Phase 6 — Coding Standards Compliance

**Goal**: Confirm uniform standards adherence.

### 6.1 PHP / Laravel

- `declare(strict_types=1)` present in all PHP files
- PSR-12 compliance (run `./vendor/bin/pint --test`)
- PHPStan/Larastan passing at level 5+
- Enums used instead of string constants
- Return types on all public methods
- No empty constructors
- `use` statements ordered and used
- PHP 8.4+ features used where beneficial (property hooks, asymmetric visibility)

### 6.2 JavaScript / TypeScript / Vue

- ESLint v10 flat config passing with zero errors
- Prettier formatting consistent
- No `any` types in TypeScript
- Components have defined prop types
- No inline styles (use Tailwind or CSS variables)
- `<script setup>` syntax used in Vue SFCs

### 6.3 Python / FastAPI

- Ruff linting passing
- mypy type checking passing
- Type hints on all function signatures
- Pydantic v2 models for all data validation
- `uv` used for dependency management
- async patterns consistent (no sync blocking in async paths)

### 6.4 General

- No `TODO` or `FIXME` without an associated ticket
- README accurate and current
- Environment variables documented in `.env.example`
- Conventional commit messages enforced (`@commitlint/cli` or equivalent)
- Pre-commit hooks present (Husky + lint-staged)?
- All config centralised in `bootstrap/app.php` (not legacy Http/Kernel)?

---

## Phase 7 — Architecture Gaps (AgentOps-Specific)

**Goal**: Identify gaps relative to the intelligent delegation architecture (Tomašev et al. framework).

### 7.1 Agent Orchestration Layer

- Delegatee model exists and has `capability_profile`?
- Trust scores are persisted and updated per-task?
- Agent registry is queryable?
- Job delegation chains are auditable (who delegated what to whom)?
- Permission attenuation implemented at sub-delegation boundaries?
- Delegation contracts specify verification mechanisms?

### 7.2 Memory Layer

- Core memory (editable agent context blocks) implemented?
- Working memory (session-scoped buffers) implemented?
- Long-term memory store present and queryable (pgvector)?
- Delegation memory (multi-agent coordination state) present?
- Memory formation is async and non-blocking?
- Privacy-aware access control on memory?

### 7.3 STAR Preamble Pipeline

- STAR generation implemented in job dispatch pipeline?
- STAR output is logged and inspectable?
- Recovery logic uses targeted re-prompting (not blind retry)?
- Jobs declare `capabilities_required`, `estimated_duration`, `reversibility`?

### 7.4 Monitoring & Observability

- All jobs emit structured log events?
- Agent execution traces available via OpenTelemetry?
- Real-time job status via Reverb channels?
- Failure rates dashboarded in Horizon?
- Token usage and cost tracked via OpenLLMetry?
- Laravel Pulse configured for app-level metrics?
- Sentry configured for error tracking?

### 7.5 Laravel AI Integration

- `laravel/ai` installed and configured for multi-provider support?
- `laravel/mcp` installed with appropriate tools/resources exposed?
- `laravel/boost` installed for AI coding agent enhancement?
- SSE streaming configured for AI response delivery?
- AI model selection appropriate per task criticality?

---

## Phase 8 — DevOps & Infrastructure

**Goal**: Verify deployment pipeline and infrastructure readiness.

### 8.1 CI/CD Pipeline

- GitHub Actions workflows present and functional?
- PHP CI: Pint + PHPStan/Larastan + `composer audit` + `php artisan test --parallel`?
- Node CI: ESLint + Vitest + `npm audit` + `npm run build`?
- Python CI: Ruff + mypy + `bandit` + pytest?
- Actions pinned to full SHA (not version tags)?
- Parallel job structure (quality → tests → build → deploy)?

### 8.2 Docker Configuration

- Multi-stage builds for production?
- Non-root user in containers?
- Health checks defined?
- Alpine variants used?
- Trivy scanning in CI?
- Docker Compose for local development (app, nginx, db, redis, horizon, node, python-api)?

### 8.3 Deployment

- Zero-downtime deployments configured (Forge or Laravel Cloud)?
- Horizon workers supervised with auto-restart?
- Graceful shutdown on deploy (`horizon:terminate`)?
- Database migrations backward-compatible?
- Rollback strategy documented?
- Feature flags (Laravel Pennant) for progressive rollouts?

### 8.4 Secrets Management

- All secrets in GitHub Actions Secrets?
- `.env` files never committed?
- `php artisan env:encrypt` for encrypted environment files?
- No long-lived API keys in CI (use OIDC where available)?

### 8.5 Monitoring & Alerting

- Structured JSON logging configured for production (Monolog JsonFormatter)?
- Alert design: ignore 422/404 noise, alert on p95 latency, error rate spikes, queue depth growth, failed job counts?
- Sentry integrated with auto-instrumentation (HTTP, DB, queues, cache)?
- Laravel Pulse configured for app-level metrics?
- OpenTelemetry with `opentelemetry-auto-laravel` installed?
- OpenLLMetry configured for LLM token/cost/latency tracking?

### 8.6 Service Communication

- Inter-service communication pattern documented (Laravel↔FastAPI)?
- Is gRPC viable for high-frequency internal calls (benchmarked at 12× fewer round-trips vs REST)?
- Meilisearch considered for full-text search of agent logs/knowledge bases?

---

## Discovery Report Template

Save findings to `tasks/DISCOVERY_REPORT.md` using this structure:

```markdown
# AgentOps Discovery Report

**Date:** YYYY-MM-DD
**Analyst:** [agent/human]
**Engineering Rules Version:** 2.0

## Executive Summary

[3-5 sentences: overall health, top 3 risks, recommended first action]

## Architecture Overview

[Diagram or textual map of current system layers]

## Issue Register

| # | Category | File / Location | Issue | Severity | Effort | Priority |
|---|----------|----------------|-------|----------|--------|----------|
| 1 | Security | app/Http/... | Missing auth middleware | CRITICAL | Low | P0 |
| 2 | Quality | app/Services/... | God class (500+ lines) | HIGH | Medium | P1 |

Severity: CRITICAL / HIGH / MEDIUM / LOW
Effort: Low (< 1 day) / Medium (1-3 days) / High (> 3 days)
Priority: P0 (do now) / P1 (next sprint) / P2 (backlog) / P3 (nice-to-have)

## Refactoring Candidates

[List top 5-10 refactors with before/after description]

## Security Vulnerabilities

[Grouped by OWASP 2025 category with file:line evidence]
[Include AI-specific findings grouped by OWASP LLM 2025 / Agentic 2026 categories]

## Gaps vs. Engineering Rules v2.0

[List deviations from agent-ops-engineering-rules.md]

## Gaps vs. Intelligent Delegation Framework

[Gaps relative to Tomašev et al. framework — delegation, trust, verification, monitoring]

## Recommended Action Plan

### Immediate (P0 — this week)

### Short-term (P1 — next sprint)

### Medium-term (P2 — next quarter)

## Metrics Baseline

- Test coverage: X%
- Mutation score: X%
- Linter violations: X
- TODO/FIXME count: X
- Zero-test files: X
- Dependency vulnerabilities: X
- AI token cost (monthly): X
```

---

## Running This Discovery

### Option A: Manual (single session)

1. Load this brief into context.
2. Load `agent-ops-engineering-rules.md` v2.0 into context.
3. Execute phases 1–8 against the codebase at `/Users/garethdaine/Code/agent`.
4. Write findings to `tasks/DISCOVERY_REPORT.md`.

### Option B: Automated Job (recommended)

Dispatch as an AgentOps job with:

```json
{
  "type": "discovery",
  "brief": "tasks/DISCOVERY_BRIEF.md",
  "rules": "agent-ops-engineering-rules.md",
  "target": "/Users/garethdaine/Code/agent",
  "output": "tasks/DISCOVERY_REPORT.md",
  "star": {
    "situation": "AgentOps codebase has grown rapidly with no formal audit against v2.0 rules",
    "task": "Produce prioritised issue register and action plan covering 8 phases",
    "action": "Execute all 8 discovery phases systematically with evidence-backed findings",
    "result": "DISCOVERY_REPORT.md with 100% phase coverage, file:line references, and metrics baseline"
  }
}
```

---

## Post-Discovery Actions

1. **Triage** the issue register — assign severity and owner to every P0/P1.
2. **Create Linear issues** for every P0 and P1 finding.
3. **Update** `agent-ops-engineering-rules.md` if gaps reveal missing rules.
4. **Install missing skills** identified during the audit.
5. **Schedule re-discovery** quarterly, or after any major architectural change.
6. **Set baseline metrics** — track coverage %, mutation score, linter violations, AI costs, and critical issues over time.

---

*Brief version: 2.0 — March 2026. Aligned with `agent-ops-engineering-rules.md` v2.0. Revise after each quarterly discovery cycle.*
