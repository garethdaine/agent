# Codebase Review Report

**Generated:** 2026-03-06
**Ritual Run:** SOLID Analysis (Tasks 82-90)
**Target:** `/Users/garethdaine/Code/agent`

---

## Executive Summary

_Synthesized by the Engineering Lead after council deliberation._

Seven specialist review passes -- SOLID Analyst (Task 88), Laravel Specialist (Task 86), Design Pattern Expert (Task 87), Code Quality Inspector (Task 82) -- produced 500+ combined findings across the full application: 75 models, 70+ controllers, 39 jobs, 32 commands, 211 support classes, 14 middleware, 7 listeners, 55+ messenger handlers. Two adversarial reviews (Tasks 83/89) challenged these findings: dismissing 13 as false positives, downgrading 14 as overstated severity, rejecting 12 over-engineering suggestions, confirming 4 as P0, and surfacing 5 new findings missed by all specialist reviews.

After deduplication and adversarial calibration, **~110 true unique findings** exist (vs. 500+ reported), consolidated into **42 actionable issues** across 3 priority tiers plus a "do not do" list:

| Priority | Count | Description |
|----------|-------|-------------|
| P0 -- Fix Now | 4 | Authorization gaps, cross-user data exposure, env var leakage, raw user model |
| P1 -- Fix Soon | 20 | Cache coherency, logic bugs, race conditions, resilience, memory growth, observability, CLI password exposure, non-idempotent keys, SQL bugs |
| P2 -- Fix When Touched | 18 | DRY extraction, template precision, null handling, enums, job config, redaction, frontend fixes, cache poisoning |
| Do Not Do | 12 | Over-engineering suggestions rejected by adversarial review |

**Key theme:** The codebase has strong foundations -- clean domain organization across 9 feature domains, 32 FormRequest classes, 14 policies, proper Sanctum auth, excellent DTO/Result patterns, Strategy/Adapter/Builder pattern usage, Circuit Breaker in Messenger, and focused utility services. The most urgent issues are **authorization gaps on admin-facing endpoints** and **data scoping bugs** that expose cross-user data. Structural concerns (large classes, missing interfaces) are real maintenance debt but lower priority than runtime security and data integrity bugs.

**Overall Score: 72/100** (projected 85/100 after P0-P1 fixes, 91/100 after all phases)

**Test Coverage: ~52% overall** (375 tests across 720 files; 75% services, 53% controllers, 38% commands, 28% jobs)

### SOLID Scorecard

| Principle | Score | Key Issue |
|-----------|-------|-----------|
| **S** -- Single Responsibility | 52/100 | 7+ God Classes across Services/Jobs/Support |
| **O** -- Open/Closed | 63/100 | Hardcoded registries, match-based factories, magic string task names |
| **L** -- Liskov Substitution | 85/100 | Generally strong contract compliance |
| **I** -- Interface Segregation | 75/100 | Some oversized interfaces; `ConnectorAdapterInterface` has 10 methods |
| **D** -- Dependency Inversion | 48/100 | 35 `app()` calls in Jobs layer; only 4 contracts in `app/Contracts/` |

---

## Critical Findings

| # | File | Issue | Severity | Category |
|---|------|-------|----------|----------|
| 1 | `ConfigurationController.php` | No role-based authorization -- any authenticated user can **write** system configuration via `PUT /configuration`. Routes are behind `auth:sanctum` + `license`, but no `$this->authorize()` or `Gate::allows()` check. | P0 | Security |
| 2 | `OfficeStateController.php:249-264,363-391` | `buildMessengerState()` queries ALL `ConnectorAccount` records; `buildEscalationsState()` queries ALL `OrgEscalation` and `EscalationIncident` records -- cross-user data exposure. 6 other state methods are properly user-scoped. | P0 | Security |
| 3 | `CliRuntimeExecutor.php:72-80` | Parent env vars (`DB_PASSWORD`, `APP_KEY`, `REDIS_PASSWORD`, `AGENT_LICENSE_KEY`, all `MESSENGER_*` secrets) inherited by child agent processes. Filter at line 75 only removes `ANTHROPIC_*` vars. `config/agent.php` `forbidden_env_keys` setting exists but is NOT applied here. `SessionProcessManager::startWrapper()` uses a clean explicit allowlist -- the inconsistency makes this gap more glaring. | P0 | Security |
| 4 | `routes/api.php:62-64` | `/user` endpoint returns `$request->user()` directly -- full model serialization without `UserResource`. Laravel's `$hidden` covers `password` and `remember_token`, but all other columns are exposed. | P0 | Security |

