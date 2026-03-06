# SOLID Principles & Laravel Best Practices Review

**Project:** Agent Scheduler (Laravel 12 / PHP 8.3)
**Date:** 2026-03-06
**Graph:** SOLID Analysis | Task ID: 98 | Attempt: 1
**Scope:** Full codebase — controllers, routes, middleware, jobs, commands, models, services, support classes, config
**Excludes:** vendor, node_modules, storage, .git

---

## STAR Pre-Execution

### SITUATION
The Agent Scheduler codebase contains 104,224 lines of PHP across the `app/` directory, with 70 Eloquent models, 54 controllers, 39 queued jobs, 32 FormRequest classes, and 36+ config files. Previous reviews (Tasks 91–94) identified 113 SOLID violations and 68 Laravel anti-patterns. This review (Task 98) re-evaluates the full codebase with fresh analysis, focusing on SOLID principles, Laravel best practices, DRY violations, design patterns, code quality, bugs, and security.

### TASK
Produce a structured SOLID analysis with severity ratings, file/line references, and actionable remediation for all violations across the codebase.

### ACTION
1. Enumerated all PHP files in target directories, measured file sizes
2. Analyzed controllers for fat controller patterns, missing FormRequests, missing authorization
3. Reviewed models for mass assignment, missing strict mode, missing scopes
4. Examined jobs for missing `failed()` methods, missing uniqueness constraints
5. Checked routes for unused patterns, missing resource routes
6. Reviewed config for hardcoded values, `env()` usage outside config
7. Assessed security posture across controllers and services

### RESULT
Report written to `docs/review/solid-analysis-2026-03-06-t98.md`. 148 findings identified across all categories.

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 13 |
| High | 44 |
| Medium | 58 |
| Low | 33 |
| **Total** | **148** |

| Category | Count |
|----------|-------|
| SOLID Violations | 68 |
| Laravel Best Practices | 36 |
| Security | 18 |
| DRY Violations | 10 |
| Code Quality | 12 |
| Bugs | 4 |

---

## Critical Findings

| # | File | Issue | Severity | Category |
|---|------|-------|----------|----------|
| 1 | `app/Http/Controllers/Api/V1/InterrogationSessionController.php` | 4,124-line god controller with ~90 methods | Critical | SRP |
| 2 | `app/Http/Controllers/Api/V1/ConfigurationController.php:114-138` | Direct `.env` file I/O in controller | Critical | Security / SRP |
| 3 | `app/Jobs/ExecuteAgentRunJob.php` | 987-line job with 7+ responsibilities | Critical | SRP |
| 4 | `app/Jobs/ExecuteInterrogationRoundJob.php` | 1,427 lines — largest job | Critical | SRP |
| 5 | `app/Support/RepoAnalysis/ReportComposer.php` | 1,196 lines — mixed composition/rendering | Critical | SRP |
| 6 | `app/Console/Commands/AgentInstallCommand.php` | 1,171 lines — 10+ responsibilities, direct `env()`/`putenv()` | Critical | SRP / Security |
| 7 | `app/Jobs/ExecuteInterrogationBuildJob.php` | 1,140 lines | Critical | SRP |
| 8 | `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php` | 1,118-line controller | Critical | SRP |
| 9 | 48 controllers missing `$this->authorize()` | No policy-based authorization on most controllers | Critical | Security |
| 10 | 50 of 70 models use `$guarded = []` | Fully unguarded mass assignment across most models | Critical | Security |
| 11 | No `Model::shouldBeStrict()` call anywhere | Missing Laravel strict mode (N+1 detection, preventing silent attribute discards) | Critical | Laravel Best Practice |
| 12 | `app/Providers/MemoryServiceProvider.php` | `MemoryFormationPipeline` not registered but resolved via `$app->make()` | Critical | Bug |
| 13 | `app/Providers/HorizonServiceProvider.php:30-34` | Empty Horizon gate blocks all users; accepts null user | Critical | Security |

---

## 1. SOLID Violations

### 1.1 Single Responsibility Principle (SRP) — 48 violations

#### Critical SRP Violations (Files > 800 lines)

**F1. InterrogationSessionController** — `app/Http/Controllers/Api/V1/InterrogationSessionController.php`
- **Lines:** 1–4,124 (~90 methods: 44 public, 46 private)
- **Responsibilities:** Session CRUD, state machine transitions, AI round orchestration, summary/plan generation, build task management, compliance extraction, data export, annotation management, tech stack management, conversation reconstruction
- **Fix:** Extract into 6+ focused controllers/services:
  - `InterrogationLifecycleController` (CRUD, pause/resume)
  - `InterrogationWorkflowService` (state transitions)
  - `InterrogationAiOrchestrationService` (round/summary/plan generation)
  - `InterrogationBuildTaskController` (build task CRUD)
  - `InterrogationExportService` (summary/plan export)
  - `InterrogationAnnotationController` (annotations)

