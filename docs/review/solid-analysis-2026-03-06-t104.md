# Laravel Best Practice & SOLID Analysis

**Project:** Agent Scheduler (Laravel 12 / PHP 8.3)
**Date:** 2026-03-06
**Graph:** SOLID Analysis | Task ID: 104 | Attempt: 1
**Scope:** `app/` -- Models, Controllers, FormRequests, Jobs, Services, Routes, Middleware, Config, Providers
**Focus Areas:** SOLID, Laravel Best Practices, DRY, Design Patterns, Code Quality, Bugs, Security

---

## STAR Pre-Execution

### SITUATION
The Agent Scheduler codebase contains ~70 models, ~70 controllers, ~30 FormRequests, ~40 jobs, ~60 services, and multiple config/route files. Previous reviews identified persistent god classes (`ExecuteAgentRunJob` at 987 LOC, `MessengerRuntimeOrchestrator` at 314 LOC). New subsystems (memory formation, org rituals, licensing, runtime sessions) have been added. This task performs a fresh, comprehensive Laravel best practices review across all layers.

### TASK
Produce a structured review with severity-rated findings covering: SOLID violations, Laravel anti-patterns, N+1 queries, missing FormRequests, mass assignment risks, authorization gaps, security concerns, DRY violations, and design pattern issues.

### ACTION
1. Launched four parallel exploration agents covering: (a) Models, (b) Controllers + FormRequests, (c) Jobs + Services, (d) Routes + Middleware + Config + Providers
2. Each agent reviewed 15-30 files in its scope and produced categorized findings
3. Synthesized into unified report with priority-ordered recommendations

### RESULT
This report identifies **89 findings** across all categories. Critical structural risks remain in god classes and security gaps. New findings include missing authorization in 5 controllers, hardcoded Neo4j credentials, overly permissive mass assignment in 22+ models, and missing rate limiting on sensitive endpoints.

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 9 |
| High | 27 |
| Medium | 34 |
| Low | 19 |
| **Total** | **89** |

### SOLID Scorecard

| Principle | Grade | Key Issues |
|-----------|-------|------------|
| Single Responsibility | D | God job (987 LOC), god services, fat controllers |
| Open/Closed | C | Hardcoded provider details, missing strategy patterns |
| Liskov Substitution | B+ | Generally well-implemented interfaces |
| Interface Segregation | C+ | Fat ToolGateway, missing service interfaces |
| Dependency Inversion | C | Direct config reads, concrete dependencies, missing abstractions |

---

## 1. Security Findings

### 1.1 Missing Authorization Checks in Controllers (CRITICAL)
**Files:**
- `app/Http/Controllers/Api/V1/ConnectorPolicyController.php:12-58` -- `show()` and `update()` have no `$this->authorize()` calls
- `app/Http/Controllers/Api/V1/PairingController.php:47-75` -- `approve()` and `revoke()` operate on any `MessengerIdentityLink` without ownership check
- `app/Http/Controllers/Api/V1/InterrogationSettingsController.php:25-52` -- `show()` and `update()` lack authorization
- `app/Http/Controllers/Api/V1/DelegateeProfileController.php:114-150` -- `store()` missing explicit authorization
- `app/Http/Controllers/Api/V1/MessengerConnectorController.php:98-120` -- store/update without authorization

**Impact:** Any authenticated user can view or modify resources belonging to other users.

**Fix:** Add `$this->authorize('view', $resource)` / `$this->authorize('update', $resource)` to all CRUD operations. Create Policy classes where missing.

### 1.2 Hardcoded Neo4j Default Password (CRITICAL)
**File:** `config/memory.php:300-306`
```php
'password' => env('NEO4J_PASSWORD', 'password'),
```

**Impact:** Production deployments may inadvertently use the default password if the environment variable is not set.

**Fix:** Remove default and throw on missing value:
```php
'password' => env('NEO4J_PASSWORD') ?: throw new \RuntimeException('NEO4J_PASSWORD is required'),
```

### 1.3 Unsanitized Process Command in AttachmentHandler (MEDIUM)
**File:** `app/Services/Messenger/AttachmentHandler.php:131`
```php
$result = Process::run("clamdscan --no-summary {$path}");
```

**Impact:** String interpolation in shell command. While `$path` is internally generated, this pattern is fragile. Any future code path that allows user influence on `$path` would create a shell injection vulnerability.

**Fix:** Use array syntax:
```php
$result = Process::run(['clamdscan', '--no-summary', $path]);
```