**Adversarial calibration applied:**

Downgraded from P0 to P1:
- `AgentUserCommand` `--password` CLI option visible in `ps aux` -- interactive install-time command with `$this->secret()` fallback; low real-world impact
- `.env` non-atomic writes (AgentInstallCommand) -- interactive install wizard, concurrent access extremely unlikely
- 5 read-only admin controllers (AuditLog, SecurityAudit, LogTail, Diagnostics, DebugPanel) -- information disclosure, not configuration tampering

Confirmed false positives (removed from all priority lists):
- CommandPolicy template injection -- 5-6 protection layers verified
- IncidentLifecycleService TOCTOU -- correct optimistic+pessimistic pattern with `lockForUpdate()` + `QueryException` catch
- LicenseService nested array NPE -- PHP `??` handles nested null access correctly
- ProcessRuntimeTurnJob uninitialized `$timeout` -- always set in constructor via `config()`
- BillingUsageService empty string vs null -- config default provides fallback
- ProcessInboundMessage idempotency race -- optimistic check + QueryException catch is standard pattern
- RunEventWriter missing FeatureFlagManager import -- both classes in same namespace (`App\Support\Agent`); resolves correctly
- Module-level shared state in Vue composables (M-23) -- all reactive state is inside the composable function, not at module level
- ChatIntentParser "Command Injection" (C-01) -- array-form execution prevents shell injection; "prompt injection" is inherent to chat features, not Critical security
- **config/agent.php closure breaks config:cache** -- FALSE POSITIVE: the `$parseEnvCsvList` closure is a local variable used during array construction, NOT stored in the returned config array. `config:cache` serializes the returned array only.
- **RecalculateTrustScoresJob "identical scores per runner type"** -- INTENTIONAL BEHAVIOR: calculator computes aggregate baseline trust per runner_type, not per-profile. Downgraded to P2 performance.
- ChatActionExecutor "needs registry pattern" -- already implements it via `registerHandler()` method

---

## SOLID Violations

### Single Responsibility

The codebase's primary structural weakness. After adversarial calibration, the following classes genuinely warrant decomposition:

| Class | LOC | Responsibilities | Urgency |
|-------|-----|-----------------|---------|
| `InterrogationSessionController` | 4,124 | 9+ domains: discovery, questions, plans, builds, summaries, exports, state transitions, cleanup, events | P2 |
| `ExecuteInterrogationRoundJob` | 1,427 | Round execution, LLM interaction, duplicate detection, state transitions, progress, quality gates, retry, timeout | P2 |
| `AgentInstallCommand` | 1,171 | License, preflight, migrations, user creation, connector config, credential validation, webhook setup, health checks | P2 |
| `RunEventWriter` | 1,000+ | Event persistence, redaction (15+ regex), pattern detection, binary detection, escalation broadcasting, rate limit extraction | P2 (partial extract only) |
| `ExecuteAgentRunJob` | 987 | Process lifecycle, compliance, env prep, STAR preamble, state transitions, cost, billing, memory dispatch | P1 (add observability) |
| `SessionProcessManager` | 728 | Process lifecycle, session state, message I/O, stream parsing, progress tracking, turn yielding | P1 (static state risk) |
| `OfficeStateController` | 467 | Single `__invoke()` aggregating 9 domains with 11 private methods | P2 |