**F2. ExecuteInterrogationRoundJob** — `app/Jobs/ExecuteInterrogationRoundJob.php`
- **Lines:** 1–1,427
- **Fix:** Extract AI interaction, output parsing, event writing, and state transition into separate services.

**F3. ReportComposer** — `app/Support/RepoAnalysis/ReportComposer.php`
- **Lines:** 1–1,196
- **Fix:** Split into `ReportDataCollector` and `ReportRenderer`.

**F4. AgentInstallCommand** — `app/Console/Commands/AgentInstallCommand.php`
- **Lines:** 1–1,171
- **Fix:** Extract into `InstallStepRunner` with discrete step classes (DB setup, Redis setup, env configuration, migration, seeding).

**F5. ExecuteInterrogationBuildJob** — `app/Jobs/ExecuteInterrogationBuildJob.php`
- **Lines:** 1–1,140
- **Fix:** Extract build execution, output parsing, and state management.

**F6. ClaudeAdapter** — `app/Support/Interrogation/Adapters/ClaudeAdapter.php`
- **Lines:** 1–1,126
- **Fix:** Extract response parsing, tool call handling, and streaming logic.

**F7. CodexAdapter** — `app/Support/Interrogation/Adapters/CodexAdapter.php`
- **Lines:** 1–1,183
- **Fix:** Same pattern as ClaudeAdapter — extract shared adapter infrastructure.

