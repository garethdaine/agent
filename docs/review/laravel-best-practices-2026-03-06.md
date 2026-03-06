# Laravel Best Practices Review

**Date:** 2026-03-06
**Graph:** SOLID Analysis | Task ID: 92 | Attempt: 1
**Framework:** Laravel 12 / PHP 8.3
**Scope:** Full codebase — controllers, routes, middleware, jobs, commands, models, services, migrations, queue config
**Excludes:** vendor, node_modules, storage, .git

---

## Executive Summary

Reviewed 64 controllers, 32 form requests, 5 API resources, 75 models, 68 services, 211 support classes, 94 migrations, and all route/middleware/queue configuration. The codebase demonstrates solid foundational patterns (service layer separation, scopes, DTOs, proper migration reversibility) but has significant areas for improvement around fat controllers, missing FormRequests, job resilience, and mass assignment protection.

**Total Issues Found: 68**

| Severity | Count |
|----------|-------|
| Critical | 6 |
| High | 12 |
| Medium | 28 |
| Low | 22 |

---

## Critical Issues

### C1. InterrogationSessionController — 4,124-Line Controller

**File:** `app/Http/Controllers/Api/V1/InterrogationSessionController.php`
**Lines:** 1–4124

The largest controller in the codebase. Contains business logic, state transitions, data transformation, and export logic that should be distributed across dedicated services.

**Fix:** Break into focused controllers and extract into services:
- `InterrogationStateTransitionService`
- `InterrogationTransformerService`
- `InterrogationExportService`

---

### C2. OfficeStateController — 467-Line Invokable Controller

**File:** `app/Http/Controllers/Api/V1/OfficeStateController.php`
**Lines:** 1–467

Single `__invoke` method with 9 private methods building an entire dashboard state. All data aggregation and transformation logic belongs in a service.

**Fix:** Extract into `OfficeStateBuilderService`. Controller should only orchestrate and return.

---

### C3. ConfigurationController — Direct .env File I/O