**Adversarial note:** 350-500 line classes with clear internal structure are acceptable in Laravel. RunEventWriter has high internal cohesion with shared mutable state -- extract only `RedactionService` and `EscalationBroadcaster` when modifying. Do NOT decompose MemoryFormationPipeline (orchestration is the value) or OfficeStateController (purely read-only data aggregation with zero business logic).

### Open/Closed

24 OCP violations. Pervasive hardcoded `match`/`switch` statements:
- `ChatResponseFormatter` -- 142-line match statement
- `MemoryAdapterFactory` -- three near-identical `makeXProvider()` methods with hardcoded provider lists
- `ApprovalGate` -- 35+ tools hardcoded in `MUTATION_TOOLS` and `EXTERNAL_TOOLS` constants
- `CommandRouter` -- 17 hardcoded command-to-handler mappings
- `AgentInstallCommand:720-909` -- hardcoded match for provider validation
- 6 models with string constants instead of PHP 8.1 enums

**Adversarial note:** `ChatActionExecutor` already implements the registry pattern. `CommandRouter`'s lazy `app()` resolution is acceptable for 17 handlers. Match statements with <5 cases are idiomatic PHP 8.1, not OCP violations.

### Liskov Substitution

5 minor findings. Generally strong contract compliance (score: 85/100). Tool adapters properly implement `ToolAdapterInterface` and are substitutable. Minor concern: `AbstractConnectorAdapter` base methods return silent failures for unimplemented optional methods.

### Interface Segregation

7 findings. `ConnectorAdapterInterface` (10 methods) forces unused implementations across 5+ adapters. Creating interfaces for all 60+ single-implementation services is YAGNI -- add interfaces only when a second implementation or explicit contract boundary is needed.

### Dependency Inversion

25 DIP violations. Key concerns:
- 35 `app()` calls across 12 job files (adversarial note: standard Laravel pattern for serialized jobs)
- `ChatIntentParser` -- uses `Process`, `Storage`, `Log` facades directly; 8+ direct `config()` calls
- `ConfigurationController` -- direct `.env` file I/O in controller
- `ExecuteAgentRunJob` -- mixed patterns: constructor DI, `app()` resolution, and `new` instantiation
- Only 4 interfaces in `app/Contracts/`

---

## Laravel Anti-Patterns

### Controller Issues

- **Missing authorization** on ConfigurationController write endpoint (P0) and 5 read-only admin controllers (P1)
- **God controllers**: `InterrogationSessionController` (4,124 LOC, 37 methods), `RepoAnalysisSessionController` (1,118 LOC)
- **Missing API Resources** on 4 controllers returning raw model data
- **Inline validation** in 9 controllers where FormRequest classes should be used

### Eloquent Misuse

- **N+1 risks** in `RepoAnalysisSessionController:884-892`, `OfficeStateController:143-154` (O(n^2) firstWhere), `DelegationCoordinator:233-239`, `RecalculateTrustScoresJob:26-36`
- **Business logic in models** -- `ConnectorAccount` (15+ policy methods), `MemoryEmbedding` (decay calculations), `InterrogationEvent` (75 lines UTF-8 normalization)
- **Shadowed scopes** -- 3 models define `scopeLatest()` that shadows Laravel's built-in `latest()` method

### Missing Framework Features

- **`env()` outside config files** in 5+ locations -- breaks `config:cache`
- **Missing `failed()` methods** on ~50% of jobs
- **Missing explicit `$tries`/`$timeout`** on 11 jobs
- **Missing `ShouldBeUnique`** on concurrency-sensitive jobs
- **HorizonServiceProvider** `viewHorizon` gate checks empty email array, always returns false
- **72+ unnamed API routes**
- **Missing exception renderers** for 403, 405, 429

### Convention Violations

- Inconsistent mass assignment: 13 models use `$fillable`, 56 use `$guarded = []`
- Mixed backoff patterns across jobs
- Inconsistent feature flag method names: `enabled()` vs `isEnabled()`

---

## Design Pattern Issues

### Anti-Patterns Detected