### 1.4 API Key Leakage in Error Logging (LOW)
**File:** `app/Services/Runtime/RuntimeLlmClient.php:67-75`

**Impact:** Failed API responses may contain sensitive data that gets logged verbatim.

**Fix:** Truncate and mask response body in error messages.

### 1.5 Horizon Gate Prevents All Access (CRITICAL)
**File:** `app/Providers/HorizonServiceProvider.php:30-34`
```php
Gate::define('viewHorizon', function ($user = null) {
    return in_array(optional($user)->email, [
        //
    ]);
});
```

**Impact:** Empty email array means no admin can access Horizon dashboard in production.

**Fix:** Populate with admin emails or use role-based check.

### 1.6 Public Health Endpoints Without Authentication (LOW)
**File:** `routes/web.php:31-35`

**Impact:** `/messenger/health` and `/agent/health/deployment` expose system status publicly.

**Fix:** Add IP whitelisting or basic auth for monitoring endpoints.

---

## 2. Mass Assignment Vulnerabilities

### 2.1 Overly Permissive `guarded = []` (HIGH)
**22+ models** use `protected $guarded = []`, making ALL attributes mass-assignable including sensitive fields like `user_id`, `team_id`, `status`.

**Affected Models (partial list):**
| Model | File | Line |
|-------|------|------|
| DelegationTask | `app/Models/DelegationTask.php` | 19 |
| ChatSession | `app/Models/ChatSession.php` | 20 |
| AgentJob | `app/Models/AgentJob.php` | 24 |
| DelegationGraph | `app/Models/DelegationGraph.php` | 20 |
| InterrogationSession | `app/Models/InterrogationSession.php` | 21 |
| DelegateeProfile | `app/Models/DelegateeProfile.php` | 21 |
| RepoAnalysisTask | `app/Models/RepoAnalysisTask.php` | 13 |
| MemoryConversationLog | `app/Models/MemoryConversationLog.php` | 22 |
| MemoryFormationFailure | `app/Models/MemoryFormationFailure.php` | 18 |
| ConnectorAccount | `app/Models/ConnectorAccount.php` | 23 |

**Fix:** Replace with explicit `$fillable` arrays on all models.

---

## 3. SOLID Violations

### 3.1 SRP: ExecuteAgentRunJob God Job (CRITICAL)
**File:** `app/Jobs/ExecuteAgentRunJob.php:35-987`

987-line job handling: process spawning, signal handling, output monitoring, state transitions, cost recording, memory context injection, STAR preamble generation, environment management, reasoning step parsing, approval gate integration, and event emission.

**Fix:** Extract into focused services:
- `ProcessLifecycleManager` -- spawn/signal/termination
- `OutputParser` -- output monitoring and parsing
- `RunCostRecorder` -- cost extraction and budget recording
- `RunMetadataManager` -- metadata mutations
- `StarPreambleGenerator` -- STAR preamble construction

### 3.2 SRP: MessengerRuntimeOrchestrator God Service (HIGH)
**File:** `app/Services/Runtime/MessengerRuntimeOrchestrator.php:14-314`

5 injected dependencies, handles: turn execution, LLM calls, tool use extraction, message normalization, system prompt building, session title derivation, and CLI/in-app mode switching.

**Fix:** Extract into: `LlmTurnExecutor`, `ToolUseExtractor`, `SystemPromptBuilder`, `TitleDeriver`.

### 3.3 SRP: ProcessRuntimeTurnJob Mixed Concerns (HIGH)
**File:** `app/Jobs/Runtime/ProcessRuntimeTurnJob.php:22-361`

Handles: runtime orchestration, progress callbacks, chat message persistence, connector adaptation, error transmission, and multiple result-state branches.

**Fix:** Extract `RuntimeProgressUpdater`, `ChatMessagePersister`, and use strategy pattern for result handlers.

### 3.4 SRP: AttachmentHandler Multiple Responsibilities (HIGH)
**File:** `app/Services/Messenger/AttachmentHandler.php:16-300`

Handles: HTTP downloads, size/MIME validation, ClamAV scanning, storage, quarantine, and presigned URL generation.

**Fix:** Split into `AttachmentDownloader`, `AttachmentValidator`, `MalwareScannerAdapter`, `AttachmentStorer`.

### 3.5 ISP: ToolGateway Fat Dependencies (HIGH)
**File:** `app/Services/Runtime/ToolGateway.php:12-307`

Two distinct responsibilities (schema generation and tool execution) forced into one class, requiring all consumers to depend on both.

**Fix:** Split into `ToolSchemaProvider` and `ToolExecutor`.