**F8. RepoAnalysisSessionController** — `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
- **Lines:** 1–1,118
- **Fix:** Extract into focused controllers for lifecycle, execution, and reporting.

**F9. ExecuteInterrogationPlanJob** — `app/Jobs/ExecuteInterrogationPlanJob.php`
- **Lines:** 1–1,078

**F10. DiscordAdapter** — `app/Support/Messenger/Adapters/DiscordAdapter.php`
- **Lines:** 1–1,039

**F11. RunEventWriter** — `app/Support/Agent/RunEventWriter.php`
- **Lines:** 1–1,000 (10 regex constants, 7+ detection responsibilities)
- **Fix:** Extract regex-based detectors into `OutputDetector` classes using a chain pattern.

**F12. ExecuteAgentRunJob** — `app/Jobs/ExecuteAgentRunJob.php`
- **Lines:** 1–987
- **Responsibilities:** Runtime validation, STAR preamble injection, memory context injection, env policy enforcement, process lifecycle management, output monitoring, heartbeat emission, timeout handling, signal management, cost recording, billing usage, memory formation dispatch, targeted retry, usage limit policy, path failure policy
- **Fix:** Extract into:
  - `PreRunPipeline` (validation, preamble, memory context, env policy)
  - `ProcessMonitor` (running loop, heartbeat, signals)
  - `PostRunPipeline` (cost recording, billing, memory, retry, policy)

**F13. InterrogationTaskProviderSyncService** — `app/Support/TaskProviders/InterrogationTaskProviderSyncService.php`
- **Lines:** 1–962

**F14. DiscordGatewayWorker** — `app/Messenger/Gateway/Workers/DiscordGatewayWorker.php`
- **Lines:** 1–867

**F15. ExecuteRepoAnalysisTaskJob** — `app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php`
- **Lines:** 1–810

**F16. DocsGenerationService** — `app/Support/Documentation/DocsGenerationService.php`
- **Lines:** 1–805

**F17. LinearTaskManagementProvider** — `app/Support/TaskProviders/Drivers/LinearTaskManagementProvider.php`
- **Lines:** 1–767

**F18. SessionProcessManager** — `app/Services/Runtime/SessionProcessManager.php`
- **Lines:** 1–727 (duplicated read loops)

#### High SRP Violations (Files 500–800 lines)

| File | Lines |
|------|-------|
| `app/Http/Controllers/Api/V1/MessengerConnectorController.php` | 697 |
| `app/Http/Controllers/Api/V1/AgentJobController.php` | 688 |
| `app/Jobs/Messenger/ProcessChatIntent.php` | 643 |
| `app/Support/Memory/MemoryFormationPipeline.php` | 628 |
| `app/Support/RepoAnalysis/ExportService.php` | 624 |
| `app/Http/Controllers/Agent/OperatorPageController.php` | 624 |
| `app/Console/Commands/AgentBenchmarkSloCommand.php` | 613 |
| `app/Jobs/ExecuteInterrogationSummaryJob.php` | 592 |
| `app/Messenger/Gateway/Workers/SlackSocketWorker.php` | 589 |
| `app/Support/Documentation/CoverageAuditService.php` | 564 |
| `app/Http/Controllers/Api/V1/DelegationGraphController.php` | 562 |

### 1.2 Open/Closed Principle (OCP) — 8 violations

**F19.** `InterrogationSessionController.php:241-700` — Phase/status transitions hardcoded in nested if-else chains. New phases require controller modification.
- **Severity:** High
- **Fix:** Use a state machine pattern (e.g., `StateTransitionMap` with registered handlers).

**F20.** `ExecuteAgentRunJob.php:98-116` — Compliance gate handling is inline. New gate types require job modification.
- **Severity:** Medium
- **Fix:** Use a pipeline pattern for pre-run checks.

**F21.** `RunEventWriter.php` — 10 hardcoded regex constants for output detection. New detectors require modifying the class.
- **Severity:** High
- **Fix:** Implement `OutputDetectorInterface` with registered implementations.

**F22.** `ConfigurationController.php:98-112` — `configKeyToEnv()` uses a match statement. New config keys require controller changes.
- **Severity:** Medium
- **Fix:** Move mapping to config file or `ConfigSchemaService`.

**F23.** `AgentInstallCommand.php` — Monolithic install steps. New install requirements require modifying the command.
- **Severity:** High
- **Fix:** Use an `InstallStep` interface with registered steps.

**F24.** `MessengerConnectorController.php` — Inline validation per provider type rather than strategy-based validation.
- **Severity:** Medium

**F25.** Routes in `routes/api.php` — `InterrogationSessionController` has 35+ manually defined routes instead of using `Route::apiResource`.
- **Severity:** Low

**F26.** `CommandRouter` — 17 handlers with growing match/switch logic.
- **Severity:** High

### 1.3 Liskov Substitution Principle (LSP) — 2 violations

**F27.** `ClaudeAdapter` vs `CodexAdapter` — Both implement the interrogation adapter interface but handle tool calls and streaming differently, with inconsistent error semantics. Callers may need to check adapter type.
- **Severity:** Medium
- **Fix:** Normalize return types and error handling across adapters.

**F28.** `DiscordAdapter` vs other messenger adapters — Different attachment handling and message length limits affect substitutability.
- **Severity:** Low

### 1.4 Interface Segregation Principle (ISP) — 4 violations

**F29.** `TaskManagementProviderDriver` interface — All methods must be implemented even if a provider only supports a subset of operations.
- **Severity:** Medium
- **File:** `app/Support/TaskProviders/Contracts/TaskManagementProviderDriver.php`
- **Fix:** Split into `TaskCreatable`, `TaskSyncable`, `ProjectListable` interfaces.

**F30.** Several services accept full model instances when they only need 2-3 properties.
- **Severity:** Low
- **Fix:** Use DTOs for cross-boundary data passing.

**F31.** `ExecuteAgentRunJob::handle()` accepts 5 injected dependencies but most paths only use 2-3.
- **Severity:** Low

**F32.** Messenger adapter interfaces require both send and receive capabilities; read-only adapters (like monitoring) must stub send methods.
- **Severity:** Medium

### 1.5 Dependency Inversion Principle (DIP) — 14 violations

**F33.** `ExecuteAgentRunJob.php:87` — `new RunEventWriter($run)` — Direct instantiation makes testing difficult.
- **Severity:** High
- **Fix:** Inject via constructor or resolve from container.

**F34.** `ExecuteAgentRunJob.php:151` — `app(PreRunDatabaseBackup::class)` — Service locator pattern.
- **Severity:** Medium
- **Fix:** Add to `handle()` method injection.

**F35.** `ExecuteAgentRunJob.php:156-158` — `app(FeatureFlagManager::class)`, `app(MemoryContextBuilder::class)` — Repeated service locator calls.
- **Severity:** Medium

**F36.** `ExecuteAgentRunJob.php:178` — `app(StarPreambleGenerator::class)` — Another service locator.
- **Severity:** Medium

**F37.** `ExecuteAgentRunJob.php:467` — `app(FailureModeClassifier::class)` — Service locator in post-processing.
- **Severity:** Medium

**F38.** `ExecuteAgentRunJob.php:667` — `app(WorkflowBudgetEnforcer::class)` — Service locator for cost recording.
- **Severity:** Medium

**F39.** `ConfigurationController.php:114-138` — Direct file system access (`file_get_contents`, `file_put_contents`) without abstraction.
- **Severity:** High
- **Fix:** Use Laravel's `Filesystem` facade or inject `Illuminate\Filesystem\Filesystem`.

**F40.** Multiple jobs use `app()` helper for service resolution instead of constructor/method injection.
- **Severity:** Medium
- **Affected:** `ExecuteInterrogationRoundJob`, `ExecuteInterrogationBuildJob`, `ExecuteInterrogationSummaryJob`, `ProcessChatIntent`

**F41.** `SessionProcessManager.php:112-113` — Direct `getenv()` calls instead of config values.
- **Severity:** Medium
- **Fix:** Use `config('app.home_path')` etc.

**F42.** `AgentInstallCommand.php:1124-1129` — Direct `putenv()` calls.
- **Severity:** High
- **Fix:** Use Laravel config repository.

**F43.** `InterrogationBuildCommandGuard.php:163` — Direct `getenv()` call.
- **Severity:** Low

**F44.** `DatabaseDestructionGuard.php` — Direct `env()` call in application code.
- **Severity:** Medium
- **Fix:** Move to config value.

**F45.** `AgentRestartCommand.php:123-124` — Nested `config(... env(...))` pattern.
- **Severity:** Low
- **Fix:** Remove `env()` wrapper; config already reads from env.

**F46.** `MemoryCapabilityResolver.php:200` — Raw `\DB::select()` to check pgvector extension.
- **Severity:** Low
- **Fix:** Extract to a `DatabaseCapabilityChecker` service.

**F46a.** `SessionProcessManager.php:29-30` — Uses `private static array $activeProcesses` for process state. Creates tight coupling to specific queue worker instance, breaks horizontal scalability.
- **Severity:** High
- **Fix:** Move process state to Redis-backed storage for cross-worker visibility.

**F46b.** `SessionProcessManager.php:79-150` — Race condition in `startWrapper()`. Check-then-act without atomic locking; two simultaneous calls could start duplicate wrappers.
- **Severity:** High
- **Fix:** Use `Cache::lock()` for distributed locking before wrapper creation.

**F46c.** `ToolGateway.php:12-307` — God class handling adapter registration, tool schema generation, execution routing, policy evaluation, audit logging, and approval gating.
- **Severity:** Medium
- **Fix:** Split into `ToolRegistry`, `ToolSchemaBuilder`, `ToolExecutionRouter`.

**F46d.** `ChatActionExecutor.php:31-100` — Service class with no interface and hardcoded handler registry.
- **Severity:** Medium
- **Fix:** Create `ChatActionExecutorInterface`. Move handler registry to DI.

**F46e.** `CoreMemoryManager.php:72+` — `set()` method has no maximum content size validation. Large payloads could be stored without limit.
- **Severity:** Medium (Security)
- **Fix:** Add content size validation with configurable max (e.g., 1MB).

**F46f.** `DelegationCoordinator.php:35-100+` — Complex implicit event choreography across 3 events with no explicit state machine. Hard to follow and reason about.
- **Severity:** Medium
- **Fix:** Implement explicit state machine or saga pattern.

---

## 2. Laravel Anti-Patterns

### 2.1 Controller Issues

**F47. Missing FormRequest classes** — 21 inline `$request->validate()` calls across 8 controllers, plus 5 `Validator::make()` calls in controllers.
- **Severity:** High
- **Affected controllers:**
  - `InterrogationSessionController.php` — 10 inline validations
  - `DelegationGraphController.php` — 3 inline validations
  - `CredentialsController.php` — 2 inline validations
  - `DelegateeProfileController.php` — 2 inline validations
  - `MessengerConnectorController.php` — 3 `Validator::make()` calls
  - `SystemDirectoryPickerController.php` — 1 `Validator::make()` call
  - `WorkflowGovernanceController.php` — 1 `Validator::make()` call
- **Fix:** Extract each validation set to a dedicated FormRequest class.

**F48. Missing authorization on most controllers** — Only 6 of 54 controllers use `$this->authorize()` (29 total calls across `AgentRunController`, `AgentJobController`, `OrgRitualRunController`, `OrgRitualController`, `OrgCouncilController`, `OrgAgentController`).
- **Severity:** Critical
- **Fix:** Create policies and add authorization checks to all resource controllers. Use `authorizeResource()` in constructors for CRUD controllers.

**F49. Fat controllers** — 18 files above 500 lines in the controllers directory (see SRP section).
- **Severity:** High
- **Fix:** Extract business logic into service classes. Controllers should be thin — validate, authorize, delegate, respond.

**F50. 71 instances of `$request->input()` / `$request->get()` in controllers** — Often used after inline validation, suggesting the validation should be in a FormRequest with typed access.
- **Severity:** Medium

**F51. No `Route::apiResource()` usage** — Despite having standard CRUD patterns for agents, graphs, profiles, rituals, sessions, etc., all routes are manually defined.
- **Severity:** Medium
- **Fix:** Use `Route::apiResource()` for standard CRUD and custom actions via `Route::post('/resource/{id}/action')`.

### 2.2 Eloquent / Model Issues

**F52. `$guarded = []` on 50 of 70 models** — Fully unguarded mass assignment. While `$guarded = []` is a valid pattern when combined with explicit `$fillable` on incoming request data, having it on sensitive models like `CredentialVault`, `AgentJob`, `ConnectorAccount`, and `MessengerIdentityLink` increases attack surface.
- **Severity:** Critical
- **Fix:** At minimum, use explicit `$fillable` on models that handle user input. For internal-only models, `$guarded = []` is acceptable but should be a conscious decision.

**F53. Missing `Model::shouldBeStrict()`** — No call to `Model::shouldBeStrict()` in `AppServiceProvider`. This means:
  - Lazy loading is silently allowed (N+1 query risk)
  - Accessing non-existent attributes returns `null` silently
  - Setting undefined attributes is silently ignored
- **Severity:** Critical
- **Fix:** Add to `AppServiceProvider::boot()`:
  ```php
  Model::shouldBeStrict(!app()->isProduction());
  ```

**F53a. Fat model with business logic** — `app/Models/ConnectorAccount.php:108-287` contains 10+ configuration getter/setter methods (getDmPolicy, setDmPolicy, getGroupPolicy, setGroupPolicy, etc.) each calling `update()` immediately, triggering individual DB writes.
- **Severity:** High
- **Fix:** Extract to `ConnectorAccountConfigManager` service. Use bulk updates for multiple config changes.

**F53b. 96-line attribute accessor** — `app/Models/InterrogationEvent.php:45-140` `payload()` attribute contains complex JSON normalization, UTF-8 sanitization, and error handling.
- **Severity:** High
- **Fix:** Extract to `PayloadNormalizer` service class.

**F53c. Domain logic in model** — `app/Models/MemoryEmbedding.php:121-136` `calculateDecayScore()` contains importance decay, recency calculations, and access frequency bonuses.
- **Severity:** Medium
- **Fix:** Create `EmbeddingImportanceScorer` service.

**F53d. Hardcoded config access in User model** — `app/Models/User.php:156-172` `getRoles()` directly calls `config('agent.roles.admin_user_ids')`. Violates DIP and makes testing difficult.
- **Severity:** Medium
- **Fix:** Inject a `RoleProvider` service.

**F53e. Config getter/setter duplication across models** — `ConnectorAccount.php` and `MemorySetting.php` both implement similar get/set patterns for nested config JSON.
- **Severity:** Medium
- **Fix:** Create a `ConfigurableAttribute` trait with reusable getConfig/setConfig methods.

**F53f. Over-complex policy logic** — `app/Policies/AgentJobRunPolicy.php:15-36` `view()` is 22 lines with nested conditionals and eager loading inside the policy.
- **Severity:** Medium
- **Fix:** Extract team resolution to a `TeamAccessResolver` service.

**F54. Status constants as strings instead of enums** — `AgentJobRun` defines 9 status constants as string constants. PHP 8.3 backed enums would provide type safety.
- **Severity:** Low
- **Affected:** `AgentJobRun`, `InterrogationSession`, `DelegationGraph`, `DelegationTask`, `RepoAnalysisSession`, `ChatAction`
- **Fix:** Migrate to `BackedEnum` classes.

**F55. Missing model scopes** — Several controllers build complex queries inline rather than using model scopes.
- **Severity:** Medium
- **Example:** `OfficeStateController.php:47-58` builds multiple filtered queries that should be model scopes.
- **Fix:** Add scopes like `scopeActive()`, `scopeRecent()`, `scopeForUser()`.

### 2.3 Job Issues

**F56. Only 6 of 39 jobs implement `failed()` method** — 33 jobs have no explicit failure handling.
- **Severity:** High
- **Affected:** All jobs except `MemoryFormationJob`, `ExecuteInterrogationBuildJob`, `ExecuteRepoAnalysisTaskJob`, `PersistDocsTelemetryEventJob`, `SendOutboundMessage`, `MemoryWorkingBufferJob`
- **Fix:** Add `failed()` methods to critical jobs, especially those that manage state transitions or external API calls.

**F57. No jobs use `ShouldBeUnique`** — Jobs like `ExecuteAgentRunJob`, `ProcessChatIntent`, `OrgExecuteRitualJob` could benefit from uniqueness to prevent double-processing.
- **Severity:** Medium
- **Fix:** Add `ShouldBeUnique` with appropriate `uniqueId()` to state-transition and execution jobs.

**F58. Missing `$maxExceptions` on long-running jobs** — `ExecuteAgentRunJob` has `$tries = 1` and `$timeout = 86500` (24h). A single unhandled exception in the monitoring loop could leave the process running without the job tracking it.
- **Severity:** High
- **Fix:** Add `$maxExceptions = 1` and ensure cleanup in `failed()`.

**F58a. Missing retry configuration on multiple jobs** — `OrgDispatchDueRitualsJob`, `OrgEscalationTimeoutJob`, `RecalculateTrustScoresJob`, `ReindexDocumentationSearchJob`, `CompactionJob` lack explicit `$tries`, `$timeout`, and `$backoff` properties, falling back to Laravel defaults.
- **Severity:** Medium
- **Fix:** Add explicit retry configuration to each job.

**F58b. ProcessInboundMessage — Missing transaction wrapping** — `app/Jobs/Messenger/ProcessInboundMessage.php:42-162` creates `ChatMessage` records then dispatches `ProcessChatIntent`, but without a transaction. If the dispatch fails, the message is orphaned.
- **Severity:** Medium
- **Fix:** Wrap in `DB::transaction()` and add `failed()` method.

### 2.4 Route Issues

**F59. Missing route model binding** — Routes use raw `{id}` parameters and manually `find()` models in controllers instead of using implicit route model binding.
- **Severity:** Medium
- **Affected:** Most controllers do `Model::find($id)` then check for null, returning 404 manually.
- **Fix:** Use `Route::model()` or type-hint models in controller methods.

**F60. Repeated middleware application** — `throttle:agent-mutations` is applied individually to ~40+ routes. Could be grouped.
- **Severity:** Low
- **Fix:** Use nested `Route::middleware(['throttle:agent-mutations'])->group(...)` for mutation routes.

### 2.5 Convention Issues

**F61. Mixed `Validator::make()` and `$request->validate()` patterns** — Some controllers use one, some the other. Inconsistent.
- **Severity:** Low
- **Fix:** Standardize on FormRequest classes.

---

## 3. Security Concerns

**F62. Direct `.env` file manipulation** — `ConfigurationController.php:114-138` reads and writes `.env` via `file_get_contents`/`file_put_contents`. This is a critical security risk:
  - No file locking (race condition risk)
  - No backup before modification
  - Controller-level file I/O
  - Potential for injection if values aren't properly escaped
- **Severity:** Critical
- **Fix:** Extract to service, add file locking, validate all values, create backup before write.

**F63. `$guarded = []` on `CredentialVault` model** — The credential vault stores encrypted secrets and uses `$guarded = []`.
- **Severity:** High
- **File:** `app/Models/CredentialVault.php:17`
- **Fix:** Use explicit `$fillable` to prevent mass assignment of sensitive fields.

**F64. `putenv()` usage** — `AgentInstallCommand.php:1124-1127` uses `putenv()` which is not thread-safe and can affect the entire process.
- **Severity:** Medium
- **Fix:** Use Laravel's config system instead.

**F65. `@file_get_contents()` with error suppression** — `InterrogationSessionController.php:3404` and `AgentJobController.php:680` suppress errors with `@`.
- **Severity:** Medium
- **Fix:** Use proper try-catch or `File::exists()` check.

**F66. DB::raw() usage** — 15+ instances of `DB::raw()` and `DB::select()` across the codebase, some with string interpolation.
- **Severity:** Medium
- **Files:** `MemoryStatsCommand.php`, `HybridRetriever.php`, `ConsolidationService.php`
- **Fix:** Audit all `DB::raw()` calls for SQL injection risk. Use parameterized queries.

**F67. WhatsApp webhook route bypasses signature verification** — `routes/api.php:428-430`: WhatsApp webhook uses `Route::match(['get', 'post'])` and the comment notes "GET verification doesn't have signature, so exclude signature middleware", but the `VerifyWebhookSignature` middleware is in the group wrapping all webhook routes.
- **Severity:** Medium
- **Fix:** Verify the middleware correctly handles GET requests without signatures.

**F68. Missing CSRF on web routes** — Need to verify web.php routes have proper CSRF protection.
- **Severity:** Medium

**F69. `$_ENV` direct access** — `ExecuteAgentRunJob.php:506` passes `$_ENV` directly to `DatabaseIsolationEnvironment::build()`.
- **Severity:** Medium
- **Fix:** Use `config()` values or `getenv()` with explicit keys.

**F69a. MemoryFormationPipeline not registered in container** — `app/Providers/MemoryServiceProvider.php` resolves `MemoryFormationPipeline::class` via `$app->make()` in `ConsolidationService` binding but never registers it. Will throw container resolution error at runtime.
- **Severity:** Critical (Bug)
- **Fix:** Add `$this->app->singleton(MemoryFormationPipeline::class, ...)` registration.

**F69b. Empty Horizon gate** — `app/Providers/HorizonServiceProvider.php:30-34` defines `viewHorizon` gate with an empty email array, blocking all users. Accepts `$user = null` parameter which is ambiguous.
- **Severity:** Critical (Security)
- **Fix:** Populate with authorized emails or use role-based check. Remove null default.

**F69c. Webhook signature brute-force fallback** — `app/Http/Middleware/Messenger/VerifyWebhookSignature.php:127-140` falls back to verifying against ALL active accounts when specific account resolution fails. Loads all accounts and iterates.
- **Severity:** High (Security / Performance)
- **Fix:** Log and reject unattributable requests rather than brute-forcing signature verification.

**F69d. Inconsistent feature flag method naming** — `FeatureFlagManager` exposes both `enabled()` and `isEnabled()`. Different middleware use different methods inconsistently. `HandleInertiaRequests` uses both in the same file.
- **Severity:** Medium
- **Fix:** Standardize on `enabled()` across all usage.

**F69e. Duplicate/conflicting attachment config** — `config/messenger.php:167-202` has three overlapping attachment config sections (`attachment_config`, `attachments`, `scan_attachments`) with conflicting field names and different env var names.
- **Severity:** Medium (DRY)
- **Fix:** Consolidate to single config structure.

**F69f. Missing Neo4j encryption settings** — `config/memory.php:300-306` Neo4j connection config lacks scheme, encryption, and trust settings. May default to insecure connections.
- **Severity:** Medium (Security)
- **Fix:** Add `scheme`, `encrypted`, and `trust` config keys.

**F69g. Tool deny/allow lists missing whitespace trim** — `config/runtime.php:43-44` splits env var by comma without trimming. `"tool1, tool2"` produces `["tool1", " tool2"]`.
- **Severity:** Medium (Security)
- **Fix:** Add `array_map('trim', ...)` after split.

**F70a. LogTailController — Unsafe file path resolution** — `app/Http/Controllers/Api/V1/LogTailController.php:97-122`
- **Severity:** Medium
- **Issue:** File path resolution uses string concatenation. While there is a channel whitelist, a crafted channel name like `laravel-../../sensitive` could potentially escape the logs directory.
- **Fix:** Use `realpath()` and verify the resolved path is within `storage_path('logs')`.

**F70b. ChatSessionController — Unvalidated query filters** — `app/Http/Controllers/Api/V1/ChatSessionController.php:22-30`
- **Severity:** High
- **Issue:** `$request->input('status')`, `$request->input('provider')`, `$request->input('connector_id')` used directly in query WHERE clauses without validation or whitelisting.
- **Fix:** Create a `ListChatSessionsRequest` FormRequest with enum/in validation for status and provider.

---

## 4. DRY Violations

**F70. Duplicated metadata merge pattern** — `ExecuteAgentRunJob.php` has 8+ instances of:
```php
$metadata = (array) ($run->metadata_json ?? []);
$run->metadata_json = array_merge($metadata, $patch);
$run->save();
```
- **Severity:** Medium
- **Fix:** Already has `updateMetadata()` helper — use it consistently.

**F71. Duplicated state transition error handling** — `ExecuteAgentRunJob.php` calls `finalizeTerminal()` in 6 different error paths with nearly identical payloads.
- **Severity:** Medium
- **Fix:** Create error-specific helper methods or use a result-type pattern.

**F72. Duplicated approval/permission/clarification resolution blocks** — `ExecuteAgentRunJob.php:562-576` and `ExecuteAgentRunJob.php:879-891` contain identical metadata cleanup logic.
- **Severity:** High
- **Fix:** Extract to `resolveMetadataFlags()` method.

**F73. `ClaudeAdapter` and `CodexAdapter` share significant structural code** — Both are 1,100+ lines with similar HTTP client setup, response parsing, and event writing.
- **Severity:** High
- **Fix:** Extract shared behavior into an `AbstractInterrogationAdapter` base class.

**F74. Session controllers repeat pagination boilerplate** — Multiple controllers repeat `$perPage = min(100, max(1, (int) $request->input('per_page', 15)))`.
- **Severity:** Low
- **Fix:** Extract to a trait or base controller method.

**F75. Repeated `ErrorEnvelope::make()` calls for 404s** — Many controllers check `if ($model === null) return ErrorEnvelope::make('NOT_FOUND', ...)`.
- **Severity:** Low
- **Fix:** Use route model binding to eliminate manual null checks.

---

## 5. Design Pattern Issues

**F76. Service Locator anti-pattern** — `app()` helper used extensively in jobs instead of constructor/method injection.
- **Severity:** High
- **Count:** 20+ instances across jobs
- **Fix:** Use Laravel's method injection in `handle()`.

**F77. God Object** — `InterrogationSessionController` is a god object with 90 methods spanning 4,124 lines.
- **Severity:** Critical
- **Fix:** Apply Command pattern — each action becomes a dedicated class.

**F78. Missing Strategy pattern** — `ConfigurationController::configKeyToEnv()` uses a match statement that grows linearly. Messenger adapters, interrogation adapters, and task provider drivers should use cleaner strategy dispatch.
- **Severity:** Medium

**F79. Missing State Machine pattern** — Multiple models (InterrogationSession, DelegationGraph, AgentJobRun, RepoAnalysisSession) embed state transition logic as inline if-else chains rather than using a proper state machine.
- **Severity:** High
- **Fix:** Use a state machine library (e.g., `spatie/laravel-model-states`) or define explicit `StateTransition` classes.

**F80. Missing Pipeline pattern** — `ExecuteAgentRunJob::handle()` performs 7+ sequential validation/transformation steps that would benefit from a pipeline.
- **Severity:** Medium
- **Fix:** Use `Illuminate\Pipeline\Pipeline` for pre-run and post-run steps.

---

## 6. Code Quality

**F81. Inconsistent error response patterns** — Some controllers return `response()->json(['error' => ...], 4xx)`, others use `ErrorEnvelope::make()`, others use `abort()`.
- **Severity:** Medium
- **Fix:** Standardize on `ErrorEnvelope` across all API controllers.

**F82. Magic numbers** — Various hardcoded values:
  - `ExecuteAgentRunJob.php:365` — `usleep(1_000_000)` (1 second wait after SIGTERM)
  - `ExecuteAgentRunJob.php:392` — `usleep(250_000)` (250ms poll interval)
  - `ExecuteAgentRunJob.php:320` — 10-second SIGKILL escalation timeout
  - `AgentJobRun.php:842` — `65535` max streak value
- **Severity:** Low
- **Fix:** Extract to named constants or config values.

**F83. Suppressed errors with `@`** — `@file_get_contents()` in 2 controllers, `@posix_kill()` in ExecuteAgentRunJob.
- **Severity:** Low
- **Fix:** Use proper error handling.

**F84. Empty catch blocks** — `ConfigurationController.php:85` catches `\Throwable` and does nothing (only has a comment).
- **Severity:** Low

**F85. Long parameter lists** — `finalizeTerminal()` accepts an array with 6+ optional keys. Difficult to reason about.
- **Severity:** Low
- **Fix:** Use a DTO for terminal state payload.

**F85a. Service instantiation inside API Resource** — `app/Http/Resources/MessengerConnectorResource.php:20` calls `app(ConnectorCredentialManager::class)` inside `toArray()`.
- **Severity:** Medium
- **Fix:** Pass pre-computed data from controller to resource. Resources should be thin transformation layers.

**F85b. Inconsistent response structure across list endpoints** — Some controllers use `pagination` meta with `links`, others use `meta` and `links` separately. Some include `filters`, some don't.
- **Severity:** Low
- **Fix:** Create a reusable `PaginatedResponse` trait or response builder.

**F85c. ChatSessionController returns raw models** — `app/Http/Controllers/Api/V1/ChatSessionController.php:37` returns raw Eloquent models. `ChatSessionResource` exists but is not used.
- **Severity:** Low
- **Fix:** Wrap with `ChatSessionResource::collection()`.

---

## 7. Potential Bugs

**F86. Race condition in `.env` writes** — `ConfigurationController::writeEnvValues()` has no file locking. Concurrent requests could corrupt `.env`.
- **Severity:** High
- **Fix:** Use `flock()` or queue the write operation.

**F87. `$request->all()` with `Validator::make()`** — `MessengerConnectorController.php:103` passes `$request->all()` which includes unexpected fields.
- **Severity:** Medium
- **Fix:** Use `$request->only([...])` to explicitly select fields.

**F88. Cleanup not always called** — `ExecuteAgentRunJob.php:493` calls `cleanupEnhancedTaskFile()` in the try block but not in the catch block at line 494-497. If the job fails with an exception, the temp file may leak.
- **Severity:** Medium
- **Fix:** Move cleanup to a `finally` block.

**F89. `$originalTaskPath` set but never restored** — `ExecuteAgentRunJob.php:163` stores original task path but modifies `$run->job->task_markdown_path` without restoring it.
- **Severity:** Low
- **Note:** Comment says "not persisted to DB" but `$run->job` is a loaded relation — changes persist in memory for the rest of the job execution.

---

## Recommendations

### Immediate (P0) — Critical security and reliability fixes

1. **Add `Model::shouldBeStrict()` to AppServiceProvider** — Prevents silent N+1 queries and attribute issues
2. **Extract `.env` I/O from ConfigurationController** — Move to a dedicated service with file locking
3. **Add `$fillable` to `CredentialVault` model** — Protect sensitive credential storage
4. **Add authorization to all controllers** — Create policies for every resource
5. **Add `finally` block for temp file cleanup in `ExecuteAgentRunJob`**
6. **Add file locking to `.env` write operations**

### Short-term (P1) — Architecture improvements

7. **Break `InterrogationSessionController` into 6+ smaller controllers/services** — Highest-impact refactor
8. **Add `failed()` methods to critical jobs** — Especially state-transition and execution jobs
9. **Replace `app()` service locator calls with proper DI** — Across all jobs
10. **Extract inline validations to FormRequest classes** — 21+ inline validations to convert
11. **Add `ShouldBeUnique` to execution jobs** — Prevent double-processing
12. **Use route model binding** — Eliminate manual find/null-check patterns
13. **Extract `RunEventWriter` detectors using chain pattern**
14. **Use `Route::apiResource()` for standard CRUD patterns**

### Long-term (P2) — Structural improvements

15. **Implement state machine pattern for stateful models** — `InterrogationSession`, `DelegationGraph`, `AgentJobRun`
16. **Extract shared adapter code into abstract base classes** — `ClaudeAdapter`/`CodexAdapter`, messenger adapters
17. **Use pipeline pattern for `ExecuteAgentRunJob`** — Pre-run and post-run step chains
18. **Migrate string status constants to PHP 8.3 backed enums**
19. **Standardize error response pattern** — `ErrorEnvelope` everywhere
20. **Split `TaskManagementProviderDriver` into smaller interfaces**

---

## Review Metadata

- **Reviewer:** SOLID Analyst (automated)
- **Files analyzed:** 70 models, 54 controllers, 39 jobs, 32 FormRequests, 36 config files, 4 route files
- **Total app/ lines:** 104,224
- **Largest files:** InterrogationSessionController (4,124), ExecuteInterrogationRoundJob (1,427), ReportComposer (1,196), CodexAdapter (1,183), AgentInstallCommand (1,171)
- **Graph:** SOLID Analysis
- **Task ID:** 98
- **Synthesis Mode:** comprehensive
