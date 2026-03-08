# AgentOps Engineering Rules v2.0

> Inject into every job, agent, and code-analysis session. Keep this context active for all tasks.

---

## How to Use This Document

1. **Read the relevant category** before touching any code in that domain.
2. **Follow the principle links** — they are authoritative references, not optional reading.
3. **Apply rules in order of specificity**: Language rule > Framework rule > General principle.
4. **When uncertain**, default to the most restrictive / safest interpretation.
5. **Never skip the STAR preamble** before beginning a job (see Planning section).
6. **Install recommended skills** — they extend these rules with enforced patterns.

---

## Skill Format & Installation

AgentOps uses the **SKILL.md open standard** (governed by the Linux Foundation's Agentic AI Foundation). Skills are compatible across Claude Code, Codex CLI, Cursor, GitHub Copilot, Gemini CLI, and Windsurf.

| Tool | Skill Location |
|---|---|
| Claude Code | `.claude/skills/SKILL.md` |
| Codex CLI | `.codex/skills/SKILL.md` |
| Cursor | `.cursor/rules/*.mdc` (`.cursorrules` is deprecated) |
| GitHub Copilot | `.github/skills/SKILL.md` |

### AgentOps Repositories

All skills and rules are aggregated into two private repos, available as Composer dev-dependencies:

| Repo | Purpose | Composer Package |
|---|---|---|
| [`garethdaine/agent-rules`](https://github.com/garethdaine/agent-rules) | Cursor rules (.mdc), coding standards, OWASP rules | `garethdaine/agent-rules` |
| [`garethdaine/agent-skills`](https://github.com/garethdaine/agent-skills) | SKILL.md files, scripts, resources, agent workflows | `garethdaine/agent-skills` |

**Local paths** (after `composer install --prefer-source`):
- Rules: `vendor/garethdaine/agent-rules/vendor/{owner}/{repo}/`
- Skills: `vendor/garethdaine/agent-skills/vendor/{owner}/{repo}/`

**Key skill sources** (bundled in the repos above):

- [`anthropics/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills) — Official Anthropic skills (~69K★)
- [`vercel-labs/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills) — React/Next.js best practices
- [`obra/superpowers`](https://github.com/garethdaine/agent-skills/tree/main/vendor/obra/superpowers) — Agentic workflow methodology (~3.8K★)
- [`sickn33/antigravity-awesome-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/sickn33/antigravity-awesome-skills) — 1,272+ universal skills (~21K★)
- [`ivangrynenko/cursorrules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) — OWASP + standards rules
- [`trailofbits/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/trailofbits/skills) — Security research skills
- [`getsentry/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/getsentry/skills) — Error tracking and PR workflows
- [`hashicorp/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/hashicorp/agent-skills) — Terraform/infrastructure
- [`supabase/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/supabase/agent-skills) — PostgreSQL best practices
- [`onmax/nuxt-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/onmax/nuxt-skills) — Vue/Nuxt ecosystem (~17 skills)
- [`LambdaTest/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/LambdaTest/agent-skills) — 46+ testing framework skills
- [`playbooks.com`](https://playbooks.com) — Universal skill marketplace (955+ pages)
- [`skillsmp.com`](https://skillsmp.com) — Aggregated index (25K–67K+ skills)

**Caution**: ~5.2% of community skills show potentially malicious patterns (Snyk research). Only install from verified sources listed above or audit manually.

**Known skill gaps** (opportunities for custom authoring): No standalone SKILL.md exists for pgvector, Redis/Horizon, Supervisor, Inertia.js v2, Pest PHP, Laravel Reverb/Echo, performance profiling, or prompt injection defense. These represent AgentOps custom skill authoring opportunities.

---

## 🏛 Software Architecture

**What**: Structure code so systems are understandable, changeable, and deployable independently.
**When**: Any new module, service, or significant refactor.

**Core Principles**:

- Separate concerns: UI / business logic / data access are distinct layers.
- Prefer explicit over implicit. Make dependencies visible.
- Design for the seam — every component should be swappable.
- Modular monolith is the default. Microservices only when domains are proven stable.
- Event-driven architecture with CQRS for critical audit trails (agent actions, task completions).

**Key Patterns**: Hexagonal (Ports & Adapters), Clean Architecture, Event-Driven, CQRS, Repository, Modular Monolith.

**References**:
- https://martinfowler.com/architecture/
- https://refactoring.guru/design-patterns/catalog

**Recommended Skills**:
- [`anthropics/skills/doc-coauthoring`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills) — Structured documentation workflow
- [`vercel-labs/agent-skills/web-design-guidelines`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills) — 100+ UI architecture rules
- [`PatrickJS/awesome-cursorrules/rules-new/codequality.mdc`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules-new) — Code quality and architecture rules
- [`playbooks.com/skills/giuseppetrisciuoglio/developer-kit/clean-architecture`](https://playbooks.com/skills/giuseppetrisciuoglio/developer-kit/clean-architecture) — Clean Architecture, Hexagonal, DDD
- [`ccheney/robust-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/ccheney/robust-skills) — Clean Architecture + DDD + Hexagonal for Go, Rust, Python, TS, Java, C#

---

## 🧩 Design Patterns

**What**: Proven, reusable solutions to recurring structural problems.
**When**: Before implementing any non-trivial class/service relationship.

**Must-Know Patterns for AgentOps**:

- **Creational**: Factory, Builder, Singleton (use sparingly)
- **Structural**: Adapter, Decorator, Facade, Repository
- **Behavioural**: Observer, Strategy, Command, Chain of Responsibility, State Machine, Pipeline
- **Agentic**: Orchestrator/Worker, Delegation Chain, Trust-Based Router, Contract Net
- **Laravel-specific**: Action Pattern (`app/Actions/`), DTO with readonly properties, Pipeline Facade, Repository (for complex domain logic only — not for simple CRUD where Eloquent suffices)

**Rule**: Name the pattern when you use it. If you can't name it, reconsider the design.

**References**:
- https://refactoring.guru/design-patterns
- Tomašev et al. "Intelligent AI Delegation" (arxiv:2602.11865) — delegation chain patterns

**Recommended Skills**:
- [`PatrickJS/awesome-cursorrules/rules-new/clean-code.mdc`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules-new) — Clean code and DRY/SOLID rules (.mdc format)
- [`PatrickJS/awesome-cursorrules/rules/optimize-dry-solid-principles`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules/optimize-dry-solid-principles-cursorrules-prompt-file) — DRY/SOLID optimization
- [`playbooks.com/skills/giuseppetrisciuoglio/developer-kit/clean-architecture`](https://playbooks.com/skills/giuseppetrisciuoglio/developer-kit/clean-architecture) — Clean Architecture patterns

---

## 🔐 Security

**What**: Prevent exploitation across the entire attack surface.
**When**: Every feature that touches auth, data, APIs, external input, file I/O, or AI.

**Non-Negotiable Rules**:

- **OWASP Top 10:2025** is the baseline, not a ceiling. Key changes from 2021: A03 is now Software Supply Chain Failures (new), A10 is Mishandling of Exceptional Conditions (new).
- **OWASP Top 10 for LLM Applications 2025**: Prompt Injection (#1), Sensitive Information Disclosure (#2), Excessive Agency (#6), System Prompt Leakage (#7, new), Vector & Embedding Weaknesses (#8, new).
- **OWASP Top 10 for Agentic Applications 2026**: Agent Goal Hijack, Tool Misuse, Memory & Context Poisoning, Insecure Inter-Agent Communication — directly relevant to AgentOps delegation chains.
- Never trust user input. Validate, sanitise, and escape at every boundary.
- Principle of Least Privilege — agents and users get only what they need.
- Secrets never in code, logs, or responses. Use environment variables.
- Prompt injection and jailbreak vectors must be mitigated in all AI-facing endpoints.
- CSRF, XSS, SQL injection, mass-assignment — check every PR.
- Auth middleware must be verified present on every protected route.
- Supply chain: `composer audit` and `npm audit` in CI. Pin action SHAs in GitHub Actions.

**References**:
- https://owasp.org/Top10/2025/
- https://genai.owasp.org (LLM Top 10 + Agentic Top 10)

**Recommended Skills**:
- [`ivangrynenko/cursorrules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) — 22+ OWASP rules for PHP/JS/Python (install: `--tags "standard:owasp-top10"`)
- [`Van-LLM-Crew/cursor-secure-coding`](https://github.com/garethdaine/agent-rules/tree/main/vendor/Van-LLM-Crew/cursor-secure-coding) — ASVS Level 1/2 rules
- [`trailofbits/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/trailofbits/skills) — Security research, CodeQL/Semgrep, vulnerability detection (14+ skills)
- [`agamm/claude-code-owasp`](https://github.com/garethdaine/agent-skills/tree/main/vendor/agamm/claude-code-owasp) — OWASP 2025 + Agentic AI Security (ASI01–ASI10)
- [`netresearch/security-audit-skill`](https://github.com/garethdaine/agent-skills/tree/main/vendor/netresearch/security-audit-skill) — 80+ security checkpoints, CWE Top 25, CVSS v4.0
- [`playbooks.com/skills/openai/skills/security-threat-model`](https://playbooks.com/skills/openai/skills/security-threat-model) — AppSec-grade threat modelling
- [`BehiSecc/VibeSec-Skill`](https://github.com/garethdaine/agent-skills/tree/main/vendor/BehiSecc/VibeSec-Skill) — Secure code patterns, vulnerability prevention (593★)

---

## 🎨 UX / UI

**What**: Every interface must be usable, accessible, and coherent.
**When**: Any frontend work, form design, error state, or agent output presentation.

**Rules**:

- Accessible by default: WCAG 2.1 AA minimum. ARIA where semantic HTML is insufficient.
- Keyboard navigable. Focus states visible.
- Error states are descriptive, actionable, and non-destructive.
- Loading states always present for async operations.
- Mobile-first, responsive. Test at 375px and 1440px minimum.
- Dark mode support where the design system supports it.

**References**:
- https://www.nngroup.com/articles/
- https://web.dev/accessibility/

**Recommended Skills**:
- [`vercel-labs/agent-skills/web-design-guidelines`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills) — 100+ UI review rules
- [`ivangrynenko/cursorrules` (accessibility-standards)](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) — WCAG/a11y enforcement (.mdc format)
- [`addyosmani/web-quality-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/addyosmani/web-quality-skills) — Core Web Vitals, WCAG 2.1, performance, accessibility, SEO (6 skills)

---

## 🎨 Design

**What**: Visual consistency, brand coherence, and aesthetic intentionality.
**When**: New components, pages, marketing assets, onboarding flows.

**Rules**:

- Use design tokens / CSS variables. No magic numbers.
- Typography hierarchy is strict: pick a scale and honour it.
- Colour palette is bounded. No one-off colours outside the system.
- Avoid generic AI-generated aesthetics (purple gradients, Inter on white, etc.).
- Spacing follows an 8pt grid unless explicitly overridden.

**Recommended Skills**:
- [`anthropics/skills/frontend-design`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills) — Production-grade frontend aesthetics
- [`Lombiq/Tailwind-Agent-Skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/Lombiq/Tailwind-Agent-Skills) — Tailwind v4 docs and patterns

---

## 🔬 Research & Analysis

**What**: Ground decisions in evidence. No cargo-culting.
**When**: Architecture decisions, technology choices, performance issues, agent behaviour changes.

**Process**:

1. State the question clearly.
2. Identify known constraints and assumptions.
3. Gather evidence (code, logs, benchmarks, papers).
4. Compare options against constraints.
5. Document the decision and rationale (ADR format preferred).

**Rule**: "We've always done it this way" is not a reason. Cite sources or run benchmarks.

---

## 🎯 SOLID Principles

**What**: Five foundational OOP/design principles for maintainable code.
**When**: Every class, service, and interface you write.

| Principle | Rule |
|---|---|
| **S**ingle Responsibility | One class/function does one thing. If you use "and" to describe it, split it. Applies at module/service level too. |
| **O**pen/Closed | Open for extension, closed for modification. Use interfaces and abstractions. |
| **L**iskov Substitution | Subtypes must be substitutable for base types without breaking behaviour. |
| **I**nterface Segregation | Small, focused interfaces. Don't force classes to implement what they don't use. |
| **D**ependency Inversion | Depend on abstractions, not concretions. Inject dependencies. |

**AHA Principle**: Avoid Hasty Abstractions — don't abstract until you see a pattern repeated 3+ times.

**Reference**: https://refactoring.guru/design-patterns

---

## 🔄 DRY (Don't Repeat Yourself)

**What**: Every piece of knowledge has a single, authoritative representation.
**When**: Any time you find yourself copy-pasting logic.

**Rules**:

- Extract shared logic to a named function/service/trait immediately.
- Configuration is data, not code. Centralise it.
- Repeated SQL/query patterns → query scopes or repository methods.
- DRY applies to tests too — use factories, fixtures, and helpers.

**Warning**: Over-abstraction is the enemy of DRY. Abstract only when the duplication is real and stable.

---

## ✨ Clean Code

**What**: Code that reads like well-written prose. **When**: Always.

**Rules**:

- Names reveal intent. `$processedItems` not `$arr`.
- Functions do one thing. If it needs a comment to explain what it does, rename it.
- Guard clauses / early returns over deeply nested conditionals.
- Max function length: ~20 lines. If longer, extract.
- No commented-out code in commits. Use version control.
- No magic numbers or strings — use named constants or enums.
- Consistent formatting enforced by linter (Pint/PHP, Ruff/Python, ESLint/JS).

**Reference**: https://refactoring.guru/refactoring

---

## 📋 Planning

**What**: Think before typing. Plan before building.
**When**: Any task with 3+ steps or architectural impact.

**STAR Preamble (mandatory before every job)**:

```
SITUATION:  What is the current state? What exists, what doesn't?
TASK:       What specifically must be true when this is complete?
ACTION:     What steps will achieve that end state?
RESULT:     How will completion be verified?
```

**Rules**:

- Write the plan to `tasks/todo.md` with checkable items.
- Verify the plan before implementation.
- Update `tasks/lessons.md` after any correction.
- Never mark a task complete without proving it works.

---

## 🌐 REST APIs & API Architecture

**What**: Consistent, predictable, secure HTTP interfaces.
**When**: Any new endpoint, API integration, or refactor of existing routes.

**Rules**:

- Resources are nouns, not verbs. `/jobs` not `/getJobs`.
- HTTP verbs are semantic. GET reads, POST creates, PUT/PATCH updates, DELETE removes.
- Return appropriate status codes. 200/201/204/400/401/403/404/422/500.
- Version your API. `/api/v1/` from day one. Use Sunset headers for deprecation.
- Always paginate collection endpoints. Cursor-based pagination for large datasets.
- Request/response shapes are documented and stable. Breaking changes = new version.
- API responses are typed. Use transformers/resources to control output shape.
- Rate limit all public and AI-facing endpoints.
- OpenAPI 3.1 for API documentation.

**Reference**: https://martinfowler.com/articles/richardsonMaturityModel.html

---

## ✂️ Refactoring

**What**: Improving internal structure without changing external behaviour.
**When**: Before adding features to messy code. After identifying code smells.

**Common Smells to Fix**: Long Method, Large Class, Primitive Obsession, Feature Envy, Shotgun Surgery, Dead code, speculative generality, duplicate code.

**Process**:

1. Write/verify tests before touching the code.
2. Refactor in small steps. One change = one commit.
3. Re-run tests after each step.
4. Never refactor and add features in the same PR.

**Reference**: https://refactoring.guru/refactoring/catalog

**Recommended Skills**: [`obra/superpowers`](https://github.com/garethdaine/agent-skills/tree/main/vendor/obra/superpowers) — systematic-debugging, root-cause-tracing, brainstorm → plan → TDD → verify chain

---

## ✏️ Coding Standards

**What**: Consistent style enforced automatically.
**When**: All code, all languages, all contributors (human and AI).

| Language | Standard | Tool |
|---|---|---|
| PHP | PSR-12 + strict_types (PER-CS emerging) | Laravel Pint |
| Python | PEP 8 + type hints | Ruff + mypy |
| JavaScript/TypeScript | ESLint v10 flat config + Prettier | ESLint |
| CSS | Tailwind v4 conventions | Stylelint |
| SQL | Uppercase keywords, lowercase identifiers | — |

**Rules**:

- Linters run on every commit (pre-commit hook or CI).
- No bypassing with `// eslint-disable` without a comment explaining why.
- PR author is responsible for passing linter before review.
- **Pre-commit hooks**: Husky + lint-staged for automatic linting on staged files.
- **Commit messages**: Conventional Commits enforced with `@commitlint/cli` — enables automated changelogs and semantic versioning.
- **Alternative**: Biome (Rust-based) as all-in-one ESLint+Prettier replacement — gaining traction for speed.

---

## 🐛 Debugging

**What**: Systematic fault isolation, not random guessing.
**When**: Any bug report, test failure, or unexpected behaviour.

**Process**:

1. Reproduce reliably. If you can't reproduce it, you can't fix it.
2. Isolate the smallest failing case.
3. Form a hypothesis. Test it. Don't fix things you don't understand.
4. Check logs, traces, and monitoring before touching code.
5. Fix the root cause, not the symptom.

**Rules**:

- Never ship `dd()`, `var_dump()`, `console.log()` debugging code.
- Error messages must be actionable. Log the context, not just the exception class.
- Exceptions are exceptional. Use return types and Result objects for expected failures.

**Tools**:
- **PHP**: Xdebug 3.5 (step debugging, profiling, coverage), LaraDumps (free Ray alternative), Laravel Nightwatch (real-time exception detection, 2025+). Telescope for local only — never in production.
- **Python**: `pdb`/`ipdb`, FastAPI debug mode, `pytest --pdb` for test failures.
- **Frontend**: Vue DevTools, browser Performance tab, Vite HMR error overlay.

**Recommended Skills**: [`obra/superpowers`](https://github.com/garethdaine/agent-skills/tree/main/vendor/obra/superpowers) (systematic-debugging, root-cause-tracing), [`getsentry/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/getsentry/skills)

---

## 📢 Marketing (Social, SEO, Email)

**What**: Consistent, on-brand, measurable communication.
**When**: Any public-facing content, landing pages, email campaigns, social posts.

**Rules**:

- SEO: Semantic HTML, correct heading hierarchy, meta tags, canonical URLs, structured data.
- Performance is SEO: Core Web Vitals (LCP < 2.5s, CLS < 0.1, INP < 200ms).
- Email: Plain text fallback always. Test on mobile. CAN-SPAM/GDPR compliance.
- Social: Platform-appropriate format. Image alt text. Accessible colour contrast.
- Copy: Clear value proposition first. CTAs are specific and action-oriented.

---

## 🖥 Server Infrastructure

**What**: Reliable, secure, observable infrastructure.
**When**: Deployments, environment configuration, queue workers, scheduled tasks.

**Rules**:

- Environment-specific config via `.env`. Never commit secrets.
- Immutable deployments. No SSH-and-edit in production.
- Health check endpoints on all services.
- Log to stdout/stderr. Structured JSON logging in production.
- Queue workers supervised (Supervisor/Horizon). Monitor queue depth and failure rates.
- **Horizon config**: Separate `supervisor-default` (standard queues, auto-balance, up to 10 processes) and `supervisor-long-running` (AI inference queues, 600s timeout, 256MB memory, up to 5 processes). `horizon:terminate` on deploy for graceful restart.
- Database migrations backward-compatible. Deploy code before running migrations.
- Use Redis/Valkey for cache, session, and queues.
- Zero-downtime deployments via Forge (symlink-based, default) or Laravel Cloud.
- **Alerting**: Ignore 422/404 noise. Alert on p95 latency thresholds, error rate spikes, queue depth growth, and failed job counts.

**Deployment Stack**: Laravel Cloud (push-to-deploy, auto-scaling, managed Reverb clusters), Laravel Forge (server provisioning, zero-downtime), Horizon (queue monitoring).

**Recommended Skills**: [`hashicorp/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/hashicorp/agent-skills), [`ivangrynenko/cursorrules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) (install: `--tags "category:devops"`)

---

## 💻 Terminal / CLI

**What**: Safe, scripted, auditable shell operations.
**When**: Any bash/shell task, Artisan command, or CLI tool usage.

**Rules**:

- Prefer idempotent commands. Re-running should be safe.
- Always check exit codes. `set -e` in scripts.
- Destructive operations require explicit confirmation or `--force` flag.
- Pipe output to logs for long-running jobs.
- Use absolute paths in scripts to avoid working-directory surprises.

---

## 🧪 Testing

**What**: Prove it works. Prove it keeps working. **When**: Always.

**Test Pyramid**: ~30% unit, ~40% feature/integration, ~20% component (Vue), ~10% E2E.

| Metric | Minimum | Target |
|---|---|---|
| Line coverage | 80% | 90%+ |
| Branch coverage | 75% | 85%+ |
| Mutation score | 60% | 80%+ |
| Type coverage (PHP) | 90% | 100% |

**PHP — Pest v4** (primary, PHP 8.3+, built on PHPUnit 12): Built-in Playwright browser testing, mutation testing (`--mutate`), architecture testing presets (`php`, `security`, `strict`, `laravel`), test sharding for CI, higher-order testing, Datasets for parameterized tests.

**JavaScript — Vitest 4.0** (primary for Vue/Vite): Native ESM/TypeScript, stable browser mode, visual regression with `toMatchScreenshot()`. Use `@testing-library/vue` with accessibility-first queries (`getByRole`, `getByLabel` — never CSS selectors). **Use `userEvent` over `fireEvent`** for realistic interactions. Co-locate test files with components (`AgentCard.test.ts` next to `AgentCard.vue`).

**E2E — Playwright 1.58**: Page Object Model, role-based locators, `trace: 'on-first-retry'`, `webServer` config pointing to `php artisan serve`. AI-assisted Test Agents (planner, generator, healer via `npx playwright init-agents --loop=claude`).

**Python — pytest + pytest-asyncio + httpx**: `factory-boy` for factories, `pytest-mock` for mocking. FastAPI `TestClient` for sync, `httpx.AsyncClient` for async.

**Cross-service — Pact**: Consumer-driven contract testing between Laravel and FastAPI services. OpenAPI spec validation in both test suites.

**Critical rules**:
- Use the **same database engine** in tests as production (MySQL/PostgreSQL, not SQLite) — accept slower speed for reliability.
- **Mutation testing** (`--mutate`) is the highest-leverage quality indicator — 93% line coverage can still have 15+ significant gaps revealed by mutations.

**CI Enforcement**:
```bash
php artisan test --coverage --min=80 --parallel
./vendor/bin/pest --mutate --min=70 --parallel
npx vitest run --coverage --coverage.thresholds.lines=80
pytest --cov=app --cov-fail-under=80
```

**Recommended Skills**: [`LambdaTest/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/LambdaTest/agent-skills) (46+ testing skills), [`anthropics/skills/webapp-testing`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills), [`obra/superpowers`](https://github.com/garethdaine/agent-skills/tree/main/vendor/obra/superpowers) (TDD), [`playbooks.com/skills/microsoft/playwright-cli`](https://playbooks.com/skills/microsoft/playwright-cli/playwright-cli)

---

## 🔄 DevOps & CI/CD

**GitHub Actions**: Parallel jobs (quality + security → tests → build → deploy). Pin actions to full SHA. Cache aggressively.

**Docker**: Multi-stage builds, non-root user, Alpine variants, health checks, Trivy scanning. **Local Docker Compose** should include: app (PHP-FPM), nginx, db (PostgreSQL 16), redis, horizon, node (Vite dev server, port 5173), and python-api (FastAPI/uvicorn).

**Monitoring**: Sentry (errors) + Laravel Pulse (metrics) + OpenTelemetry with `opentelemetry-auto-laravel` v1.4.0 (distributed tracing) + OpenLLMetry (LLM observability — token counts, cost, decision paths) + Horizon (queues).

**Secrets**: GitHub Actions Secrets, `php artisan env:encrypt`, never `.env` in version control.

**Recommended Skills**: [`hashicorp/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/hashicorp/agent-skills), [`getsentry/skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/getsentry/skills), [`ivangrynenko/cursorrules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) (install: `--tags "category:devops"` for docker-compose-standards, github-actions-standards)

---

## 🛠 Technology-Specific Rules

### PHP
**Standard**: PSR-12, `strict_types`, PHP 8.4+ (8.5 current). `declare(strict_types=1)` in every file. Property hooks (8.4), pipe operator (8.5). Return types on all methods.
**Skills**: [`ivangrynenko/cursorrules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/ivangrynenko/cursorrules) (install: `--tags "language:php"`), [`pekral/cursor-rules`](https://github.com/garethdaine/agent-rules/tree/main/vendor/pekral/cursor-rules) (Composer package: `composer require pekral/cursor-rules --dev`)

### Python
**Standard**: PEP 8, type hints, Python 3.12+ (3.14 current). Pydantic v2 for validation. `uv` for packages, `ruff` for linting, `mypy` for types. FastAPI: dependency injection for sessions and auth.
**Skills**: [`playbooks.com/skills/jezweb/claude-skills/fastapi`](https://playbooks.com/skills/jezweb/claude-skills/fastapi), [`PatrickJS/awesome-cursorrules/rules/py-fast-api`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules/py-fast-api), [`PatrickJS/awesome-cursorrules/rules-new/python.mdc`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules-new)

### JavaScript / TypeScript
**Standard**: ESLint v10 + Prettier. TypeScript strict mode (5.9 current, 7.0 Go compiler in preview). No `any`. `const` by default. Node 24 LTS runs TS natively.
**Skills**: [`vercel-labs/agent-skills/react-best-practices`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills), [`PatrickJS/awesome-cursorrules/rules-new/typescript.mdc`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules-new)

### Rust
Clippy + rustfmt. Edition 2024. No `unwrap()` in production. `thiserror`/`anyhow` for errors.

### Go
`gofmt`, `golangci-lint`. Go 1.24+. Check every error. `context.Context` first parameter for I/O.

### Swift
Swift 6+, SwiftUI, Swift Concurrency. `async/await` over completion handlers.

### C++
C++23+. Smart pointers only. RAII for resources. `constexpr` and `[[nodiscard]]`.

### .NET / C#
C# 12+, .NET 9+. Nullable reference types. `record` for immutable data. `ILogger<T>`.

### Three.js
Three.js r183+, via React Three Fiber where possible. **WebGPU production-ready since r171** with TSL (Three Shader Language) for cross-platform shaders (~95% browser coverage). Dispose geometry/materials/textures on removal. `BufferGeometry` only. Implement LOD for complex scenes. Instancing for repeated geometry.

### Additional Technologies (should-have awareness)

- **Bun** (1.3.x, acquired by Anthropic Dec 2025): 3× faster than Node.js, built-in S3/PostgreSQL/Redis clients. MIT-licensed, open source.
- **PydanticAI** (v1.66.0): Production-ready model-agnostic agent framework, MCP/A2A support. Relevant for FastAPI memory layer.
- **Zod v4**: TypeScript validation (14× faster than v3). Zod Mini (~1.9KB) for browser bundles.
- **gRPC**: Consider for Laravel↔FastAPI communication — benchmarked at 12× fewer round-trips vs REST. Laravel supports via RoadRunner.
- **Meilisearch**: Full-text search for agent logs/knowledge bases via Laravel Scout. Open-source, sub-50ms responses.
- **Turborepo** (2.8.x): Monorepo tooling with incremental builds and remote caching, if project grows to multi-package structure.
- **tRPC v11**: End-to-end type safety with TanStack Query v5 support — relevant if building TypeScript API consumers.

---

## 🗄 Databases

- Indexes on every FK and frequently filtered columns. Reversible migrations.
- Never `SELECT *`. Parameterised queries only. Soft deletes on business entities.
- Cursor pagination for large datasets. Connection pooling configured.
- PostgreSQL: JSONB for semi-structured data, `gen_random_uuid()` for PKs.

### pgvector (AI memory layer)
**pgvector 0.8.2**: HNSW/IVFFlat, up to 64K dimensions. **pgvectorscale 0.7.1** (Timescale): disk-based DiskANN, 28× lower p95 latency than Pinecone. **benbjurstrom/pgvector-scout**: Laravel Scout driver.

**Skills**: [`supabase/agent-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/supabase/agent-skills) — PostgreSQL best practices, RLS, indexing, [`PatrickJS/awesome-cursorrules/rules-new/database.mdc`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules-new)

---

## ⚛️ Frontend Frameworks

**React 19.2+**: Functional components, hooks only. Server Components by default (Next.js).
**Next.js 16+**: App Router, Cache Components (`'use cache'`), `proxy.ts`, Turbopack default.
**Vue 3.5+**: Composition API, `<script setup>`. 56% less memory, 10× faster reactive arrays. (3.6 Vapor Mode in beta — VDOM-free rendering.)
**Nuxt 4+**: `useFetch`/`useAsyncData`, server routes, `app/` directory.
**Tailwind v4**: CSS-first config (`@theme {}`), 5× faster builds. Migrate: `npx @tailwindcss/upgrade`.
**Inertia.js 2.x**: Async requests, deferred props, `<WhenVisible>`. (3.0 in beta.)

**Skills**: [`vercel-labs/agent-skills/react-best-practices`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills) + [`composition-patterns`](https://github.com/garethdaine/agent-skills/tree/main/vendor/vercel-labs/agent-skills) (React/Next.js), [`onmax/nuxt-skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/onmax/nuxt-skills) (Vue/Nuxt), [`Lombiq/Tailwind-Agent-Skills`](https://github.com/garethdaine/agent-skills/tree/main/vendor/Lombiq/Tailwind-Agent-Skills)

---

## 🏗 Laravel & Ecosystem

**Standard**: Laravel 12+, PHP 8.4+, PSR-12. Thin controllers, Actions/Services for logic. Form Requests for validation. Enums over string constants. `$fillable` on every model. Jobs for async. Events for cross-cutting. All config centralised in `bootstrap/app.php` (Http/Kernel removed since Laravel 11) with `withRouting()`, `withMiddleware()`, `withExceptions()`.

### First-Party AI Packages

- **laravel/ai** (v0.2.x): Unified API for OpenAI/Anthropic/Gemini. Agents, tools, structured output, embeddings, audio. `RemembersConversations` trait.
- **laravel/mcp**: Build MCP servers — Tools, Resources, Prompts. OAuth 2.1 + Sanctum.
- **laravel/boost**: 15+ MCP tools, 17K+ vectorized docs. Auto-detects packages, loads matching skills.

### SSE Streaming
`response()->eventStream()` for AI streaming (one-way, simpler than WebSockets). **Laravel Reverb** for bidirectional real-time.

### Ecosystem
**Pennant** (feature flags), **Reverb** (WebSockets, ~30K concurrent), **Cloud** (managed deployment, managed Valkey). Spatie packages: permission, media-library, activitylog, data, query-builder. Notable 12.x features: automatic eager loading (12.8 — tackles N+1 automatically), session cache (per-user scoped), failover queue driver.

**Skills**: Install [Boost](https://laravel.com/docs/12.x/boost), [`PatrickJS/awesome-cursorrules/rules/laravel-php-83`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules/laravel-php-83-cursorrules-prompt-file) + [`laravel-tall-stack`](https://github.com/garethdaine/agent-rules/tree/main/vendor/PatrickJS/awesome-cursorrules/rules/laravel-tall-stack-best-practices-cursorrules-prom)

---

## 🤖 AI APIs

**Universal rules**: Keys in env only. Retry with exponential backoff. Set `max_tokens`. Log prompts (redact PII). Sanitise AI output. Implement timeouts. Track token costs. Treat AI output as untrusted.

### Model Selection (March 2026)

| Provider | Flagship | Balanced | Budget | Context |
|---|---|---|---|---|
| Anthropic | Opus 4.6 | Sonnet 4.6 | Haiku 4.5 | 200K (1M beta) |
| OpenAI | GPT-5.4 Pro | GPT-5.4 | GPT-4.1 mini | Up to 1.05M |
| Google | Gemini 3.1 Pro | Gemini 3 Flash | Flash Lite | 1M |
| xAI | Grok 4 | Grok 4.1 Fast | Grok 3 mini | Up to 2M |

**Default**: `claude-sonnet-4-6`. Complex reasoning: `claude-opus-4-6`. High-volume: `claude-haiku-4-5`.

**OpenAI note**: Responses API replaces Assistants API (sunset Aug 26, 2026). Migrate now.

**Protocols**: MCP (tool integration, Linux Foundation), A2A (Google, agent-to-agent), ACP (agent communication).

**Skills**: [`anthropics/skills/claude-api`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills), [`anthropics/skills/mcp-builder`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills), [`playbooks.com/skills/openai/openai-agents-python/openai-knowledge`](https://playbooks.com/skills/openai/openai-agents-python/openai-knowledge)

---

## ⚙️ AgentOps-Specific Rules

### Job Dispatch Pipeline
- STAR preamble before every job. Jobs declare `capabilities_required`, `estimated_duration`, `reversibility`.
- Progress events on Reverb. Structured error context on failure. **Targeted re-prompting**, not blind retry.

### Agent Architecture
- Isolated agents, no shared mutable state. `capability_profile` and `trust_score` per agent.
- Auditable delegation chains. Permission attenuation at sub-delegation boundaries.
- **Prompt injection defense gap**: No dedicated SKILL.md exists for this yet — mitigate via input sanitisation, output validation, and the OWASP Agentic Top 10 guidelines.

**Agent orchestration skills**: [`obra/superpowers`](https://github.com/garethdaine/agent-skills/tree/main/vendor/obra/superpowers) (brainstorm → write-plan → TDD → systematic-debugging → verification chain), [`anthropics/skills/skill-creator`](https://github.com/garethdaine/agent-skills/tree/main/vendor/anthropics/skills) (meta-skill: create/evaluate other skills), [`google-gemini/gemini-cli/code-reviewer`](https://playbooks.com/skills/google-gemini/gemini-cli/code-reviewer) (professional code reviews).

### Memory Architecture
- **Core Memory**: Editable blocks (identity, facts, preferences).
- **Working Memory**: Session-scoped buffers.
- **Long-term Memory**: pgvector + temporal knowledge graphs.
- **Delegation Memory**: Multi-agent coordination state. Async, non-blocking formation.

### Observability
- Structured log events on all jobs. OpenTelemetry traces. Reverb for real-time status.
- OpenLLMetry for token/cost/latency tracking. Horizon for failure rates.

### Code Quality Gates
1. ✅ Linter passes (zero warnings)
2. ✅ Tests pass (or new tests for new behaviour)
3. ✅ No secrets or debug code committed
4. ✅ STAR verification: result matches defined outcome
5. ✅ Staff engineer standard: "Would a senior dev approve this?"

---

*Document version: 2.0 — March 2026. Update when new patterns are adopted.*
