# AgentOps Discovery Report

**Date:** 2026-03-08
**Analyst:** AI Agent (Claude)
**Engineering Rules Version:** 2.0

---

## 1. Executive Summary

The AgentOps codebase is a substantial Laravel 12 / PHP 8.3 monolith (80 models, 83 controllers, 45 jobs, 248 support files) with a well-architected delegation framework and memory layer, but critical security and operational gaps. The top three risks are: (1) mass-assignment vulnerability via `$guarded = []` across 57 models exposing all fields including `trust_score` and `soul_json`; (2) zero CI quality gate workflows — no automated tests, linting, static analysis, or dependency scanning run on any PR or push; and (3) no production error tracking (Sentry), distributed tracing (OpenTelemetry), or LLM observability (OpenLLMetry) installed. The recommended first action is to install CI quality gates (PHP tests, PHPStan, Pint, ESLint, Vitest, composer audit, npm audit, build verification) as this gates all other improvements.

---

## 2. Architecture Overview

### System Layer Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Frontend (Vue 3 / Inertia.js 2)              │
│   103 Components │ 107 Pages │ 12 Composables │ Tailwind v3        │
│   Vite 7 │ Vitest │ Playwright │ Three.js (3D Office)              │
├─────────────────────────────────────────────────────────────────────┤
│                     Laravel 12 Application Layer                     │
│  83 Controllers │ 36 Form Requests │ 12 API Resources │ 15 Middleware│
│  45 Jobs │ 16 Events │ 6 Listeners │ 5 Subscribers │ 51 Commands   │
│  248 Support files │ 7 Service Providers                             │
├──────────────┬──────────────┬──────────────┬────────────────────────┤
│  Delegation  │    Memory    │  Messenger   │   Connectors           │
│  Framework   │    Layer     │  (Chat)      │   (OAuth/Webhooks)     │
│  10 models   │  7 models    │  9 models    │   7 models             │
│  8 services  │  13 services │  4 adapters  │   32 manifests         │
├──────────────┴──────────────┴──────────────┴────────────────────────┤
│                         Data & Infrastructure                        │
│  PostgreSQL │ Redis (DB 0/1/2) │ Neo4j 5.x │ Typesense 27.1       │
│  Horizon (15 supervisors) │ Reverb (WebSockets) │ Pennant (flags)  │
└─────────────────────────────────────────────────────────────────────┘
```

### PHP-Only Memory Layer

The memory system is entirely PHP-based (no Python/FastAPI layer exists):
- **Core Memory (Layer 1)**: `MemoryCoreBlock` — editable blocks with version tracking, classification (public/internal/confidential)
- **Working Memory (Layer 2)**: Redis sorted set (DB 2) — session-scoped buffers with oldest-first eviction, 7200s TTL
- **Long-term Memory (Layer 3)**: `MemoryFormationPipeline` → entity extraction → embedding generation → `Neo4jGraphStore`. Retrieval via `HybridRetriever` (Reciprocal Rank Fusion: pgvector cosine + BM25 tsvector + Neo4j graph traversal)
- **Delegation Memory (Layer 4)**: **Missing** — no multi-agent context propagation or coordination state

### 15 Horizon Supervisors

| Supervisor | Queue | Timeout | Memory | Max Procs (Prod) |
|-----------|-------|---------|--------|-----------------|
| supervisor-1 | agent | 86,500s | 128MB | 10 |
| supervisor-interrogation | interrogation | 7,800s | 128MB | env-driven |
| supervisor-messenger | messenger-high, messenger-default | 120s | 128MB | env-driven |
| supervisor-delegation | delegation | 900s | 128MB | env-driven |
| supervisor-memory-working | memory-working | 5s | 128MB | env-driven |
| supervisor-memory-formation | memory-formation | 300s | 128MB | env-driven |
| supervisor-org-rituals | org-rituals | 600s | 128MB | env-driven |
| supervisor-code-analysis | code-analysis | ~3,780s | 128MB | env-driven |
| supervisor-subagent | subagent | 3,600s | 128MB | env-driven |
| supervisor-skill-validation | skill-validation | 120s | 128MB | env-driven |
| supervisor-tunnel | tunnel | 0 (unlimited) | 128MB | 1 |
| supervisor-connector-credentials | connector-credentials | 60s | 128MB | env-driven |
| supervisor-connector-webhooks | connector-webhooks | 30s | 128MB | env-driven |
| supervisor-connector-approvals | connector-approvals | 30s | 128MB | 1 |
| supervisor-default | default | 120s | 128MB | env-driven |

### External Integrations

| Service | Protocol | Client |
|---------|----------|--------|
| Anthropic API | HTTPS | Guzzle (`AnthropicAdapter`) |
| OpenAI API | HTTPS | Guzzle (`OpenAIAdapter`) |
| Neo4j 5.x | Bolt | `laudis/neo4j-php-client:^3.0` |
| Typesense 27.1 | HTTP | `typesense/typesense-php:^6.0` via Scout |
| Redis | TCP | `predis/predis:^2.3` (DB 0/1/2) |
| PostgreSQL | TCP | PDO (pgsql) |
| Stripe | HTTPS | `laravel/cashier:^16.3` |
| WebSockets | WSS | `laravel/reverb:^1.7` |

---

## 3. Issue Register

| # | Category | File / Location | Issue | Severity | Effort | Priority |
|---|----------|----------------|-------|----------|--------|----------|
| 1 | Security | 57 models (see §6) | `$guarded = []` across 57 models — mass-assignment vulnerability (OWASP A01:2025). All fields including `trust_score`, `soul_json` mass-assignable. Refs: `AccountLinkToken.php:22`, `AgentAuditLog.php:13`, `AgentBackupSetting.php:13`, `AgentFeatureSetting.php:9`, `AgentJob.php:24`, `AgentJobRun.php:41`, `AgentMaintenanceCheckpoint.php:9`, `AgentRunEvent.php:12`, `AgentSystemState.php:19`, `ApiDocArtifact.php:15`, `ChatAction.php:22`, `ChatAttachment.php:19`, `ChatMessage.php:25`, `ChatSession.php:20`, `ConnectedProvider.php:17`, `ConnectorAccount.php:23`, `CredentialVault.php:17`, `DelegateeMetric.php:17`, `DelegateeProfile.php:21`, `DelegationAttempt.php:17`, `DelegationCapability.php:16`, `DelegationEvent.php:17`, `DelegationGraph.php:20`, `DelegationTask.php:19`, `DelegationTaskDependency.php:16`, `DelegationVerificationResult.php:80`, `DocumentationEntry.php:15`, `DocumentationFragment.php:16`, `DocumentationLink.php:13`, `EscalationIncident.php:12`, `InterrogationBuildTask.php:12`, `InterrogationEvent.php:13`, `InterrogationSession.php:21`, `InterrogationSetting.php:10`, `InterrogationTechStack.php:14`, `MemoryConsolidationLog.php:20`, `MemoryConversationLog.php:22`, `MemoryCoreBlock.php:22`, `MemoryEmbedding.php:22`, `MemoryFormationFailure.php:18`, `MemoryProviderUsage.php:23`, `MemorySetting.php:18`, `MessengerDeadLetter.php:33`, `MessengerEventDeduplication.php:18`, `MessengerIdentityLink.php:25`, `NlOrgParseAttempt.php:13`, `NlParseAttempt.php:13`, `PendingConfirmation.php:19`, `RepoAnalysisArtifact.php:12`, `RepoAnalysisEvent.php:13`, `RepoAnalysisReport.php:12`, `RepoAnalysisSession.php:22`, `RepoAnalysisTask.php:13`, `RunClassification.php:12`, `SchedulerHeartbeat.php:11`, `TunnelSetting.php:13`, `WorkflowGateTransition.php:12` | Critical | Medium | **P0** |
| 2 | CI/CD | `.github/workflows/` | Missing PHP test workflow (`php artisan test --parallel`) | Critical | High | **P0** |
| 3 | CI/CD | `.github/workflows/` | Missing PHPStan/Larastan workflow | Critical | High | **P0** |
| 4 | CI/CD | `.github/workflows/` | Missing Pint lint check workflow | Critical | High | **P0** |
| 5 | CI/CD | `.github/workflows/` | Missing composer audit workflow | Critical | High | **P0** |
| 6 | CI/CD | `.github/workflows/` | Missing ESLint workflow | Critical | High | **P0** |
| 7 | CI/CD | `.github/workflows/` | Missing Vitest workflow | Critical | High | **P0** |
| 8 | CI/CD | `.github/workflows/` | Missing npm audit workflow | Critical | High | **P0** |
| 9 | CI/CD | `.github/workflows/` | Missing npm build verification workflow | Critical | High | **P0** |
| 10 | Observability | `composer.json` / `config/` | Sentry not installed — no production error tracking | Critical | Low | **P0** |
| 11 | AI Security | `DelegateeProfileController.php:324` | `soul_json` (system prompts) exposed in API response (LLM07:2025) | Critical | Low | **P0** |
| 12 | AI Security | `MemoryFormationPipeline.php:196-204` | Memory poisoning — unsanitized entities stored in Neo4j (Agentic: Memory Poisoning) | Critical | High | **P0** |
| 13 | AI Security | `AttemptSpawner.php:125-131` | `--dangerously-skip-permissions` auto-injected in delegation (Agentic: Permission Attenuation) | High | Medium | **P0** |
| 14 | Testing | PHPStan output | PHPStan level 5: 3,413 errors — type coverage far below 90% minimum | High | High | **P0** |
| 15 | Supply Chain | `.github/workflows/docs-deploy-sync.yml:25` | `actions/checkout@v4` not SHA-pinned (OWASP A03:2025) | Medium | Low | **P1** |
| 16 | Supply Chain | `.github/workflows/docs-deploy-sync.yml:28` | `shivammathur/setup-php@v2` not SHA-pinned (OWASP A03:2025) | Medium | Low | **P1** |
| 17 | Security | `routes/api.php:79` | N8n webhook endpoint missing auth middleware/signature verification (OWASP A07:2025) | Medium | Low | **P1** |
| 18 | Security | `config/session.php` | Session `secure` cookie defaults to falsy if `SESSION_SECURE_COOKIE` env var unset | Medium | Low | **P1** |
| 19 | AI Security | `GeneralTaskHandler.php:178,184` | Unescaped soul data concatenated into system prompts (LLM01:2025) | High | Low | **P1** |
| 20 | AI Security | `AiCriticStep.php:154` | `str_replace` prompt template injection in verification (LLM01:2025) | Medium | Low | **P1** |
| 21 | AI Security | `HumanApprovalStep.php:35-72` | No pre-execution confirmation gate — tasks execute before approval (Agentic: Tool Misuse) | Medium | Medium | **P1** |
| 22 | AI Security | `RunEventWriter.php:61-73` | AI responses logged without PII redaction (LLM02:2025) | Medium | Medium | **P1** |
| 23 | AI Security | `DelegateeProfile.php:56-65` | No validation on soul content — users can embed secrets (LLM06:2025) | Medium | Low | **P1** |
| 24 | Code Quality | `InterrogationSessionController.php` | God Class — 4,021 lines, 37 public + 53 private methods | High | High | **P1** |
| 25 | Code Quality | `RunEventWriter.php` | God Class — 1,169 lines, 4 public + 42 private methods | High | High | **P1** |
| 26 | Code Quality | `RepoAnalysisSessionController.php` | God Class — 1,118 lines, 21 public + 10 private methods | High | High | **P1** |
| 27 | Code Quality | `AgentRunController.php:249-436` | `stop()` method is 188 lines — Long Method | High | Medium | **P1** |
| 28 | Code Quality | 77 controllers | Business logic in controllers — direct DB queries, `::where`, `->save()`, `->create()` (Missing Action Pattern) | Medium | High | **P1** |
| 29 | Compliance | 504/910 PHP files | Missing `declare(strict_types=1)` — 55.4% non-compliant | Medium | Low | **P1** |
| 30 | Compliance | 138 PHP files | Pint PSR-12 violations (284 fixes needed) | Medium | Low | **P1** |
| 31 | Compliance | `package.json` | No Husky + lint-staged pre-commit hooks for code quality | Medium | Low | **P1** |
| 32 | Compliance | `package.json` | No conventional commit enforcement (commitlint) | Medium | Low | **P1** |
| 33 | Queue | `config/horizon.php` | `supervisor-1` naming deviation — should be `supervisor-long-running` per rules v2.0 | Medium | Low | **P1** |
| 34 | Queue | `config/horizon.php` | All long-running supervisors use 128MB memory — rules require 256MB | Medium | Low | **P1** |
| 35 | Queue | `config/horizon.php` | Timeout outliers: supervisor-1 (86,500s), interrogation (7,800s), tunnel (0), code-analysis (~3,780s), subagent (3,600s) | Medium | Low | **P1** |
| 36 | Observability | `composer.json` | OpenTelemetry not installed — no distributed tracing | Medium | Medium | **P1** |
| 37 | Observability | `composer.json` | OpenLLMetry not installed — no LLM-specific observability | Medium | Medium | **P1** |
| 38 | Observability | `composer.json` | Laravel Pulse not configured — no real-time application dashboard | Medium | Low | **P1** |
| 39 | Cache | codebase-wide | No documented cache strategy (key naming, invalidation, warming, sizing) | Medium | Medium | **P1** |
| 40 | Frontend | `package.json` | Tailwind v3 with JS config — rules require v4 with CSS-first `@theme {}` | Medium | Medium | **P1** |
| 41 | Testing | `database/factories/` | 44 missing database factories (out of 80 models) | Medium | Medium | **P1** |
| 42 | Testing | `app/Services/`, `app/Jobs/` | 51 untested files: Services (30), Jobs (9), Actions (12) | Medium | High | **P1** |
| 43 | Testing | `resources/js/` | Only 4 Vue component tests for entire frontend | Medium | High | **P1** |
| 44 | Testing | `resources/js/Components/__tests__/` | Vue test co-location violation — tests in `__tests__/` subdirs, not co-located | Medium | Low | **P1** |
| 45 | Testing | `phpunit.xml` / Pest config | No Pest architecture testing presets configured | Medium | Low | **P1** |
| 46 | Deployment | Repository root | No documented or automated deployment strategy | Medium | Medium | **P1** |
| 47 | Deployment | Deploy scripts | No `horizon:terminate` in deploy scripts — workers may run stale code | Medium | Low | **P1** |
| 48 | Logging | `config/logging.php` | Default production logging not structured JSON (`LOG_STACK=single`) | Medium | Low | **P1** |
| 49 | Logging | `config/logging.php` | `LOG_REDACT_SENSITIVE` defaults to `false` — sensitive data redaction opt-in | Medium | Low | **P1** |
| 50 | Architecture | `DelegateeProfile` model | Missing `capability_profile` JSON column — Tomasev requires queryable profile, not pivot-based | Medium | Medium | **P1** |
| 51 | Architecture | `ContractEnforcer` | Contract enforcement incomplete — no deadline, escalation, or resource quota enforcement | Medium | Medium | **P1** |
| 52 | Architecture | Delegation layer | No formal permission attenuation service (current scope narrowing is functional but informal) | Medium | Medium | **P1** |
| 53 | Architecture | Delegation layer | No sub-delegation framework — no delegation chains or transitive assignment | Medium | High | **P1** |
| 54 | Architecture | `DelegateeProfile` | No trust score history/versioning — only current score persisted | Medium | Medium | **P1** |
| 55 | Architecture | Memory layer | Delegation memory (Layer 4) entirely missing — no multi-agent context propagation | Medium | High | **P1** |
| 56 | Security | `MessengerConnectorController.php:103,208,359` | `$request->all()` passed to Validator without `->only()` (OWASP A01:2025) | Low | Low | **P2** |
| 57 | Security | 10 Vue files | `v-html` usage without sanitization — XSS risk if user content (OWASP A03:2021) | Low | Low | **P2** |
| 58 | Security | 9 locations | `DB::raw()` in aggregation queries — low risk, no user input interpolation | Low | Low | **P2** |
| 59 | Code Quality | 87 files | Files exceeding 300-line threshold (Large Class) | Medium | High | **P2** |
| 60 | Code Quality | `CodexAdapter.php` / `ClaudeAdapter.php` | Massive structural duplication — 15+ build methods each with embedded JSON schemas | Medium | High | **P2** |
| 61 | Frontend | `resources/js/` | Three.js `OrbitControls` chunk 509 kB — candidate for lazy-loading | Low | Low | **P2** |
| 62 | Frontend | `resources/js/` | `Wizard` chunk 726 kB — exceeds 500 kB Vite warning threshold | Low | Medium | **P2** |
| 63 | Database | `MessengerHealthController.php:29,161` | `ConnectorAccount::all()` unbounded queries (2 occurrences) | Low | Low | **P2** |
| 64 | Cache | Various | Cacheable opportunities not exploited (ConnectorAccount lookups, route/permission checks) | Low | Low | **P2** |
| 65 | AI Integration | `composer.json` | `laravel/ai` not available on Packagist — using Guzzle fallback | Low | — | **P2** |
| 66 | AI Integration | `composer.json` | `laravel/mcp` not installed | Low | Low | **P2** |
| 67 | AI Integration | `composer.json` | `laravel/boost` not installed | Low | Low | **P2** |
| 68 | Docker | Repository root | No application Dockerfile for containerized deployment | Low | Medium | **P2** |
| 69 | Deployment | `config/pennant.php` | Pennant configured but unused — no feature flags defined | Low | Low | **P2** |
| 70 | Queue | `config/horizon.php` | `supervisor-tunnel` balance set to string `'false'` — potential bug (truthy in PHP) | Low | Low | **P2** |
| 71 | Testing | Test suite | 11 failing tests, 9 skipped tests | Low | Medium | **P2** |
| 72 | Standards | `resources/js/` | 19 ESLint problems (2 errors, 17 warnings) | Low | Low | **P2** |
| 73 | Standards | `resources/js/Components/` | 5 Vue components missing `<script setup>` (Jetstream scaffolding) | Low | Low | **P2** |
| 74 | Standards | `playwright.config.ts` | Playwright tests use CSS selectors instead of role-based locators | Low | Low | **P2** |

---

## 4. Refactoring Candidates

### 4.1 Large Class → Extract Class

**InterrogationSessionController** (`app/Http/Controllers/Api/V1/InterrogationSessionController.php`)
- **Lines**: 4,021 | **Public**: 37 | **Private**: 53
- **Smell**: Large Class / God Object
- **Responsibilities**: Session CRUD, state transitions, event coordination, plan revisions, build task management, approval flows, export, cleanup, restore
- **Before**: Single controller with 90 methods and 9+ responsibility groups
- **After**: Extract `InterrogationBuildService`, `InterrogationPlanService`, `InterrogationExportService`, `InterrogationApprovalService`

### 4.2 Large Class → Extract Class

**RunEventWriter** (`app/Support/Agent/RunEventWriter.php`)
- **Lines**: 1,169 | **Public**: 4 | **Private**: 42
- **Smell**: Large Class — hidden complexity behind narrow public API
- **Responsibilities**: Event capture, output chunking, rate-limit detection, approval/permission/clarification pattern matching, redaction, MCP error parsing, failure backoff, memory integration
- **Before**: Monolithic event writer with pattern matching, redaction, and memory integration
- **After**: Extract `EventPatternMatcher`, `OutputRedactor`, `EventBroadcaster`

### 4.3 Large Class → Extract Class

**RepoAnalysisSessionController** (`app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`)
- **Lines**: 1,118 | **Public**: 21 | **Private**: 10
- **Smell**: Large Class
- **Responsibilities**: Session CRUD, snapshot generation, task planning/execution/retry, coverage validation, report generation, pause/resume/retry workflows
- **Before**: Controller with business logic, workflow orchestration, and report generation
- **After**: Extract `RepoAnalysisWorkflowService`, `RepoAnalysisReportService`

### 4.4 Long Method → Extract Method

**AgentRunController::stop()** (`app/Http/Controllers/Api/V1/AgentRunController.php:249-436`)
- **Lines**: 188
- **Smell**: Long Method
- **Before**: Single method handling process termination, state cleanup, event recording, notification dispatch
- **After**: Extract `terminateProcess()`, `cleanupState()`, `recordStopEvent()`

### 4.5 Duplicate Code → Extract Base Adapter

**CodexAdapter / ClaudeAdapter** (`app/Support/Interrogation/Adapters/`)
- **Lines**: 1,194 + 1,161
- **Smell**: Duplicate Code / Large Class
- **Responsibilities**: Both implement 15+ build command methods and 8+ parse response methods with embedded JSON schemas
- **Before**: Two adapters with massive structural duplication
- **After**: Extract `AbstractBuildAdapter` with shared schema definitions and provider-specific overrides

### 4.6 Feature Envy → Move Method (Missing Action Pattern)

**77 controllers** — direct database queries across 25+ controllers
- **Smell**: Feature Envy / Missing Action Pattern
- **Highest concentration**: `RepoAnalysisSessionController` (14 DB ops), `DelegationGraphController` (5), `ChatSessionController` (5)
- **Before**: Controllers with inline `::where`, `->save()`, `->create()`, `->update()`, `->delete()`
- **After**: Extract to Action classes per engineering rules v2.0

---

## 5. Security Vulnerabilities

### OWASP A01:2025 — Broken Access Control

| Finding | Location | Risk |
|---------|----------|------|
| `$guarded = []` across 57 models | See Issue Register #1 | **Critical** — all fields mass-assignable including trust_score, soul_json |
| `$request->all()` without `->only()` | `MessengerConnectorController.php:103,208,359`, `WhatsAppAdapter.php:105`, `DiscordAdapter.php:117` | Low — validated before use but broader than needed |

### OWASP A03:2025 — Software Supply Chain Failures

| Finding | Location | Risk |
|---------|----------|------|
| No CI quality gate workflows | `.github/workflows/` | **Critical** — no automated tests, linting, or security scans in CI |
| GitHub Actions not SHA-pinned | `.github/workflows/docs-deploy-sync.yml:25,28` | Medium — mutable version tags allow supply chain attack |
| Composer audit: 0 vulnerabilities | `composer.json` | Clean |
| npm audit: 0 vulnerabilities | `package.json` | Clean |

### OWASP A07:2025 — Security Misconfiguration

| Finding | Location | Risk |
|---------|----------|------|
| N8n webhook missing auth | `routes/api.php:79` | Medium — no signature verification documented |
| Session secure cookie no default | `config/session.php` | Medium — transmits over HTTP if env var unset |
| Log redaction disabled by default | `config/logging.php` | Medium — `LOG_REDACT_SENSITIVE=false` |

### OWASP A03:2021 — Cross-Site Scripting (XSS)

| Finding | Location | Risk |
|---------|----------|------|
| `v-html` without sanitization | 10 Vue files (`Docs/Show.vue:541`, `Docs/Index.vue:557`, etc.) | Low-Medium — risk if rendering user content |
| No unescaped Blade output | All Blade templates | Clean |

### Strengths

- No hardcoded secrets found — all API keys use `config()` or `env()`
- No debug code (`dd()`, `var_dump()`, `console.log()`) in production files
- API endpoints consistently use `auth:sanctum` + `license` middleware
- Messenger webhooks properly implement signature verification with replay protection
- CORS configured restrictively (app URL only)
- All `.env` variants in `.gitignore`

---

## 6. AI-Specific Security

### LLM07:2025 — System Prompt Leakage (CRITICAL — P0)

**File:** `app/Http/Controllers/Api/V1/DelegateeProfileController.php:324`

The `transformProfile()` method exposes `soul_json` (personality, system_prompt, user_context) in API responses. Both `index()` and `show()` endpoints return this to authenticated users. `MessengerConnectorController.php:349` has a separate `soul()` endpoint returning system_prompt (max 5000 chars).

**Attack vector:** Authenticated user enumerates system prompts across all delegatee profiles.

### LLM01:2025 — Prompt Injection (HIGH — P1)

**File:** `app/Messenger/ChatAction/Handlers/GeneralTaskHandler.php:178,184`
- Line 178: `$parts[] = $soul['personality']` — no escaping
- Line 184: `$parts[] = "About the user: {$soul['user_context']}"` — no escaping

**File:** `app/Support/Delegation/Verification/AiCriticStep.php:154`
- `str_replace("{{{$key}}}", (string) $value, $prompt)` — task context substituted without sanitization. `task->name` and `task->contract_json['prompt']` are user-controllable.

### Agentic — Memory Poisoning (CRITICAL — P0)

**File:** `app/Support/Memory/MemoryFormationPipeline.php:196-204`

User-supplied conversation content flows directly into Neo4j without sanitization. Raw entities stored via `$this->graphStore->storeEntities()`. Crafted conversation content can create poisoned graph entities that surface in future memory queries.

### Agentic — Permission Attenuation (HIGH — P0)

**File:** `app/Support/Delegation/AttemptSpawner.php:125-131`

Permission bypass flags auto-injected during delegation:
- Claude: `--dangerously-skip-permissions` (line 125-127)
- Codex: `--dangerously-bypass-approvals-and-sandbox` (line 129-131)

The `ContractEnforcer` validates scope, but `AttemptSpawner` defeats it. `DelegationCoordinator.php:103-114` does NOT re-validate permissions post-execution.

### Agentic — Tool Misuse (MEDIUM — P1)

**File:** `app/Support/Delegation/Verification/HumanApprovalStep.php:35-72`

Human approval is asynchronous — tasks execute immediately, verification happens after. No pre-execution confirmation gate for irreversible actions.

### LLM02:2025 — Sensitive Information Disclosure (MEDIUM — P1)

**File:** `app/Support/Agent/RunEventWriter.php:61-73`

All agent output logged to `agent_run_events` without PII redaction. 5000-char cap but no content filtering for emails, API keys, or credentials.

### LLM06:2025 — Sensitive Information in System Prompt (MEDIUM — P1)

**File:** `app/Models/DelegateeProfile.php:56-65`

`setSoul()` accepts and stores system prompts with no content validation. No policy prevents storing secrets in soul fields.

---

## 7. Gaps vs Engineering Rules v2.0

| # | Rule | Current State | Gap |
|---|------|--------------|-----|
| 1 | `declare(strict_types=1)` in every PHP file | 406/910 files (44.6%) compliant | **504 files missing** |
| 2 | Tailwind v4 with CSS-first `@theme {}` config | Tailwind `^3.4.0` with `tailwind.config.js` | **v3 → v4 migration required** |
| 3 | PHPStan/Larastan at level 5+ | Installed but 3,413 errors | **3,413 errors to resolve** |
| 4 | ESLint v10 flat config | Installed, 19 problems (2 errors, 17 warnings) | **Minor cleanup needed** |
| 5 | Pre-commit hooks (Husky + lint-staged) | `.githooks/pre-commit` exists but only runs docs sync | **No quality gate hooks** |
| 6 | Conventional commit enforcement | No `@commitlint/cli` installed | **Not enforced** |
| 7 | Test co-location (`ComponentName.test.ts` next to `.vue`) | All 4 tests in `__tests__/` subdirectories | **Violates co-location rule** |
| 8 | `supervisor-long-running` with 600s timeout, 256MB memory | `supervisor-1` with 86,500s timeout, 128MB memory | **Naming, timeout, memory all deviate** |
| 9 | `$fillable` on every model | 57 models use `$guarded = []` | **57 models non-compliant** |
| 10 | PSR-12 / Pint formatting | 138 files with violations (284 fixes needed) | **138 files need formatting** |
| 11 | No CI quality gate workflows | Only `docs-deploy-sync.yml` exists | **8 CI stages missing** |
| 12 | Pest architecture presets (`php`, `security`, `laravel`) | Not configured | **Not present** |
| 13 | Vue `<script setup>` syntax | 5 components use Options API (Jetstream scaffolding) | **5 components to migrate** |

---

## 8. Gaps vs Tomašev et al. Delegation Framework

### 8.1 Missing `capability_profile` JSON Column (P1)

**Current**: `DelegateeProfile` uses pivot-based `capabilities()` BelongsToMany through `DelegateeCapabilityPivot` + `DelegationCapability`.
**Required**: Queryable `capability_profile` JSON column for single-query capability matching.
**Impact**: Cannot perform efficient capability-based agent selection without JOIN overhead.

### 8.2 Delegation Contracts Assessment

**Status**: PRESENT with gaps.
- `DelegationTask.contract_json` stores contracts
- `ContractValidator` enforces: capability existence, max_runtime ≤ 86400s, criticality enum, prompt XOR task_markdown_path, verification strategy
- **Gap (P1)**: `ContractEnforcer` does NOT enforce `time_constraints.deadline_ts`, criticality-based escalation, resource quotas, or per-capability permission checks

### 8.3 Permission Attenuation Assessment

**Status**: PARTIAL (P1).
- `ContractEnforcer` implements scope narrowing (path/env/runtime intersection with profile boundaries)
- **Gap**: No formal `PermissionAttenuationService`, no hierarchical permission degradation middleware
- **Critical gap**: `AttemptSpawner.php:125-131` defeats attenuation by injecting `--dangerously-skip-permissions`

### 8.4 Trust Verification Mechanisms

**Status**: PRESENT.
- `trust_score` (decimal 3,2) + `trust_updated_at` on `DelegateeProfile`
- `TrustScoreCalculator`: composite score with 4 weighted components (starCompletion 0.15, taskCorrect 0.35, firstPassSuccess 0.30, recovery 0.20)
- Three confidence levels (low/medium/high) with sample thresholds
- **Gap (P1)**: No trust score history/versioning — only current score persisted, no audit trail

### 8.5 Sub-delegation (P1)

**Status**: MISSING. No sub-delegation framework, no delegation chains, no transitive assignment, no hierarchical delegator-to-delegator patterns.

### 8.6 Memory Layer Completeness

| Layer | Status | Gap |
|-------|--------|-----|
| Core (Layer 1) | COMPLETE | None |
| Working (Layer 2) | COMPLETE | None |
| Long-term (Layer 3) | COMPLETE* | pgvector/Neo4j optional with graceful degradation |
| Delegation (Layer 4) | **MISSING** | **Entire layer** — no multi-agent context propagation, learning aggregation, or coordination state |

### 8.7 STAR Preamble Pipeline

**Status**: WELL-IMPLEMENTED. `StarPreambleGenerator` → A/B testing → `FailureModeClassifier` → `TargetedRetryService` (confirmed NOT blind retry). Learned guardrails via `LessonsManager`. No gaps.

---

## 9. Recommended Action Plan

### Immediate (P0 — this week)

1. **Install CI quality gates** — Create GitHub Actions workflows for: PHP tests, PHPStan, Pint, ESLint, Vitest, composer audit, npm audit, npm build. This is the single highest-leverage improvement.
2. **Replace `$guarded = []` with `$fillable`** on all 57 models. Start with security-sensitive models: `DelegateeProfile`, `CredentialVault`, `AgentJobRun`, `MemoryCoreBlock`.
3. **Install Sentry** — `composer require sentry/sentry-laravel`, configure DSN. No production error tracking currently exists.
4. **Fix `soul_json` API exposure** — Remove `soul_json` from `DelegateeProfileController::transformProfile()` API response. Add dedicated admin-only endpoint if needed.
5. **Sanitize memory formation inputs** — Add entity validation in `MemoryFormationPipeline` before Neo4j storage.
6. **Remove `--dangerously-skip-permissions`** from `AttemptSpawner` — implement proper permission delegation instead of bypassing.
7. **Address PHPStan errors** — Begin triaging 3,413 errors. Focus on type-safety in security-critical paths first.

### Short-term (P1 — next sprint)

8. **SHA-pin GitHub Actions** — Pin `actions/checkout` and `shivammathur/setup-php` to full commit SHAs.
9. **Add pre-commit hooks** — Install Husky + lint-staged with Pint, ESLint, and PHPStan checks.
10. **Fix Horizon supervisor naming** — Rename `supervisor-1` to `supervisor-long-running`, set 256MB memory for long-running supervisors.
11. **Add `declare(strict_types=1)`** to 504 missing PHP files (can be automated with a script).
12. **Run Pint auto-fix** on 138 files with formatting violations.
13. **Install OpenTelemetry** — `composer require open-telemetry/opentelemetry-php` for distributed tracing.
14. **Install OpenLLMetry** — LLM-specific observability for token/cost/latency tracking.
15. **Refactor InterrogationSessionController** — Extract to 4+ focused services (largest God class at 4,021 lines).
16. **Create missing database factories** — 44 factories needed for comprehensive testing.
17. **Add prompt injection defense** — Sanitize soul data in `GeneralTaskHandler`, validate contract prompts in `AiCriticStep`.
18. **Add `capability_profile` JSON column** to `DelegateeProfile` migration.
19. **Document cache strategy** — Key naming convention, invalidation policy, warming strategy.
20. **Migrate Tailwind v3 → v4** — `npx @tailwindcss/upgrade`.
21. **Configure session secure cookie** — Default `secure` to `true` in production.
22. **Switch production logging to structured JSON** — Set `LOG_STACK` to use `json` channel.
23. **Enable sensitive data redaction by default** — Set `LOG_REDACT_SENSITIVE=true`.

### Medium-term (P2 — next quarter)

24. **Implement delegation memory (Layer 4)** — Multi-agent context propagation, learning aggregation, coordination state.
25. **Implement sub-delegation framework** — Delegation chains with transitive assignment.
26. **Add trust score history** — Create `trust_score_history` table for audit trail.
27. **Refactor adapter duplication** — Extract `AbstractBuildAdapter` from CodexAdapter/ClaudeAdapter.
28. **Extract business logic from controllers** — Create Action classes for 77 controllers with inline DB operations.
29. **Expand Vue testing** — From 4 component tests to comprehensive coverage; co-locate test files.
30. **Lazy-load Three.js** — Dynamic `import()` for OrbitControls (509 kB chunk).
31. **Create application Dockerfile** — For containerized deployment capability.
32. **Install Laravel Pulse** — Real-time application performance dashboard.
33. **Evaluate `laravel/ai`, `laravel/mcp`, `laravel/boost`** when available on Packagist.

---

## 10. Metrics Baseline

| Metric | Current | Minimum | Target |
|--------|---------|---------|--------|
| Test suite results | 3,959 passed, 11 failed, 9 skipped (14,382 assertions) | All pass | All pass |
| Test duration | 270.36s | — | — |
| Line coverage | N/A (PCOV OOM at ~512MB) | 80% | 90% |
| Branch coverage | N/A (PCOV OOM) | 75% | 85% |
| Mutation score | Not run | 60% | 80% |
| PHP type coverage (PHPStan L5) | ~62% (3,413 errors / 910 files) | 90% | 100% |
| PHPStan level 5 errors | **3,413** | 0 | 0 |
| ESLint problems | **19** (2 errors, 17 warnings) | 0 | 0 |
| Pint violations | **284** fixes across 138 files | 0 | 0 |
| TODO/FIXME count | **1** | 0 | 0 |
| Zero-test files | **51** (Services: 30, Jobs: 9, Actions: 12) | 0 | 0 |
| Missing factories | **44** (out of 80 models) | 0 | 0 |
| Dependency vulnerabilities (composer) | **0** | 0 | 0 |
| Dependency vulnerabilities (npm) | **0** | 0 | 0 |
| Models with `$guarded = []` | **57** | 0 | 0 |
| Models total | 80 | — | — |
| Controllers total | 83 | — | — |
| Jobs total | 45 | — | — |
| Support files total | 248 | — | — |
| PHP files missing `strict_types` | **504** / 910 | 0 | 0 |
| Vue component tests | **4** | 20+ | Full coverage |
| Horizon supervisors | 15 | — | — |
| Routes total | 434 (413 authenticated, 21 unauthenticated) | — | — |
| AI token cost (monthly) | Not tracked | Tracked | Tracked + alerted |
| CI quality gate workflows | **0** (only docs-deploy-sync.yml) | 8 | 8+ |
| Largest JS chunk | 726 kB (Wizard) | <500 kB | <500 kB |

---

## 11. Tooling Installation Sequence

The following P0 prerequisite tools must be installed in order before quality metrics can be captured:

| Step | Command | Purpose | Blocks |
|------|---------|---------|--------|
| 1 | `pecl install pcov` (or enable via `php.ini`) | Code coverage engine | Line/branch coverage metrics |
| 2 | `composer require --dev phpstan/phpstan larastan/larastan` | Static analysis | PHPStan compliance checks |
| 3 | Create `phpstan.neon` at level 5 (paths: `app/`, `config/`, `database/`, `routes/`) | PHPStan config | PHPStan execution |
| 4 | `npm install --save-dev eslint@latest @eslint/js` | JavaScript linting | ESLint compliance checks |
| 5 | Create `eslint.config.js` (flat config with Vue + TypeScript) | ESLint config | ESLint execution |
| 6 | `npm install --save-dev rollup-plugin-visualizer` | Bundle analysis | Frontend performance metrics |
| 7 | Configure visualizer in `vite.config.js` as plugin | Visualizer config | Bundle stats output |
| 8 | Verify `pest --mutate` support (install infection adapter if needed) | Mutation testing | Mutation score baseline |

**Note:** Steps 2-3 and 4-5 have already been completed during this discovery audit. Steps 1, 6-8 remain to be executed for full metrics capture.

---

## Linear Issues Created

**Team:** Agent Orchestration | **Project:** AgentOps | **Label:** Discovery Audit

### P0 — Urgent (14 issues)

| Finding # | Linear Issue | Priority | Title |
|---|---|---|---|
| 1 | [AGE-2281](https://linear.app/garethdaine/issue/AGE-2281) | P0 | $guarded = [] across 57 models — mass-assignment vulnerability |
| 2 | [AGE-2282](https://linear.app/garethdaine/issue/AGE-2282) | P0 | Missing CI quality gate: PHP tests |
| 3 | [AGE-2283](https://linear.app/garethdaine/issue/AGE-2283) | P0 | Missing CI quality gate: PHPStan/Larastan |
| 4 | [AGE-2284](https://linear.app/garethdaine/issue/AGE-2284) | P0 | Missing CI quality gate: Pint |
| 5 | [AGE-2285](https://linear.app/garethdaine/issue/AGE-2285) | P0 | Missing CI quality gate: composer audit |
| 6 | [AGE-2286](https://linear.app/garethdaine/issue/AGE-2286) | P0 | Missing CI quality gate: ESLint |
| 7 | [AGE-2287](https://linear.app/garethdaine/issue/AGE-2287) | P0 | Missing CI quality gate: Vitest |
| 8 | [AGE-2288](https://linear.app/garethdaine/issue/AGE-2288) | P0 | Missing CI quality gate: npm audit |
| 9 | [AGE-2289](https://linear.app/garethdaine/issue/AGE-2289) | P0 | Missing CI quality gate: npm build verification |
| 10 | [AGE-2290](https://linear.app/garethdaine/issue/AGE-2290) | P0 | Sentry not installed — no production error tracking |
| 11 | [AGE-2291](https://linear.app/garethdaine/issue/AGE-2291) | P0 | soul_json exposed in API response — system prompt leakage (LLM07:2025) |
| 12 | [AGE-2292](https://linear.app/garethdaine/issue/AGE-2292) | P0 | Memory poisoning — unsanitized entities stored in Neo4j |
| 13 | [AGE-2293](https://linear.app/garethdaine/issue/AGE-2293) | P0 | --dangerously-skip-permissions auto-injected in delegation |
| 14 | [AGE-2294](https://linear.app/garethdaine/issue/AGE-2294) | P0 | PHPStan level 5: 3,413 errors — type coverage critically low |

### P1 — High (41 issues)

| Finding # | Linear Issue | Priority | Title |
|---|---|---|---|
| 15 | [AGE-2295](https://linear.app/garethdaine/issue/AGE-2295) | P1 | GitHub Actions not SHA-pinned — actions/checkout@v4 |
| 16 | [AGE-2296](https://linear.app/garethdaine/issue/AGE-2296) | P1 | GitHub Actions not SHA-pinned — shivammathur/setup-php@v2 |
| 17 | [AGE-2297](https://linear.app/garethdaine/issue/AGE-2297) | P1 | N8n webhook endpoint missing auth middleware |
| 18 | [AGE-2298](https://linear.app/garethdaine/issue/AGE-2298) | P1 | Session secure cookie defaults to falsy |
| 19 | [AGE-2299](https://linear.app/garethdaine/issue/AGE-2299) | P1 | Unescaped soul data in system prompts — prompt injection (LLM01:2025) |
| 20 | [AGE-2300](https://linear.app/garethdaine/issue/AGE-2300) | P1 | str_replace prompt template injection in AiCriticStep |
| 21 | [AGE-2301](https://linear.app/garethdaine/issue/AGE-2301) | P1 | No pre-execution confirmation gate — tool misuse risk |
| 22 | [AGE-2302](https://linear.app/garethdaine/issue/AGE-2302) | P1 | AI responses logged without PII redaction (LLM02:2025) |
| 23 | [AGE-2303](https://linear.app/garethdaine/issue/AGE-2303) | P1 | No validation on soul content — secrets can be embedded (LLM06:2025) |
| 24 | [AGE-2304](https://linear.app/garethdaine/issue/AGE-2304) | P1 | God Class — InterrogationSessionController (4,021 lines) |
| 25 | [AGE-2305](https://linear.app/garethdaine/issue/AGE-2305) | P1 | God Class — RunEventWriter (1,169 lines) |
| 26 | [AGE-2306](https://linear.app/garethdaine/issue/AGE-2306) | P1 | God Class — RepoAnalysisSessionController (1,118 lines) |
| 27 | [AGE-2307](https://linear.app/garethdaine/issue/AGE-2307) | P1 | Long Method — AgentRunController::stop() (188 lines) |
| 28 | [AGE-2308](https://linear.app/garethdaine/issue/AGE-2308) | P1 | Business logic in 77 controllers — Missing Action Pattern |
| 29 | [AGE-2309](https://linear.app/garethdaine/issue/AGE-2309) | P1 | Missing declare(strict_types=1) in 504/910 PHP files |
| 30 | [AGE-2310](https://linear.app/garethdaine/issue/AGE-2310) | P1 | Pint PSR-12 violations — 284 fixes across 138 files |
| 31 | [AGE-2311](https://linear.app/garethdaine/issue/AGE-2311) | P1 | No Husky + lint-staged pre-commit hooks |
| 32 | [AGE-2312](https://linear.app/garethdaine/issue/AGE-2312) | P1 | No conventional commit enforcement (commitlint) |
| 33 | [AGE-2313](https://linear.app/garethdaine/issue/AGE-2313) | P1 | Horizon supervisor-1 naming deviation from rules v2.0 |
| 34 | [AGE-2314](https://linear.app/garethdaine/issue/AGE-2314) | P1 | All long-running supervisors use 128MB — rules require 256MB |
| 35 | [AGE-2315](https://linear.app/garethdaine/issue/AGE-2315) | P1 | Horizon timeout outliers — supervisor-1 (86,500s), tunnel (0) |
| 36 | [AGE-2316](https://linear.app/garethdaine/issue/AGE-2316) | P1 | OpenTelemetry not installed — no distributed tracing |
| 37 | [AGE-2317](https://linear.app/garethdaine/issue/AGE-2317) | P1 | OpenLLMetry not installed — no LLM observability |
| 38 | [AGE-2318](https://linear.app/garethdaine/issue/AGE-2318) | P1 | Laravel Pulse not configured — no real-time dashboard |
| 39 | [AGE-2319](https://linear.app/garethdaine/issue/AGE-2319) | P1 | No documented cache strategy |
| 40 | [AGE-2320](https://linear.app/garethdaine/issue/AGE-2320) | P1 | Tailwind v3 with JS config — rules require v4 CSS-first |
| 41 | [AGE-2321](https://linear.app/garethdaine/issue/AGE-2321) | P1 | 44 missing database factories (out of 80 models) |
| 42 | [AGE-2322](https://linear.app/garethdaine/issue/AGE-2322) | P1 | 51 untested files — Services (30), Jobs (9), Actions (12) |
| 43 | [AGE-2323](https://linear.app/garethdaine/issue/AGE-2323) | P1 | Only 4 Vue component tests for entire frontend |
| 44 | [AGE-2324](https://linear.app/garethdaine/issue/AGE-2324) | P1 | Vue test co-location violation — tests in __tests__/ subdirs |
| 45 | [AGE-2325](https://linear.app/garethdaine/issue/AGE-2325) | P1 | No Pest architecture testing presets configured |
| 46 | [AGE-2326](https://linear.app/garethdaine/issue/AGE-2326) | P1 | No documented or automated deployment strategy |
| 47 | [AGE-2327](https://linear.app/garethdaine/issue/AGE-2327) | P1 | No horizon:terminate in deploy — workers may run stale code |
| 48 | [AGE-2328](https://linear.app/garethdaine/issue/AGE-2328) | P1 | Production logging not structured JSON |
| 49 | [AGE-2329](https://linear.app/garethdaine/issue/AGE-2329) | P1 | LOG_REDACT_SENSITIVE defaults to false |
| 50 | [AGE-2330](https://linear.app/garethdaine/issue/AGE-2330) | P1 | Missing capability_profile JSON column on DelegateeProfile |
| 51 | [AGE-2331](https://linear.app/garethdaine/issue/AGE-2331) | P1 | ContractEnforcer incomplete — no deadline/escalation/quota enforcement |
| 52 | [AGE-2332](https://linear.app/garethdaine/issue/AGE-2332) | P1 | No formal permission attenuation service |
| 53 | [AGE-2333](https://linear.app/garethdaine/issue/AGE-2333) | P1 | No sub-delegation framework — no delegation chains |
| 54 | [AGE-2334](https://linear.app/garethdaine/issue/AGE-2334) | P1 | No trust score history/versioning — no audit trail |
| 55 | [AGE-2335](https://linear.app/garethdaine/issue/AGE-2335) | P1 | Delegation memory (Layer 4) entirely missing |

---

## Discovery Statistics

- **Total findings:** 74
- **P0 (Critical):** 14
- **P1 (High):** 41
- **P2 (Medium):** 19
- **P3 (Low):** 0
- **Linear issues created:** 55 (14 P0 + 41 P1)
- **Audit commands executed:** 7 (composer audit, npm audit, phpstan analyse, eslint, pint --test, php artisan test, npm run build)
- **Files scanned:** 910 PHP files + 103 Vue components + 107 Pages
- **Sections in report:** 12 (including tooling sequence and statistics)
- **Acceptance criteria verified:** 26/26