1. **God Classes** (8 files >500 LOC): ~6,654 lines concentrated in 8 files
2. **Static in-memory state**: `SessionProcessManager::$activeProcesses` -- fails silently in multi-worker Horizon
3. **Transient object mutation**: `ExecuteAgentRunJob:154-174` mutates in-memory model without persisting
4. **Non-idempotent keys**: `ProcessChatIntent.php:368` derives idempotency keys from `Str::uuid()` on every execution
5. **Silent failures**: `RunEventWriter:83-85` and `ApprovalGate:112-115` swallow all Throwables without logging
6. **Double feature-flag checking**: config + FeatureFlagManager checked redundantly in multiple files
7. **DelegationRecoveryHandler SQL bug**: `distinct('col')->count('col')` does NOT produce `COUNT(DISTINCT col)` -- re-delegation limit check allows more retries than intended (NEW from adversarial review)

### DRY Violations

**Estimated total duplicate code: ~1,100+ lines**

| Pattern | Files | Lines |
|---------|-------|-------|
| State transition services (5x near-identical) | Support/Agent, Support/Delegation, Support/Interrogation, Support/RepoAnalysis | ~250 |
| Credential validation (4x) | AgentInstallCommand (Slack/Discord/Telegram/WhatsApp) | ~200 |
| Turn handling near-duplicate | SessionProcessManager read vs resume | ~150 |
| Fragment/stream extraction (3x) | CliRuntimeExecutor, SessionProcessManager, MessengerRuntimeOrchestrator | ~150 |
| Pattern detection blocks (4x) | RunEventWriter:132-154 | ~40 |
| Metadata array merge pattern (37x) | 15 files | N/A (boilerplate) |

### Abstraction Problems

- **Missing enums**: 6 models with 45+ string constants should use PHP 8.1 enums
- **Hardcoded magic numbers**: 30+ across services
- **Missing Repository pattern**: services directly use Eloquent models throughout

---

## Bugs & Security

### Potential Bugs

| # | File | Bug | Priority |
|---|------|-----|----------|
| 1 | `LicenseService.php` | Cache coherency: `isValid()` returns false when cache expires instead of calling `validate()`. `fallbackOrInvalid()` always reads empty cache (dead code path) | P1 |
| 2 | `AgentUpdateCommand.php:33-43` | License verification task always returns `true`; migration exit code discarded -- command always reports SUCCESS | P1 |
| 3 | `SessionProcessManager.php:245-367` | Unbounded `$fragments[]` growth in `readTurnResponse()` -- chatty runner over 1800s timeout can exhaust worker memory | P1 |
| 4 | `SessionProcessManager.php:340-356,698-714` | Resource leak on unexpected process exit -- pipes not closed, `proc_close()` not called, stale entry in `$activeProcesses` | P1 |
| 5 | `InstanceFingerprint.php:24-39` | Race condition: check-then-create without unique constraint allows duplicate salts | P1 |
| 6 | `AttachmentHandler.php:155-189` | Null safety gaps on model relationships | P1 |
| 7 | `ProcessChatIntent.php:368,493,530` | Non-idempotent keys: derived from fresh `Str::uuid()` -- retries produce different keys | P1 |
| 8 | `useOfficeRealtime.js:62-65` | In-place mutation breaks `watch()` old/new comparison -- ALL visual transitions (~30 lines) are dead code | P1 |
| 9 | `DelegationRecoveryHandler.php:95-97` | `distinct('col')->count('col')` produces `SELECT DISTINCT COUNT(col)` not `COUNT(DISTINCT col)` -- re-delegation limit check bypassed | P1 |
| 10 | `RitualRunCompletionListener.php:16-35` | No transaction wrapping or pessimistic locking -- concurrent listeners race on state transitions | P1 |
| 11 | `DeliverWebhookJob.php:23` | Scalar `$backoff = 5` instead of exponential array | P2 |
| 12 | `AiCriticCompletedJob.php:139-141` | `file_get_contents()` returns `false`; `trim(false)` deprecated in PHP 8.1+ | P2 |
| 13 | `OrgDispatchDueRitualsJob.php:20-31` | No duplicate dispatch guard -- overlapping executions dispatch duplicate rituals | P2 |
| 14 | `DelegationAttemptCompletedJob.php:61-67` | Not idempotent: overwrites status without terminal state guard | P2 |
| 15 | `AgentOffice.vue:334` | `agentThoughts` keyed by `run_id` instead of `agent.id` | P2 |