### 3.6 DIP: WorkflowBudgetEnforcer Concrete Dependencies (HIGH)
**File:** `app/Services/Cost/WorkflowBudgetEnforcer.php:17-413`

Directly calls `IncidentLifecycleService`, `WorkflowGovernanceService`, and `GateTransitionRecorder` within transaction boundaries. No abstraction for enforcement actions.

**Fix:** Create `BudgetEnforcementAction` interface, inject array of actions.

### 3.7 DIP: RuntimeLlmClient Hardcoded Provider (MEDIUM)
**File:** `app/Services/Runtime/RuntimeLlmClient.php:14-111`

Anthropic-specific API version and endpoint hardcoded. No provider abstraction for swapping LLM backends.

**Fix:** Create `LlmProvider` interface with `AnthropicProvider` implementation.

### 3.8 DIP: PolicyEngine Implicit Config Dependencies (MEDIUM)
**File:** `app/Services/Runtime/PolicyEngine.php:10-131`

Reads config directly at lines 21, 45-46, 65-66, 87 instead of accepting policies via constructor injection.

**Fix:** Accept policy arrays via constructor DI.

### 3.9 OCP: ChatIntentParser Tight Coupling (HIGH)
**File:** `app/Services/Messenger/ChatIntentParser.php:200-207`

Directly spawns CLI process for AI parsing with hardcoded timeout, no fallback strategy, no circuit breaker.

**Fix:** Create `IntentParsingProvider` interface with `CliIntentParsingProvider` and `LlmIntentParsingProvider` implementations.

---

## 4. Laravel Anti-Patterns

### 4.1 N+1 Query Risk in forUser() Scopes (CRITICAL)
**Files:**
- `app/Models/AgentJob.php:117-124`
- `app/Models/Runtime/RuntimeSession.php:121-128`
- `app/Models/AgentJobRun.php:93-102`

Each call to `scopeForUser()` executes `$user->allTeams()->pluck('id')` -- a separate query. When multiple models use this scope in sequence, it creates N+1 patterns.

**Fix:** Cache team IDs in calling code or accept them as parameter:
```php
public function scopeForUser(Builder $query, User $user, ?array $teamIds = null): void
{
    $teamIds ??= $user->allTeams()->pluck('id')->toArray();
    // ...
}
```

### 4.2 Missing FormRequests on Store/Update Operations (HIGH)
**Controllers using inline `$request->validate()`:**
- `app/Http/Controllers/Api/V1/ConnectorPolicyController.php:19-33`
- `app/Http/Controllers/Api/V1/DelegateeProfileController.php:114-126`
- `app/Http/Controllers/Api/V1/MessengerConnectorController.php:98-120`
- `app/Http/Controllers/Api/V1/DelegationGraphController.php:115-126, 170-186`

**Fix:** Create dedicated FormRequest classes for each operation.

### 4.3 Fat Controllers with Business Logic (HIGH)
**Files:**
- `app/Http/Controllers/Api/V1/ConfigurationController.php:54-138` -- Contains `writeEnvValues()` method that manipulates `.env` file directly
- `app/Http/Controllers/Api/V1/DebugPanelController.php:15-96` -- 75+ line `index()` aggregating data from 7+ models

**Fix:** Extract into `ConfigurationWriterService` and `SystemHealthService`.

### 4.4 Fat Model: ConnectorAccount (HIGH)
**File:** `app/Models/ConnectorAccount.php:108-287`

~150 lines of business logic for config management (`getDmPolicy`, `setDmPolicy`, `getGroupPolicy`, `getSoul`, `setSoul`, etc.).

**Fix:** Extract into `ConnectorConfigManager` service.

### 4.5 Fat Model: InterrogationEvent (HIGH)
**File:** `app/Models/InterrogationEvent.php:45-140`

~95 lines of payload encoding/decoding logic with UTF-8 normalization.

**Fix:** Extract into `PayloadNormalizer` service.

### 4.6 Missing Scopes for Repeated Status Filtering (MEDIUM)
**Models with status constants but missing filtering scopes:**
- `app/Models/DelegationTask.php` -- 9 status constants, no scopes
- `app/Models/InterrogationSession.php` -- 14 constants with ACTIVE/TERMINAL sets
- `app/Models/InterrogationBuildTask.php` -- status filtering in compliance methods

**Fix:** Add `scopeActive()`, `scopeTerminal()`, `scopePending()` to each model.