**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php`
**Lines:** 114–138

Controller directly reads and writes `.env` using `file_get_contents()` / `file_put_contents()`. Security and architectural concern — file I/O should never live in a controller.

**Fix:** Extract into `EnvironmentConfigurationService` with proper validation, error handling, and access control.

---

### C4. N8n Webhook Route — No Authentication

**File:** `routes/api.php`
**Line:** 73

The N8n webhook endpoint sits inside the `agent/api/v1` prefix but outside the `auth:sanctum` middleware group. No authentication or signature verification is applied.

```php
Route::post('/n8n/webhook', \App\Http\Controllers\Api\V1\N8nWebhookController::class);
```

**Fix:** Add signature verification middleware (like Messenger webhooks) or move inside the authenticated group. Document explicitly if intentionally public.

---

### C5. N+1 Query Risk in ChatSessionManager

**File:** `app/Services/Messenger/ChatSessionManager.php`
**Lines:** 64–83, 127–143

`getSessionHistory()` calls `$session->messages()` without eager loading relationships. Iterating results and accessing relationships triggers additional queries.

**Fix:**
```php
$query = $session->messages()->with(['attachments', 'actions']);
```

---

### C6. N+1 Query in CompactionService

**File:** `app/Services/Messenger/CompactionService.php`
**Lines:** 45, 77

`$session->messages()` is called multiple times independently in `isCompactionNeeded()` and `compactIfNeeded()`. Each call hits the database.

**Fix:** Cache the messages collection or consolidate into a single query.

---

## High Issues

### H1. Inline Validation Instead of FormRequests (40+ instances)

**Files:** Multiple controllers including:
- `app/Http/Controllers/Api/V1/ChatSessionController.php:63-65`
- `app/Http/Controllers/Api/V1/CredentialsController.php:41-65`
- `app/Http/Controllers/Api/V1/DelegationTaskController.php:116`

40+ instances of `$request->validate()` inline in controller methods instead of dedicated FormRequest classes.

**Fix:** Create FormRequest classes:
- `SendChatMessageRequest`
- `StoreCredentialRequest`
- `DestroyCredentialRequest`
- `ResolveVerificationRequest`
- etc.

---

### H2. AccountLinkController — Business Logic in Controller

**File:** `app/Http/Controllers/Messenger/AccountLinkController.php`
**Lines:** 56–127

`store()` contains database queries, identity link creation/updates, and logging logic spanning 70+ lines.

**Fix:** Extract into `MessengerAccountLinkService::completeLink()`.

---

### H3. Mass Assignment Vulnerability — `$guarded = []` on Many Models

**Files:** `DelegateeProfile`, `AgentJobRun`, `DelegationTask`, `DelegationGraph`, `InterrogationSession`, `ChatSession`, `RepoAnalysisTask`, `MemoryEmbedding`, `ConnectorAccount`, `RuntimeSession`, `RuntimeTurn`

Using `protected $guarded = []` allows all mass assignment. Exposes unintended attributes to mass assignment.

**Fix:** Replace with explicit `$fillable` arrays listing allowed attributes.

---

### H4. ExecuteAgentRunJob — Missing `failed()` Method

**File:** `app/Jobs/ExecuteAgentRunJob.php`
**Lines:** 35–987

Complex state management job with no `failed()` method. If the job fails at the queue level (retries exhausted), the run may remain stuck in STARTING/RUNNING state indefinitely.

**Fix:** Implement `failed(Throwable $exception)` that calls `failRunSafely()` to transition to a terminal state.

---

### H5. DelegationBroadcastSubscriber — ShouldQueue on Event Subscriber

**File:** `app/Listeners/DelegationBroadcastSubscriber.php`
**Lines:** 28, 40

Implements `ShouldQueue` while subscribing to multiple events. Creates race conditions — events fire before the listener is queued, and queued execution may be out of order.

**Fix:** Remove `ShouldQueue` (keep synchronous) or refactor to dispatch individual broadcast jobs.

---

### H6. Multiple Jobs Missing Idempotency Guarantees

**Files:**
- `app/Jobs/RecalculateTrustScoresJob.php`
- `app/Jobs/Org/OrgDispatchDueRitualsJob.php`
- `app/Jobs/Org/OrgEscalationTimeoutJob.php`

State-changing jobs with no idempotency checks. Duplicate dispatch (retry or duplicate) causes double execution.

**Fix:** Implement idempotency keys or check "already processed" state before executing.

---

### H7. SessionProcessManager — Static State in Queue Workers

**File:** `app/Services/Runtime/SessionProcessManager.php`
**Lines:** 29–30

`private static array $activeProcesses = []` uses in-memory static state. Different queue workers won't share this state.

**Fix:** Use Redis for process state tracking instead of static arrays.

---

### H8. AgentInstallCommand — 1,171-Line Command

**File:** `app/Console/Commands/AgentInstallCommand.php`
**Lines:** 81–1171

Massive command with validation, configuration, and setup logic that should be in services.

**Fix:** Extract into `LicenseValidator`, `EnvironmentValidator`, `ConnectorConfigurator` services.

---

### H9. CredentialVault — Silent Decryption Failures

**File:** `app/Models/CredentialVault.php`
**Lines:** 33–46

`getDecryptedValue()` returns `null` on decryption failure without logging or throwing. Masks corruption or key rotation issues.

**Fix:** Log and throw `CredentialDecryptionException`.

---

### H10. Neo4jGraphStore — Default Password in Config

**File:** `app/Support/Memory/Neo4jGraphStore.php`
**Lines:** 301–307

```php
$password = config('memory.neo4j.password', 'password');
```

Default password fallback is a security risk.

**Fix:** Require credentials to be configured; throw `RuntimeException` if missing.

---

### H11. Missing Return Type Declarations on Middleware

**Files:**
- `app/Http/Middleware/DelegationUiFeatureGate.php:13`
- `app/Http/Middleware/OrgUiFeatureGate.php:13`

`handle()` methods missing `: Response` return type. Inconsistent with other middleware in the codebase.

**Fix:** Add `public function handle(Request $request, Closure $next): Response`.

---

### H12. Deprecated Accessor/Mutator Pattern

**File:** `app/Models/AgentJob.php`
**Lines:** 64–85

Using `setCommandAttribute()` / `getCommandAttribute()` instead of the `Attribute` class introduced in Laravel 8+.

**Fix:** Migrate to `Attribute::make(get: ..., set: ...)`.

---

## Medium Issues

### M1. AppServiceProvider Overloaded (77–231 lines of boot logic)

**File:** `app/Providers/AppServiceProvider.php`

15 singletons, 4 event subscriptions, 9 policies, 6 gate definitions, 4 rate limiters, 2 route patterns, console guards, observers, Scramble config, and tool gateway registration all in one provider.

**Fix:** Extract into domain-specific providers: `ToolingServiceProvider`, `AuthorizationServiceProvider`, `RateLimitingServiceProvider`.

---

### M2. Inconsistent Feature Flag Method Names

**Files:**
- `app/Http/Middleware/OrgFeatureGate.php:17` — uses `isEnabled()`
- `app/Http/Middleware/DelegationFeatureGate.php:17` — uses `enabled()`

**Fix:** Standardize on `enabled()` across all middleware.

---

### M3. Inconsistent HTTP Status Codes in Feature Gates

**Files:**
- `app/Http/Middleware/DelegationUiFeatureGate.php:16` — `abort(403)`
- `app/Http/Middleware/OrgUiFeatureGate.php:17` — `abort(404)`

**Fix:** Use consistent status code for the same scenario.

---

### M4. Missing Timeouts on Multiple Jobs

**Files:**
- `app/Jobs/AiCriticCompletedJob.php` — no timeout
- `app/Jobs/Messenger/SendOutboundMessage.php` — no timeout
- `app/Jobs/Messenger/ProcessChatIntent.php` — no timeout
- `app/Jobs/Org/OrgEscalationTimeoutJob.php` — no timeout
- `app/Jobs/Agent/DeliverWebhookJob.php` — no timeout
- `app/Jobs/Runtime/SubAgentCompletionJob.php` — no timeout
- `app/Jobs/Runtime/RuntimeTurnCompletedJob.php` — no timeout

**Fix:** Add explicit `public int $timeout = N;` to all jobs.

---

### M5. DeliverWebhookJob — Fixed Backoff

**File:** `app/Jobs/Agent/DeliverWebhookJob.php`
**Lines:** 21–31

`public int $backoff = 5` (fixed 5 seconds for all retries). Poor for webhook delivery — external APIs need exponential backoff.

**Fix:** Change to `public array $backoff = [5, 30, 120];`.

---

### M6. Horizon Workers Never Restart

**File:** `config/horizon.php`
**Lines:** 212–352

All supervisors have `'maxJobs' => 0` and `'maxTime' => 0`. Workers never restart, risking memory leaks.

**Fix:** Set `'maxTime' => 3600` and `'maxJobs' => 1000` for periodic worker recycling.

---

### M7. Inconsistent Backoff Configuration in Horizon

**File:** `config/horizon.php`
**Lines:** 212–352

Backoff values vary: some supervisors use `0`, others use arrays. Job-level backoff overrides supervisor config, creating unpredictable retry behavior.

**Fix:** Standardize and document which takes precedence.

---

### M8. DelegationRecoveryHandler — Race Condition on Retry Counts

**File:** `app/Listeners/DelegationRecoveryHandler.php`
**Lines:** 84–130

Counts attempts and profiles without locks. Between count and action, another process could create a new attempt, violating retry limits.

**Fix:** Use database transactions with `FOR UPDATE` locks.

---

### M9. DelegationCoordinator — Heavy Logic in Event Subscriber

**File:** `app/Listeners/DelegationCoordinator.php`
**Lines:** 35–378

Contains spawning, assigning, transitions, and graph completion checking. Hard to test and reuse.

**Fix:** Delegate to `DelegationOrchestrator` service; listener just calls service methods.

---

### M10. OrgDispatchDueRitualsJob — Silent Invalid Cron Handling

**File:** `app/Jobs/Org/OrgDispatchDueRitualsJob.php`
**Lines:** 53–68

Catches `\Throwable` silently on invalid cron expressions, returning `false`. Broken schedules go unnoticed.

**Fix:** Log warnings for invalid cron expressions.

---

### M11. DelegationAttemptCompletedJob — Unsafe Status Mapping Default

**File:** `app/Jobs/DelegationAttemptCompletedJob.php`
**Lines:** 79–86

`mapRunStatusToAttemptStatus()` defaults to FAILED for unknown statuses. New run statuses silently map to failure.

**Fix:** Remove default case or log a warning.

---

### M12. MemoryEmbedding — Race Condition in createOrGetByContentHash()

**File:** `app/Models/MemoryEmbedding.php`
**Lines:** 156–177

Check-then-create pattern without locking. Concurrent requests can create duplicates.

**Fix:** Use `firstOrCreate()`.

---

### M13. MemoryEmbedding — Inefficient recordAccess()

**File:** `app/Models/MemoryEmbedding.php`
**Lines:** 110–114

Two separate database operations for a single update:
```php
$this->increment('access_count');
$this->update(['last_accessed_at' => now()]);
```

**Fix:** `$this->increment('access_count', 1, ['last_accessed_at' => now()]);`

---

### M14. ConnectorAccount — Multiple Individual Config Updates

**File:** `app/Models/ConnectorAccount.php`
**Lines:** 140–286

Setter methods (`setDmPolicy()`, `setGroupPolicy()`, etc.) each call `$this->update()` separately. Multiple database queries when setting multiple properties.

**Fix:** Batch updates into a single `updateConfiguration(array $updates)` method.

---

### M15. TrustScoreCalculator — Duplicate Query Logic

**File:** `app/Support/Delegation/TrustScoreCalculator.php`
**Lines:** 29–34, 130–141

`calculateForUser()` and `getMetrics()` both build similar `AgentJobRun` join queries.

**Fix:** Extract shared query builder method.

---

### M16. Inline Response Transformation Instead of JsonResources

**Files:** Multiple controllers (55+ instances)

Controllers manually map models to arrays instead of using JsonResource classes. Only 5 resource classes exist.

**Fix:** Create `OfficeStateResource`, `DelegationTaskResource`, `JobSummaryResource`, etc.

---

### M17. Inconsistent Authorization Implementation

29 of 64 controllers use authorization checks. No standardized approach across endpoints.

**Fix:** Ensure all endpoints modifying or retrieving user data include `$this->authorize()` checks.

---

### M18. FormRequest Authorization — Only Checks User Exists

**Files:**
- `app/Http/Requests/Org/StoreOrgAgentRequest.php:12-14`
- `app/Http/Requests/Org/UpdateOrgAgentRequest.php:12-14`
- `app/Http/Requests/Interrogation/SubmitAnswerRequest.php:10-12`

`authorize()` only checks `$this->user() !== null`, not actual resource-level authorization.

**Fix:** Add policy checks: `return $this->user()?->can('create', OrgAgentProfile::class);`

---

### M19. AgentInstallCommand — Static Boot Reconciliation State

**File:** `app/Console/Commands/AgentInstallCommand.php`
**Lines:** 19–28

`$bootReconciled` static property doesn't work across distributed supervisor processes.

**Fix:** Use Redis or database for boot reconciliation tracking.

---

### M20. ExecuteAgentRunJob — No Backoff Strategy

**File:** `app/Jobs/ExecuteAgentRunJob.php`
**Lines:** 43–45

`$tries = 1` and `$backoff = 0`. No resilience to transient failures.

**Fix:** Set `$tries = 2` with `$backoff = [60]`.

---

### M21. RecalculateTrustScoresJob — No Timeout or Error Handling

**File:** `app/Jobs/RecalculateTrustScoresJob.php`
**Lines:** 13–38

No timeout, tries, or `failed()` method. Chunking with external service calls can hang.

**Fix:** Add `public int $timeout = 600;`.

---

### M22. Job Dispatch Without Error Propagation

**File:** `app/Jobs/ExecuteAgentRunJob.php`
**Lines:** 621, 644, 647

Multiple jobs dispatched inside `finalizeTerminal()` without error handling. If dispatch fails, events don't fire.

**Fix:** Wrap in try-catch or use `Bus::chain()`.

---

### M23. Missing Route Name on Office State Endpoint

**File:** `routes/api.php`
**Line:** 140

Invokable controller route without a name.

**Fix:** Add `->name('office.state')`.

---

### M24. ChatSessionManager — Unsafe Deep Array Access

**File:** `app/Services/Messenger/ChatSessionManager.php`
**Lines:** 42–55

Uses `isset($payload['message']['reply_to_message']['message_id'])` instead of Laravel's `data_get()`.

**Fix:** `$messageId = data_get($payload, 'message.reply_to_message.message_id');`

---

### M25. Missing Foreign Key Constraint Strategy

**File:** `database/migrations/2026_03_06_100000_add_compaction_columns_to_chat_sessions_table.php`
**Lines:** 17–20

Foreign key uses `nullOnDelete()` for compaction boundary message. If message is deleted, boundary reference silently breaks.

**Fix:** Evaluate whether `cascadeOnDelete()` or application-level validation is more appropriate.

---

### M26. AgentJobRun scopeForUser — N+1 Risk

**File:** `app/Models/AgentJobRun.php`
**Lines:** 93–102

`$user->allTeams()->pluck('id')` loads teams in a separate query inside a scope.

**Fix:** Use `whereHas()` with nested relation queries.

---

### M27. Queue Priority Not Documented

**File:** `config/horizon.php`

No per-queue priority configuration or documentation. Critical jobs (runtime turns) could be starved by bulk jobs (trust score recalculation).

**Fix:** Document expected priority/SLA or use distinct worker pools.

---

### M28. Horizon supervisor-memory-formation Timeout Buffer

**File:** `config/horizon.php`
**Lines:** 283–296

Supervisor timeout matches job timeout (300s). No buffer — jobs at the limit risk being killed.

**Fix:** Set supervisor timeout to 360s (60s buffer).

---

## Low Issues

### L1. Validation Rule Duplication Between Store/Update Requests

**Files:** `StoreOrgAgentRequest.php` and `UpdateOrgAgentRequest.php` share 80% identical rules.

**Fix:** Extract shared rules into a base class.

---

### L2. ErrorEnvelope Usage Inconsistency

178 instances of `ErrorEnvelope::make()` bypassing Laravel's exception handling pipeline.

**Fix:** Consider using standard exception handling for consistency (stylistic).

---

### L3. Missing Resource Collection Classes

No dedicated `ResourceCollection` classes for paginated API responses.

**Fix:** Create collection resources for common paginated endpoints.

---

### L4. ChatMessage Constant Not Used in CompactionService

`CompactionService.php` uses magic string `'inbound'` instead of `ChatMessage::DIRECTION_INBOUND`.

**Fix:** Use the constant.

---

### L5. InterrogationSession — Hardcoded Status Array

`ACTIVE_STATUSES` array duplicates status constants manually.

**Fix:** `const ACTIVE_STATUSES = [self::STATUS_SETUP, self::STATUS_DISCOVERING, ...];`

---

### L6. ContextUsageEstimator — Precision Loss

Integer division loses precision on token estimation.

**Fix:** Use `(int) ceil($totalChars / $charsPerToken)`.

---

### L7. ProcessInboundMessage — `$maxExceptions` Not Standard

`public int $maxExceptions = 3` is not a standard Laravel queue property and has no effect.

**Fix:** Remove or replace with correct configuration.

---

### L8. Scramble Configuration Mixed into AppServiceProvider Boot

**File:** `app/Providers/AppServiceProvider.php:213-217`

**Fix:** Move to dedicated `ScrambleServiceProvider`.

---

### L9. DelegationTask — Missing Index on Self-Referential Pivot

**File:** `app/Models/DelegationTask.php:74-95`

Self-referential many-to-many pivot table may lack composite index.

**Fix:** Ensure migration has `$table->index(['task_id', 'depends_on_task_id']);`.

---

### L10. Missing Explicit Indexes on Some Foreign Keys

**File:** `database/migrations/2026_03_05_000001_create_runtime_sessions_table.php:14-34`

Some foreign keys lack explicit indexes (implicit index behavior varies by DB).

**Fix:** Add explicit indexes on all foreign key columns.

---

### L11. AiCriticCompletedJob — No Retry Configuration

**File:** `app/Jobs/AiCriticCompletedJob.php:27-40`

No explicit retry/backoff. Single transient failure causes permanent job failure.

**Fix:** Add `public int $tries = 2;` and `public int $backoff = 30;`.

---

### L12. ProcessRuntimeTurnJob — Unbounded Dynamic Timeout

**File:** `app/Jobs/Runtime/ProcessRuntimeTurnJob.php:31,40-41`

Timeout set from config without bounds checking.

**Fix:** `max(60, min(86400, (int) config(...)))`.

---

### L13. ResumeRuntimeTurnJob — Timeout Floor Missing

**File:** `app/Jobs/Runtime/ResumeRuntimeTurnJob.php:26-34`

If `$remainingTimeout` is 0 or negative, timeout becomes too short.

**Fix:** `$this->timeout = max(300, $remainingTimeout + 60);`.

---

### L14. SendOutboundMessage — Rate Limit Jitter Strategy

**File:** `app/Jobs/Messenger/SendOutboundMessage.php:224-227`

Fixed 5–10 second jitter on rate limiting. Can cause thundering herd.

**Fix:** Use exponential backoff or jitter proportional to attempt count.

---

### L15. DeliverWebhookJob — Missing Timeout

**File:** `app/Jobs/Agent/DeliverWebhookJob.php:14-36`

No timeout for HTTP delivery. Slow webhook endpoints hang workers.

**Fix:** Add `public int $timeout = 30;`.

---

### L16. SubAgentCompletionJob / RuntimeTurnCompletedJob — No Timeout

**Files:**
- `app/Jobs/Runtime/SubAgentCompletionJob.php:17-37`
- `app/Jobs/Runtime/RuntimeTurnCompletedJob.php:20-37`

**Fix:** Add `public int $timeout = 60;` and `public int $timeout = 120;` respectively.

---

### L17. DeliverRunWebhook Listener — No Error Handling on Dispatch

**File:** `app/Listeners/DeliverRunWebhook.php:10-52`

If job dispatch fails, webhook delivery is silently skipped.

**Fix:** Wrap dispatch in try-catch with logging.

---

### L18. MemoryFormationJob — Unvalidated backoff() Return

**File:** `app/Jobs/Memory/MemoryFormationJob.php:67-70`

`backoff()` returns config value without validation.

**Fix:** Add type checking with `array_filter(array_map('intval', ...), fn($v) => $v > 0)`.

---

### L19. RecalculateTrustScoresJob — Missing Idempotency Key

**File:** `app/Jobs/RecalculateTrustScoresJob.php:13-38`

Could be dispatched multiple times without deduplication.

**Fix:** Add cached "last_run_at" check.

---

### L20. Horizon supervisor-memory-working — Ambiguous tries=0

**File:** `config/horizon.php:269-282`

`tries => 0` is ambiguous. Jobs with their own `$tries` property override this.

**Fix:** Use `tries => 1` or document why 0.

---

### L21. AgentDispatchDueCommand — Silent Reconciliation Error

**File:** `app/Console/Commands/AgentDispatchDueCommand.php:20-24`

Reconciliation error caught and warned, then continues. Could lead to cascading issues.

**Fix:** Provide option to fail on reconciliation error.

---

### L22. Missing Route Names on Several Endpoints

Various API routes lack `->name()` for consistency and testability.

**Fix:** Add route names systematically.

---

## Positive Patterns Observed

1. **Good service layer separation** — most business logic lives in services, not controllers
2. **Proper use of scopes** — models have well-defined, reusable query scopes
3. **DTOs in use** — StarMetrics, TrustScore, and other DTOs maintain clean contracts
4. **Migration reversibility** — all migrations have proper `down()` methods
5. **Casts properly defined** — JSON and datetime fields use Laravel casts
6. **Observer pattern** — ChatMessageObserver used appropriately
7. **Good DI patterns** — services are properly injected via constructors
8. **FormRequests where used** — existing FormRequests show excellent `withValidator()` patterns
9. **Good authorization in Org module** — proper `$this->authorize()` usage with policies

---

## Priority Recommendations

### Immediate (Before Deployment)
1. Add authentication to N8n webhook endpoint (C4)
2. Fix N+1 queries in ChatSessionManager and CompactionService (C5, C6)
3. Add `failed()` method to ExecuteAgentRunJob (H4)
4. Replace `$guarded = []` with explicit `$fillable` on all models (H3)
5. Fix default password in Neo4jGraphStore (H10)

### Near-Term (Next Sprint)
6. Extract InterrogationSessionController into services (C1)
7. Extract OfficeStateController into service (C2)
8. Move .env file I/O out of ConfigurationController (C3)
9. Create FormRequests for 40+ inline validation instances (H1)
10. Add timeouts to all jobs (M4)
11. Fix race condition in DelegationRecoveryHandler (M8)

### Medium-Term (Next 2–3 Sprints)
12. Refactor AppServiceProvider into domain providers (M1)
13. Add idempotency checks to state-changing jobs (H6)
14. Create JsonResource classes for API responses (M16)
15. Standardize authorization across all controllers (M17)
16. Configure Horizon worker recycling (M6)

### Ongoing Maintenance
17. Migrate deprecated accessor/mutator pattern (H12)
18. Standardize backoff strategies across jobs (M5, M7)
19. Add explicit indexes where missing (L9, L10)
20. Create resource collection classes (L3)