### Security Concerns

| # | File | Concern | Priority |
|---|------|---------|----------|
| 1 | `ConfigurationController.php` | Write access to .env values without role gate | P0 |
| 2 | `OfficeStateController.php` | Cross-user data exposure (escalations, connectors, incidents) | P0 |
| 3 | `CliRuntimeExecutor.php:72-80` | Env var inheritance leaks secrets to child processes | P0 |
| 4 | `routes/api.php:62-64` | `/user` returns full model without resource transformation | P0 |
| 5 | `AgentUserCommand.php:17` | `--password` CLI option visible in `ps aux` | P1 |
| 6 | 5 read-only admin controllers | Information disclosure without authorization gates | P1 |
| 7 | `LicenseService.php:49-66` | Bypass domains overly permissive -- `.test`, `.local`, `.localhost` match without dot boundary | P1 |
| 8 | `ChatActionPolicyValidator.php:193-203` | Regex-based dangerous command matching trivially bypassed | P1 |
| 9 | `routes/api.php:73` | N8n webhook endpoint has no authentication or rate limiting | P1 |
| 10 | `RunEventWriter.php:400-418` | Bearer token regex doesn't match `/` in JWT tokens | P2 |

### Error Handling Gaps

- `ExecuteAgentRunJob` -- `$tries=1`, no `failed()` handler -- failures completely invisible
- `RitualCouncilDeliberationListener:54-58` -- catches `Throwable`, silently ignores with no logging
- `DelegationCoordinator.broadcastTaskCompleted()` -- empty catch on `Throwable`
- `ProcessRuntimeTurnJob` -- `$tries=1`, no backoff, transient errors permanently fail
- `RuntimeLlmClient:61-75` -- no HTTP retry on Anthropic API (429, 5xx)
- `MemoryWorkingBufferJob` -- `$tries=0` with silent catch, failures invisible
- ~50% of jobs lack `failed()` handlers

---

## Code Quality Metrics

### Dead Code

- `ReplayParityService.php` -- stub always returns success, never validates anything
- `LicenseService.fallbackOrInvalid()` -- always reads empty cache, effectively dead code path
- `AgentOffice.vue:250-281` -- ~30 lines of visual transition effects that never execute due to reactivity bug

### Test Coverage Gaps

| Category | Total | Tested | Coverage |
|----------|-------|--------|----------|
| Services | 68 | 51 | 75% |
| Controllers | 70 | 37 | 53% |
| Commands | 32 | 12 | 38% |
| Jobs | 39 | 11 | 28% |
| **Overall** | **720 files** | **375 tests** | **~52%** |

**Top 5 untested critical areas:**
1. `ExecuteAgentRunJob` (987 lines, 0 tests) -- core agent execution
2. `MessengerRuntimeOrchestrator` (300+ lines, 0 tests) -- turn orchestration
3. `CliRuntimeExecutor` (200+ lines, 0 tests) -- subprocess management
4. RepoAnalysis Jobs (7 jobs, 0 tests) -- multi-phase analysis
5. Org Ritual/Escalation Jobs (3 jobs, 0 tests) -- scheduling, execution, timeout

### Performance Concerns