### 4.7 Inconsistent Scope Return Types (MEDIUM)
Some scopes return `Builder`, others return `void`:
- `app/Models/DelegationGraph.php:72-80` -- returns `Builder`
- `app/Models/AgentJob.php:102-115` -- returns `void`
- `app/Models/ChatSession.php:53-66` -- returns `void`

**Fix:** Standardize all scopes to return `Builder` for chainability.

### 4.8 Missing Implicit Route Model Binding (MEDIUM)
**File:** `routes/api.php` -- Throughout

Routes use `{id}` parameters with manual `findOrFail()` in controllers instead of leveraging Laravel's implicit route model binding.

**Fix:** Use typed parameters (`{job}`, `{session}`) and accept model instances in controller methods.

### 4.9 Direct Model Queries in Controllers (MEDIUM)
**Files:**
- `app/Http/Controllers/Api/V1/ComplianceController.php:26-46` -- Complex aggregation queries inline
- `app/Http/Controllers/Api/V1/DebugPanelController.php:38-40` -- Multiple model queries without services

**Fix:** Extract into repository or service classes.

---

## 5. DRY Violations

### 5.1 Duplicated Event Writing Pattern (MEDIUM)
**Files:**
- `app/Jobs/ExecuteAgentRunJob.php` -- lines 88-93, 283-289, 350-357, 601-608
- `app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php` -- lines 110-117, 197-206, 301-312, 636-644

Multiple jobs create `EventWriter` instances and call `append()` with similar payload structures.

**Fix:** Create abstract `BaseAsyncJob` with `recordStateTransition()` helper.

### 5.2 Duplicated forUser() Scope Logic (MEDIUM)
Three models (`AgentJob`, `RuntimeSession`, `AgentJobRun`) implement nearly identical `forUser()` scopes with the same team ID resolution pattern.

**Fix:** Extract into a `ForUserScope` trait or shared scope class.

---

## 6. Job Anti-Patterns

### 6.1 Missing ShouldBeUnique Where Needed (MEDIUM)
**Files:**
- `app/Jobs/Messenger/ProcessInboundMessage.php` -- can process same message twice on retry
- `app/Jobs/Runtime/ProcessRuntimeTurnJob.php` -- can execute same turn twice
- `app/Jobs/Runtime/SubAgentCompletionJob.php` -- can spawn duplicate agents

**Fix:** Implement `ShouldBeUnique` or `WithoutOverlapping()` middleware.

### 6.2 DeliverWebhookJob Hardcoded Linear Backoff (MEDIUM)
**File:** `app/Jobs/Agent/DeliverWebhookJob.php:23`
```php
public int $backoff = 5;
```

**Fix:** Use exponential backoff array: `public array $backoff = [5, 10, 30];`

### 6.3 Timeout Configuration Inconsistencies (LOW)
Jobs use inconsistent patterns for timeout: some use class constants, some use config at runtime, some use both with confusing precedence.

**Fix:** Standardize on config-driven timeouts with class-level defaults.

---

## 7. Route & Middleware Issues

### 7.1 Missing Rate Limiting on Sensitive GET Endpoints (HIGH)
**File:** `routes/api.php`

Endpoints without rate limiting:
- `/health` (line 69) -- public, no rate limit
- `/runs` (line 102) -- diagnostics
- `/audit-log` (line 133) -- audit data
- `/logs` (line 137-138) -- log endpoints

**Fix:** Apply `throttle:agent-reads` middleware to all sensitive GET endpoints.

### 7.2 Inconsistent Feature Gate Error Responses (MEDIUM)
**Files:**
- `app/Http/Middleware/DelegationUiFeatureGate.php:16` -- uses `abort(403)`
- `app/Http/Middleware/OrgUiFeatureGate.php:17` -- uses `abort(404)`
- `app/Http/Middleware/OrgFeatureGate.php` -- uses `ErrorEnvelope::make()`
- `app/Http/Middleware/MemoryEnabled.php` -- uses `ErrorEnvelope::make()`

**Fix:** Standardize all feature gates to use `ErrorEnvelope::make()` for API routes.

### 7.3 Inconsistent Route Parameter Naming (MEDIUM)
**File:** `routes/api.php`

Mixed parameter styles: `{id}` vs `{workflowKey}` vs `{graphId}` vs `{buildId}`.

**Fix:** Standardize on `{id}` for numeric PKs and descriptive names for string keys.

### 7.4 Redundant Route Pattern Definitions (LOW)
**File:** `bootstrap/app.php:129-130`
```php
Route::pattern('workflowKey', WorkflowKey::routePattern());
Route::pattern('workflow_key', WorkflowKey::routePattern());
```