| # | Location | Issue | Priority |
|---|----------|-------|----------|
| 1 | `OfficeStateController` | 3x `Schema::hasTable()` per request + 4x repeated `whereHas` subqueries | P1 |
| 2 | `SessionProcessManager` | Unbounded `$fragments` array growth -- memory exhaustion risk | P1 |
| 3 | `RecalculateTrustScoresJob:26-36` | Individual `$profile->update()` per chunk -- use `upsert()` | P2 |
| 4 | `VerifyWebhookSignature.php:127-144` | O(n) HMAC verification fallback across all active accounts | P2 |
| 5 | `ExecuteAgentRunJob.php:695-730` | Loads all run events into memory without pagination | P2 |
| 6 | `useOfficeZones.js:99-120` | Full scene traversal every animation frame | P2 |
| 7 | `useOfficeScene.js` | GPU memory leak: Three.js objects not disposed in cleanup | P2 |

### Naming Conventions

- Mixed service suffixes (Manager, Executor, Handler, Service, Router, Registry) without documented convention
- `enabled()` vs `isEnabled()` alias on `FeatureFlagManager`
- Inconsistent route naming patterns

---

## Adversarial Notes

_Challenges to findings, false positives identified, and dissenting opinions._

**13 findings dismissed as false positives:**
1. LicenseService nested array NPE -- PHP null coalescing handles this
2. IncidentLifecycleService TOCTOU -- correct optimistic+pessimistic with `lockForUpdate()`
3. CommandPolicy template injection -- 5-6 layers of protection verified
4. ProcessRuntimeTurnJob uninitialized `$timeout` -- always set in constructor
5. BillingUsageService empty string vs null -- config default provides fallback
6. ProcessInboundMessage idempotency race -- optimistic check + QueryException catch
7. CommandRouter parseCommand() security -- preg_split with safe patterns
8. ChatActionExecutor "needs registry pattern" -- already implements it
9. Module-level shared state in Vue composables -- reviewer error
10. ChatIntentParser "Command Injection" -- array-form execution
11. RunEventWriter missing FeatureFlagManager import -- same namespace
12. **config/agent.php closure breaks config:cache** -- local variable, not in returned array
13. **RecalculateTrustScoresJob "identical scores" bug** -- intentional aggregate behavior per runner_type

**12 over-engineering suggestions rejected:**
1. Split `RunEventWriter` into 5 services (high cohesion, shared mutable state)
2. Extract `OfficeStateController` to `OfficeStateAggregator` (moves code, zero behavioral benefit)
3. `DiagnosticsService` -> `HealthCheck` interface (11 files for 278 lines)
4. Decompose `MemoryFormationPipeline` into 4 services (orchestration is the value)
5. Split `FeatureFlagManager` into domain-specific flag classes
6. Create interfaces for all 60+ single-implementation services (YAGNI)
7. Replace `CommandRouter`'s lazy `app()` resolution with 17 constructor injections
8. Replace `app(FeatureFlagManager::class)` in serialized jobs (standard Laravel pattern)
9. Introduce "registry pattern" for `ChatActionExecutor` (already implements it)
10. Split install command into 3-4 separate Artisan commands (bad UX for install wizard)
11. Create `TokenBudgetStrategy` interface (single algorithm, no variants)
12. Create `PreambleTemplateRepository` (single template)

**Key adversarial observations:**
- Reviews are heavily biased toward structural/SRP concerns (36% of findings) while underweighting actual runtime bugs
- Only 4 of the 24 originally "Critical" findings represent code that will break in production, expose data, or lose money
- The SOLID analysis reports 103 findings but ~78 are style preferences or patterns that would add complexity without preventing bugs
- Cross-report duplication is extreme: 8 findings each appeared in 4-6 separate reviews

---

## Recommendations

### Immediate (P0) -- Fix This Week

| # | Action | Effort |
|---|--------|--------|
| 1 | Add `can:manage-system` gate to `ConfigurationController` write endpoint | S |
| 2 | Add user scoping to `OfficeStateController` -- `buildEscalationsState()`, `buildMessengerState()` | S |
| 3 | Switch `CliRuntimeExecutor` to explicit env var allowlist; apply `forbidden_env_keys` filtering | S |
| 4 | Add `UserResource` transformation to `/user` endpoint | S |

### Short-term (P1) -- Next 2 Sprints

| # | Action | Effort |
|---|--------|--------|
| 5 | Remove `--password` CLI option from `AgentUserCommand`; always use `$this->secret()` | S |
| 6 | Fix `LicenseService` cache coherency: `isValid()` should call `validate()` when cache empty; fix dead `fallbackOrInvalid()` | S |
| 7 | Fix `AgentUpdateCommand`: return `$status->valid` from task closure; check migration exit code | S |
| 8 | Fix `SessionProcessManager` unbounded `$fragments` memory growth -- add cap or stream to disk | S |
| 9 | Add read authorization gates to 5 admin controllers (AuditLog, SecurityAudit, LogTail, Diagnostics, DebugPanel) | S |
| 10 | Fix `InstanceFingerprint` race condition: use `firstOrCreate()` with unique constraint | S |
| 11 | Add `failed()` handlers to `ExecuteAgentRunJob`, `ProcessRuntimeTurnJob`, `ProcessChatIntent` | S |
| 12 | Fix frontend reactivity: replace in-place mutation with new object creation in `useOfficeRealtime.js` | S |
| 13 | Fix `ProcessChatIntent` idempotency keys: derive from deterministic inputs (session_id + message_id + action), not fresh UUIDs | S |
| 14 | Fix `DelegationRecoveryHandler` `distinct()/count()` SQL: use `->distinct()->count('delegatee_profile_id')` or `DB::raw('COUNT(DISTINCT ...)')` | S |
| 15 | Add pessimistic locking to `RitualRunCompletionListener` state transitions | S |
| 16 | Secure N8n webhook endpoint with auth/signature verification | S |
| 17 | Fix `HorizonServiceProvider` `viewHorizon` gate (always returns false) | S |
| 18 | Add `ShouldBeUnique` to `RecalculateTrustScoresJob` and `OrgDispatchDueRitualsJob` | S |
| 19 | Add retry/backoff to `ProcessRuntimeTurnJob` (`$tries=3`, `backoff=[10,30,60]`) | S |
| 20 | Add HTTP retry to `RuntimeLlmClient` for transient/5xx errors | S |
| 21 | Log exceptions in `RitualCouncilDeliberationListener` and `DelegationCoordinator.broadcastTaskCompleted()` | S |
| 22 | Cache `Schema::hasTable()` results in `OfficeStateController` | S |
| 23 | Fix null safety gaps in `AttachmentHandler` | S |
| 24 | Replace `env()` calls outside config files with `config()` (5+ locations) | M |

### Long-term (P2) -- Fix When Touched

| # | Action | Effort |
|---|--------|--------|
| 25 | Fix `AttemptSpawner` template string replacement: use regex with word boundaries | S |
| 26 | Fix `SessionProcessManager` resource leak on unexpected process exit | S |
| 27 | Add duplicate dispatch guard to `OrgDispatchDueRitualsJob` | S |
| 28 | Add status guard to `DelegationAttemptCompletedJob` before status overwrite | S |
| 29 | Fix `AiCriticCompletedJob` `file_get_contents` false handling and unsafe verdict type cast | S |
| 30 | Fix `RunEventWriter` Bearer token regex to cover JWT characters (`/`, `+`, `=`) | S |
| 31 | Fix `DeliverWebhookJob` scalar backoff to array `[30, 60, 120]` | S |
| 32 | Replace magic string task names in `RitualCouncilDeliberationListener` with constants/enum | S |
| 33 | Add logging for invalid cron expressions in `OrgDispatchDueRitualsJob` | S |
| 34 | Fix `agentThoughts` keyed by `run_id` in `AgentOffice.vue:334` -- use agent ID | S |
| 35 | Dispose Three.js geometries/materials in `useOfficeScene.js` cleanup | S |
| 36 | Batch `RecalculateTrustScoresJob` updates by runner_type (performance) | S |
| 37 | Validate `LicenseService` API response structure before caching | S |
| 38 | Add null guard in `DelegationRecoveryHandler.findAlternativeProfile()` | S |
| 39 | Extract `ProviderCredentialValidator` from `AgentInstallCommand` (4x DRY, ~200 lines) | M |
| 40 | Extract `RedactionService` from `RunEventWriter` (only when modifying redaction logic) | M |
| 41 | Extract model status constants to PHP 8.1 enums (6 models, 45+ constants) | M |
| 42 | Add explicit `$tries`/`$timeout` to 11 jobs missing them | S |