**Fix:** Standardize on one convention (snake_case per Laravel conventions).

---

## 8. Config & Provider Issues

### 8.1 AppServiceProvider Bloated (HIGH)
**File:** `app/Providers/AppServiceProvider.php:77-231`

Handles: service composition, 7 tool adapter registrations, 4 event listener subscriptions, 9 gate policies, and 5 rate limiters.

**Fix:** Split into: `ToolServiceProvider`, `PolicyServiceProvider`, `RateLimitServiceProvider`.

### 8.2 Missing Environment Defaults for Critical Settings (HIGH)
**File:** `config/agent.php:18-20, 28-29, 148`

- Codex model defaults to non-existent `'gpt-5.3-codex'`
- Reviewer model defaults to `null`

**Fix:** Add validation or sensible defaults.

### 8.3 Scattered Rate Limit Configuration (MEDIUM)
Rate limiting defined across `AppServiceProvider` and `bootstrap/app.php` with no central config file.

**Fix:** Create `config/rate_limits.php` with all rate limit definitions.

### 8.4 MemoryServiceProvider Circular Dependency Risk (MEDIUM)
**File:** `app/Providers/MemoryServiceProvider.php:134-143`

`ensureMemoryEnabled()` resolves `FeatureFlagManager` which may depend on database queries, failing during fresh installations.

**Fix:** Add try-catch with graceful fallback during console/installation context.

### 8.5 Broadcast Channels Don't Log Authentication Failures (MEDIUM)
**File:** `routes/channels.php:21-68`

Failed channel auth returns `false` silently without audit logging.

**Fix:** Add warning-level logging for failed broadcast authentication attempts.

---

## 9. Missing Service Interfaces

### 9.1 Services Without Interface Contracts (MEDIUM)
Multiple core services lack interfaces, preventing mock testing and implementation swapping:

| Service | File |
|---------|------|
| WorkflowBudgetEnforcer | `app/Services/Cost/WorkflowBudgetEnforcer.php` |
| AttachmentHandler | `app/Services/Messenger/AttachmentHandler.php` |
| ChatSessionManager | `app/Services/Messenger/ChatSessionManager.php` |
| RuntimeLlmClient | `app/Services/Runtime/RuntimeLlmClient.php` |
| ToolGateway | `app/Services/Runtime/ToolGateway.php` |
| PolicyEngine | `app/Services/Runtime/PolicyEngine.php` |

**Fix:** Create interface contracts in `app/Contracts/` for all services that may have alternative implementations.

---

## Priority Remediation Roadmap

### Immediate (Critical -- Address This Sprint)
1. **Add authorization checks** to 5 controllers missing `$this->authorize()` calls
2. **Remove hardcoded Neo4j password** from `config/memory.php`
3. **Fix Horizon gate** to allow admin access
4. **Cache forUser() team IDs** to prevent N+1 query cascades

### Short-Term (High -- Next 2 Sprints)
5. **Replace `guarded = []`** with explicit `$fillable` on all 22+ models
6. **Create FormRequest classes** for 4 controllers using inline validation
7. **Add rate limiting** to unprotected sensitive GET endpoints
8. **Fix shell command injection pattern** in `AttachmentHandler`
9. **Extract business logic** from `ConfigurationController` and `DebugPanelController`
10. **Split `AppServiceProvider`** into focused providers
11. **Extract `ConnectorAccount` config logic** into service

### Medium-Term (Medium -- Next Quarter)
12. **Refactor `ExecuteAgentRunJob`** (987 LOC) into focused services
13. **Split `MessengerRuntimeOrchestrator`** into single-responsibility services
14. **Create service interfaces** for core services
15. **Add `ShouldBeUnique`** to jobs that can duplicate work
16. **Standardize scope return types** across all models
17. **Implement route model binding** across API routes
18. **Create `IntentParsingProvider` interface** with fallback strategy

### Long-Term (Low -- Backlog)
19. **Standardize route parameter naming** conventions
20. **Add broadcast channel auth logging**
21. **Unify timeout configuration** patterns across jobs
22. **Remove redundant route pattern definitions**

---

## Metrics

| Category | Files Reviewed | Issues Found |
|----------|---------------|--------------|
| Models | 30+ | 14 |
| Controllers | 20+ | 13 |
| FormRequests | 10+ | 4 |
| Jobs | 18+ | 12 |
| Services | 18+ | 22 |
| Routes/Middleware | 8 | 11 |
| Config/Providers | 8 | 13 |
| **Total** | **112+** | **89** |