**Effort Key:** S = < 1 hour, M = 1-4 hours, L = 4+ hours

---

## Positive Patterns to Preserve

The codebase demonstrates strong foundations that should be maintained and extended:

- **Domain organization** -- clear boundaries between Agent, Interrogation, Delegation, Org, Runtime, Memory, Messenger, Documentation, RepoAnalysis
- **32 FormRequest classes** -- validation is well-separated for the majority of endpoints
- **14 Policies** -- authorization is established for core resources
- **DTO/Result pattern** -- `LicenseStatus`, `CommandResult`, `ValidationResult`, `EnforcementResult`, `GateEvaluationResult`, `ToolResult`, `ChatActionResult`, `MemoryFormationResult` with readonly classes and static factories
- **Strategy pattern** -- synthesis strategies, tool adapters (8 implementations), chat action handlers, slash command handlers all implement clean contracts
- **Adapter pattern** -- `AbstractConnectorAdapter` with rate limiting, circuit breaker, and backoff across 4+ providers
- **Circuit Breaker** -- proper three-state machine in Messenger with cache-backed state
- **ErrorEnvelope** -- consistent error responses across all controllers (185 usages, 29 files)
- **Credential encryption** -- `CredentialVault` uses `Crypt::encryptString()` with `$hidden` attribute
- **SQL injection prevention** -- all examined `whereRaw()`/`selectRaw()` use parameter bindings
- **Process safety** -- CLI executor constructs commands as arrays; `SessionProcessManager::startWrapper()` uses clean explicit env allowlist
- **CommandPolicy protections** -- 5-6 layers of defense (verified by 5 adversarial reviews)
- **Atomic state transitions** in `DelegationCoordinator` with race condition guards
- **Sanctum + License middleware** -- proper layered authentication
- **Memory system layering** -- Core -> Working -> Long-term with graceful degradation via `NullEmbeddingProvider`
- **Focused utility services** -- `CanonicalCostCalculator`, `GateEvaluator`, `FailureTaxonomyMapper`, `ActiveHoursEvaluator`
- **Modern PHP** -- constructor property promotion, enums, match expressions, named arguments, readonly classes
- **SlashCommandRegistrar** -- well-structured with version tracking and proper Discord API integration

---

## Review Metadata

- **Reviewers:** Engineering Lead (Synthesis), SOLID Analyst (Task 88), Laravel Specialist (Task 86), Code Quality Inspector (Task 82), Design Pattern Expert (Task 87), Adversarial Reviewer (Tasks 83/89)
- **Council Decision:** Synthesized with adversarial calibration
- **Synthesis Mode:** weighted -- adversarial adjustments applied to all raw findings
- **Delegation Graph:** SOLID Analysis
- **Input findings:** 500+ raw across specialist review passes, consolidated to 42 unique actionable issues after deduplication, false-positive removal, and adversarial calibration
- **False positives removed:** 13 (10 from prior passes + 3 from adversarial review Task 89)
- **Over-engineering rejected:** 12 suggestions
- **New findings from adversarial:** 5 (DelegationRecoveryHandler SQL, cache poisoning, RitualRunCompletionListener race, null safety, telemetry dedup)
- **SOLID scores:** SRP 52/100, OCP 63/100, LSP 85/100, ISP 75/100, DIP 48/100
- **Review quality (adversarial assessment):** Prior Adversarial A-, Gap Analysis A-, Code Quality B-, Design Patterns B, Laravel BP B, SOLID Analysis C+
