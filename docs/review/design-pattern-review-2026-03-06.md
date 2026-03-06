# Design Pattern Review

**Date:** 2026-03-06
**Scope:** Full codebase (`app/`) - Services, Jobs, Commands, Controllers, Support, Models, Config, Routes, Middleware, Providers, Listeners
**Focus Areas:** SOLID, Laravel Best Practices, DRY, Design Patterns, Code Quality, Bugs, Security
**Graph:** SOLID Analysis | Task ID: 93 | Attempt: 1

---

## Executive Summary

**Overall Score: 59/100**

The codebase (720 PHP files, ~104k LOC in `app/`) demonstrates a **maturing event-driven architecture** with strong domain layering (Agent, Delegation, Org, Memory, Compliance, Messenger, Runtime). Several areas show excellent pattern usage -- tool adapters via Strategy pattern, compliance policy via interface contracts, delegation coordination via event subscribers, atomic state transitions. However, critical issues exist in god objects, DRY violations, mixed dependency injection patterns, and an oversized AppServiceProvider.

### SOLID Scorecard

| Principle | Score | Key Issue |
|-----------|-------|-----------|
| **S** -- Single Responsibility | 45/100 | 8+ God Classes; `InterrogationSessionController` (4124 LOC), `ExecuteAgentRunJob` (987 LOC), `SessionProcessManager` (727 LOC), `RunEventWriter` (1000 LOC) |
| **O** -- Open/Closed | 65/100 | Hardcoded registries (`CommandRouter`, `TaskManagementProviderManager`); strong adapter patterns offset by magic strings |
| **L** -- Liskov Substitution | 85/100 | Strong contract compliance across adapter hierarchies (`ToolAdapterInterface`, `ConnectorAdapterInterface`, `InterrogationRunnerAdapter`) |
| **I** -- Interface Segregation | 72/100 | Only 4 interfaces in `app/Contracts/`; many services lack abstractions; `TaskManagementProviderDriver` has 10 methods |
| **D** -- Dependency Inversion | 50/100 | Mixed DI patterns (constructor injection, `app()`, `new`); 29+ direct `app(Foo::class)` calls; most Support classes lack interface bindings |

### Risk Heatmap

| Component | Risk | LOC | Concerns |
|-----------|------|-----|----------|
| `InterrogationSessionController` | CRITICAL | 4124 | 20+ actions, monolithic lifecycle controller |
| `ExecuteAgentRunJob` | CRITICAL | 987 | 13 responsibilities, mixed DI, process lifecycle + compliance + retry + billing |
| `SessionProcessManager` | CRITICAL | 727 | Static mutable state, `readTurnResponse`/`resumeReadTurnResponse` ~95% duplicated |
| `RunEventWriter` | HIGH | 1000 | Event writing, redaction, pattern detection, broadcasting, escalation |
| `ExecuteInterrogationRoundJob` | HIGH | 1427 | Largest job file |
| `AppServiceProvider` | HIGH | 231 | 14 singletons, 7 tool registrations, 9 policies, 3 rate limiters, 4 subscribers, API config |
| `AgentInstallCommand` | HIGH | 1171 | Multi-tool installer with 8+ responsibilities |

### Category Scores

| Category | Score | Key Issues |
|----------|-------|------------|
| SOLID Adherence | 5/10 | SRP violations in 8+ critical files |
| Design Patterns | 7/10 | Good Strategy/Adapter/Observer; missing generic Repository, State Machine |
| DRY | 4/10 | 5 state transition services (3 nearly identical), turn response handling duplicated, rate limiter closures repeated |
| Error Handling | 6/10 | Good `ErrorEnvelope` pattern (185 usages across 29 files); some swallowed Throwables |
| Laravel Best Practices | 7/10 | Good Form Requests, Policies, Events; `app()` calls should be constructor DI |
| Code Quality | 5/10 | 12 files over 800 LOC; methods exceeding 150 lines |
| Security | 5/10 | Strong guards (`EnvPolicy`, `DatabaseDestructionGuard`); but `ConfigurationController` has no auth gate on `.env` writes, 55/73 models use `$guarded = []`, 4 controllers missing authorization |

---

## 1. SOLID Principle Violations

### 1.1 Single Responsibility Principle (SRP)

#### CRITICAL: God Objects

| File | Lines | Responsibilities | Priority |
|------|-------|-----------------|----------|
| `app/Http/Controllers/Api/V1/InterrogationSessionController.php` | 4124 | 20+ actions: CRUD, state transitions, build management, event streaming, annotations, exports, plan revision, task syncing | CRITICAL |
| `app/Jobs/ExecuteAgentRunJob.php` | 987 | Validation, backup, memory context injection, STAR preamble, env merging, process lifecycle, output streaming, reasoning parsing, metadata management, error classification, compliance evaluation, targeted retry, cost accounting, billing | CRITICAL |
| `app/Services/Runtime/SessionProcessManager.php` | 727 | Process lifecycle, stdin/stdout I/O, fragment extraction, stream parsing, progress persistence, turn yielding/resuming, session caching | CRITICAL |
| `app/Support/Agent/RunEventWriter.php` | 1000 | Event writing/chunking, PII redaction, pattern detection, MCP endpoint extraction, office broadcasting, escalation handling | HIGH |
| `app/Console/Commands/AgentInstallCommand.php` | 1171 | License validation, preflight checks, migrations, user creation, connector config, ingress setup, health checks | HIGH |
| `app/Providers/AppServiceProvider.php` | 231 | 14 singletons + 7 tool registrations + 9 Gate policies + 4 event subscribers + 3 rate limiters + API config | HIGH |
| `app/Http/Controllers/Api/V1/OfficeStateController.php` | 467 | 8 data aggregation methods with direct DB queries, string-based activity inference, metadata parsing -- all business logic in a controller | HIGH |
| `app/Http/Controllers/Api/V1/AgentRunController.php` | 552 | `stop()` at 186 lines mixes state transitions, process killing, metadata manipulation, audit logging | MEDIUM |

**Recommended Decompositions:**

`ExecuteAgentRunJob` -> Pipeline pattern:
```
PreRunValidationStep -> ComplianceCheckStep -> MemoryInjectionStep
-> StarPreambleStep -> ProcessExecutionStep -> PostExecutionStep
```

`SessionProcessManager` -> Extract:
- `StreamFragmentProcessor` - fragment parsing + text extraction
- `TurnYieldManager` - yield/resume logic (eliminates the massive duplication)

`AppServiceProvider` -> Split into:
- `ComplianceServiceProvider` - OrchestrationPolicyService, ComplexityClassifier, etc.
- `RuntimeServiceProvider` - ToolGateway + adapter registrations
- `MessengerServiceProvider` (already partially exists for connector setup)
- Keep AppServiceProvider for Gate policies and rate limiters only

### 1.2 Open/Closed Principle (OCP)

#### Hardcoded Registries

**`CommandRouter`** (`app/Services/Messenger/CommandRouter.php:52-70`):
```php
private array $handlers = [
    'jobs' => JobsCommandHandler::class,
    'runs' => RunsCommandHandler::class,
    // ... 17 hardcoded entries
];
```
Adding a command requires modifying the router. Should use tagged container bindings or auto-discovery.

**`TaskManagementProviderManager`** (`app/Support/TaskProviders/TaskManagementProviderManager.php:13`):
```php
return match (strtolower(trim($driver))) {
    'linear' => app(LinearTaskManagementProvider::class),
    default => throw new InvalidArgumentException(...)
};
```
Extends only by modifying the match statement. Should use Laravel's `Manager` pattern with `extend()`.

#### Good OCP Usage

- `ToolGateway` (`app/Services/Runtime/ToolGateway.php:33-36`): Open for extension via `register(ToolAdapterInterface)` - new tools can be added without modifying the gateway.
- `ConnectorManager` (`app/Support/Messenger/ConnectorManager.php`): Open via `register(provider, adapterClass)`.
- `OrchestrationPolicyService` implementing `OrchestrationPolicyServiceContract`: Swappable via container binding.

### 1.3 Liskov Substitution Principle (LSP)

**Strong compliance.** All adapter hierarchies honor their contracts:
- `ToolAdapterInterface` with 7 registered adapters (Fs, Runtime, Web, Browser, Discovery, AgentApi, Mcp)
- `ConnectorAdapterInterface` for messenger providers
- `InterrogationRunnerAdapter` with Claude/Codex implementations
- `TaskManagementProviderDriver` with Linear implementation

### 1.4 Interface Segregation Principle (ISP)

**19 interfaces** across the codebase, but scattered inconsistently:

- `app/Contracts/` (4): `OrchestrationPolicyServiceContract`, `ToolAdapterInterface`, `ConnectorAdapterInterface`, `SlashCommandHandlerInterface`
- `app/Messenger/` (3): `GatewayWorkerInterface`, `ChatActionHandlerInterface`, `StreamableHandlerInterface`
- `app/Support/` (7): `AnalyzerInterface`, `InterrogationRunnerAdapter`, `TaskManagementProviderDriver`, `ExtractionProvider`, `EmbeddingProvider`, `SynthesisStrategyInterface`, plus 4 documentation interfaces
- `app/Support/TaskProviders/Contracts/` (1): `TaskManagementProviderDriver` (10 methods -- could be split into `AuthorizationDriver` and `ProjectManagementDriver`)

**Gap:** Interface location is inconsistent -- some in `app/Contracts/`, some co-located with implementations, some in domain `Contracts/` subdirectories. No single convention. Most Support classes (state transition services, builders, validators) have no interfaces, making them hard to mock in tests and tightly coupled.

### 1.5 Dependency Inversion Principle (DIP)

**Mixed patterns** observed:

Good - Constructor injection:
```php
// ToolGateway
public function __construct(
    private PolicyEngine $policyEngine,
    private ApprovalGate $approvalGate,
    private AuditLogger $auditLogger,
) {}
```

Bad - Service location via `app()`:
```php
// ExecuteAgentRunJob:151
$backupResult = app(PreRunDatabaseBackup::class)->backup($run);
// ExecuteAgentRunJob:156
if (app(FeatureFlagManager::class)->enabled(...))
// ExecuteAgentRunJob:158
$contextBuilder = app(MemoryContextBuilder::class);
```

These should be injected via the `handle()` method signature (Laravel auto-resolves job dependencies).

**29+ `app(Foo::class)` calls** across the codebase, with 11 in `ExecuteAgentRunJob` alone.

---

## 2. DRY Violations

### 2.1 CRITICAL: State Transition Services (5 near-identical classes)

| Class | Namespace | Model | Extra Methods |
|-------|-----------|-------|---------------|
| `RunStateTransitionService` | `App\Support\Agent` | `AgentJobRun` | None |
| `GraphStateTransitionService` | `App\Support\Delegation` | `DelegationGraph` | None |
| `TaskStateTransitionService` | `App\Support\Delegation` | `DelegationTask` | None |
| `SessionStateTransitionService` | `App\Support\Interrogation` | `InterrogationSession` | `transitionPhase()` |
| `SessionStateTransitionService` | `App\Support\RepoAnalysis` | `RepoAnalysisSession` | `pause()`, `resume()`, `retry()` + state matrix |

The first three are **byte-for-byte identical** except for the model class. These should use a generic trait or base class:

```php
trait AtomicStateTransition
{
    abstract protected function modelClass(): string;

    public function transition(int $id, array $fromStatuses, string $toStatus, array $attributes = []): bool
    {
        if ($fromStatuses === []) return false;
        $payload = array_merge($attributes, [
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);
        return $this->modelClass()::query()
            ->whereKey($id)->whereIn('status', $fromStatuses)
            ->update($payload) === 1;
    }
}
```

### 2.2 HIGH: SessionProcessManager Turn Response Duplication

`readTurnResponse()` (lines 233-368) and `resumeReadTurnResponse()` (lines 573-726) are ~95% identical -- 135-line and 153-line methods with the same loop structure, fragment collection, heartbeat logic, progress persistence, yield checks, and process status handling. The only difference is that `resumeReadTurnResponse` loads buffered fragments from cache first.

**Fix:** Extract a `processTurnLoop()` method that accepts initial fragments and start time.

### 2.3 HIGH: Stream Parsing Duplication (CliRuntimeExecutor + SessionProcessManager)

`CliRuntimeExecutor::extractFinalText()` (lines 181-239) and `SessionProcessManager` (lines 370-413) implement identical JSON stream parsing and text block extraction logic (~60 lines). Additionally, `unwrapStreamEvent()` is defined identically in both classes (CliRuntimeExecutor:283-290, SessionProcessManager:419-426).

**Fix:** Extract a shared `StreamEventParser` service used by both classes.

### 2.4 MEDIUM: Rate Limiter Closure Duplication

`AppServiceProvider.php` defines 4 rate limiters (`agent-mutations`, `interrogation`, `memory-reads`, `memory-writes`) with nearly identical `ErrorEnvelope::make('RATE_LIMITED', ...)` response closures. Three use the same `by()` key pattern.

**Fix:** Extract a helper:
```php
private function rateLimiter(int $perMinute, string $message): Limit { ... }
```

### 2.5 MEDIUM: Build Job Detection Logic

`isBuildJob()` logic appears in `AgentJobController.php:645-655` using `str_starts_with($name, 'Interrogation Build S')` and repeated in index filtering (lines 62-75). The magic string `'Interrogation Build S'` is a fragile coupling between `BuildTaskRunFactory` naming and controller logic.

**Fix:** Move to `AgentJob` model as a computed property or scope:
```php
public function isBuildJob(): bool { ... }
public function scopeBuildJobs(Builder $query): Builder { ... }
```

### 2.6 MEDIUM: Metadata Array Merge Pattern

The pattern `(array) ($run->metadata_json ?? [])` appears 37+ times across 15 files. Consider an accessor on `AgentJobRun`:
```php
public function getMetadataAttribute(): array { return $this->metadata_json ?? []; }
public function mergeMetadata(array $patch): void { ... }
```

### 2.7 HIGH: Runtime Turn Job Duplication

`ProcessRuntimeTurnJob` and `ResumeRuntimeTurnJob` share ~75 lines of identical progress callback building (lines 177-212 / 95-130). Additionally, `ProcessRuntimeTurnJob` (lines 120-170) and `RuntimeTurnCompletedJob` (lines 60-97) duplicate both the completion message sending and error message sending logic (~80 lines total).

**Fix:** Extract `RuntimeTurnResponseHandler` for message sending and `RuntimeProgressCallbackBuilder` for callback construction.

### 2.8 MEDIUM: Memory Job Feature Flag Double-Check

Five memory jobs (`MemoryFormationJob`, `RuntimeMemoryFormationJob`, `MemoryPruneJob`, `MemoryConsolidationJob`, `MemoryWorkingBufferJob`) all check the `MEMORY_ENABLED` feature flag in both `shouldQueue()` and `handle()` -- redundant guards totalling ~50 lines of duplicated code. Extract a `MemoryFeatureGuard` trait or base job.

### 2.9 MEDIUM: Memory Job Backoff Arrays

`MemoryPruneJob` and `MemoryConsolidationJob` define identical `$tries = 3` and `backoff(): [30, 60, 120]` (lines 32, 57-60 in both). No shared constant or trait.

### 2.10 MEDIUM: JSON Response Parsing in Memory Adapters

`OpenAIAdapter::parseJsonArray()` (lines 326-361) and `AnthropicAdapter::parseJsonArray()` (lines 265-300) implement identical 35-line JSON response parsing logic. Extract to a shared `JsonResponseParser` trait or base class.

---

## 3. Design Pattern Analysis

### 3.1 Patterns Used Well

| Pattern | Implementation | Quality |
|---------|---------------|---------|
| **Strategy** | `ToolAdapterInterface` + 7 adapters, `InterrogationRunnerAdapter` + Claude/Codex | Excellent |
| **Registry** | `ToolGateway.register()`, `ConnectorManager.register()` | Good - open for extension |
| **Observer/Subscriber** | `DelegationCoordinator`, `DelegationRecoveryHandler`, `DelegationBroadcastSubscriber`, `DocumentationTelemetrySubscriber` | Excellent - clean event-driven coordination |
| **State Machine** | Atomic state transitions via WHERE-guarded UPDATE queries | Good concept, but duplicated 5x |
| **DTO** | `app/DTOs/` namespace with 14 focused DTOs: `ToolResult`, `RuntimeContext`, `NormalizedMessage`, `CommandResult`, etc. | Good |
| **Value Object** | `WorkflowKey`, `Duration`, `ErrorEnvelope` | Good |
| **Guard/Policy** | `PlanPayloadGuard`, `QuestionPayloadGuard`, `EnvPolicy`, `DatabaseDestructionGuard` | Excellent defensive patterns |
| **Template Method** | `AbstractToolAdapter` base for tool adapters | Good |
| **Chain of Responsibility** | `Bus::chain()` in `AttemptSpawner` for delegation execution | Appropriate |

### 3.2 Missing/Underused Patterns

| Pattern | Where Needed | Impact |
|---------|-------------|--------|
| **Pipeline** | `ExecuteAgentRunJob` -- validation, compliance, memory, STAR, execution, post-processing are natural pipeline stages | HIGH |
| **Generic State Machine** | Replace 5 duplicated state transition services with a parameterized base | HIGH |
| **API Resource** | Controllers return hand-built arrays (`transformJob()`, etc.) instead of Laravel API Resources; only 3 Resources exist (`ChatSessionResource`, `ChatMessageResource`, `ChatActionResource`) | MEDIUM |
| **Repository** | Only 2 repository classes (`WorkflowReliabilityCurrentRepository`, `ActiveBuildScopedRepository`); most data access is inline in controllers/jobs | MEDIUM |
| **Strategy (Search)** | `HybridRetriever` (327 LOC) tightly couples 3 search strategies (semantic, keyword, graph) + RRF fusion; extract `SearchStrategy` interface | MEDIUM |
| **Service Provider decomposition** | `AppServiceProvider` handles too many concerns; should be split by domain | MEDIUM |

### 3.3 Anti-Patterns Identified

#### Magic String Dependencies

`AgentJobRun` uses string constants for status values:
```php
const STATUS_QUEUED = 'queued';
const STATUS_STARTING = 'starting';
// ... 7 more
```

While `Runtime/` enums use PHP 8.1 backed enums properly (`RuntimeToolCallStatus`, `RuntimeMode`, etc.). The codebase has **partial enum adoption** -- 16 proper enums in `app/Enums/` but 100+ string constants on models.

#### Static Mutable State

`SessionProcessManager` uses `private static array $activeProcesses = []` to track running wrapper processes. This:
- Prevents test isolation (state leaks between tests)
- Couples the class to the specific worker process (documented in class docblock, but still fragile)
- Makes the class impossible to mock for the process part

#### Container Service Locator

`ChatActionExecutor` (`app/Services/Messenger/ChatActionExecutor.php:52-55`) injects the entire IoC container:
```php
public function __construct(
    private Container $container,
    private ChatActionPolicyValidator $policyValidator,
) {}
```
Then uses `$this->container->make()` to resolve handlers (line 244). This hides dependencies, making the class's actual requirements invisible and harder to test. Should use a dedicated handler factory.

#### Large Transaction with Side Effects

`WorkflowBudgetEnforcer::recordRunCost()` (`app/Services/Cost/WorkflowBudgetEnforcer.php:45-259`) wraps a 215-line block in `DB::transaction()` containing multiple service calls with side effects (incident lifecycle, governance, gate transitions). If the transaction rolls back, the side effects may have already been dispatched. Transactions should be minimal; orchestration should use events dispatched after commit.

#### Transient Object Mutation

`ExecuteAgentRunJob:163-165`:
```php
$originalTaskPath = $run->job->task_markdown_path;
$run->job->task_markdown_path = $contextPath;
$memoryContextApplied = true;
```
Mutates the job model in memory without persisting, then relies on the mutated value downstream. This is fragile and hard to debug.

#### metadata_json as Untyped Grab-Bag

`ExecuteAgentRunJob` stores 8+ unrelated concerns in `metadata_json`: rate limit state, permission blockers, approval status, clarification flags, reasoning summaries, failure mode hints, termination mode, task category/file counts. No typed structure enforces consistency. A single JSON column is used as a catch-all for what should be distinct, typed fields or a dedicated state object.

#### Missing Idempotency Guards in Jobs

Several jobs that mutate state lack idempotency checks:
- `MemoryFormationJob` (lines 106-127): Doesn't check if formation already completed
- `OrgExecuteRitualJob` (line 42): Creates a new ritual run without checking for duplicates
- `AiCriticCompletedJob` (lines 81-85): Updates verification result without version check

If these jobs are retried (after timeout or transient failure), they may produce duplicate records or overwrite valid state.

#### Missing `failed()` Handlers

7 jobs lack a `failed()` method: `CompactionJob`, `DeliverWebhookJob`, `RecalculateTrustScoresJob`, `ReindexDocumentationSearchJob`, `DelegationAttemptCompletedJob`, `AiCriticCompletedJob`, `OrgDispatchDueRitualsJob`. When these jobs exhaust retries, failures are only captured by Laravel's generic handler -- no domain-specific cleanup or alerting occurs.

#### Magic Numbers in Critical Classes

`RunEventWriter.php` uses undocumented magic numbers: `5_000_000` bytes (line 16), `4096` chunk size (line 18), `8192` max payload (line 20), `5` consecutive failures threshold (line 225), `10` recent failures window. `HybridRetriever.php` uses `k=60` for RRF fusion (line 63), `default_limit=10`, `max_limit=50`. These should be named constants with rationale comments.

#### Feature Envy: Provider-Specific Knowledge in Generic Services

`ChatSessionManager` (`app/Services/Messenger/ChatSessionManager.php:42-55`) knows about Slack `thread_ts` and Telegram `reply_to_message` payload structures. This provider-specific logic should live in the respective connector adapters, not in a generic session manager.

---

## 4. Code Quality Issues

### 4.1 Oversized Files (> 800 LOC)

| File | LOC | Recommendation |
|------|-----|----------------|
| `InterrogationSessionController.php` | 4124 | Split into 4-5 focused controllers |
| `ExecuteInterrogationRoundJob.php` | 1427 | Extract validation, prompting, response processing |
| `ReportComposer.php` | 1196 | Extract section generators |
| `CodexAdapter.php` | 1183 | Review for extraction opportunities |
| `AgentInstallCommand.php` | 1171 | Extract step classes (preflight, migration, connector setup) |
| `ExecuteInterrogationBuildJob.php` | 1140 | Pipeline extraction |
| `ClaudeAdapter.php` | 1126 | Review for shared base class with CodexAdapter |
| `RepoAnalysisSessionController.php` | 1118 | Split by sub-resource |
| `ExecuteInterrogationPlanJob.php` | 1078 | Pipeline extraction |
| `DiscordAdapter.php` | 1039 | Complex but likely inherent to Discord API surface |
| `RunEventWriter.php` | 1000 | Extract concerns per SRP recommendation |
| `ExecuteAgentRunJob.php` | 987 | Pipeline pattern |

### 4.2 `InterrogationSessionController` Deep Dive

At 4124 lines, this is the single largest concern. It handles:
1. CRUD operations (index, store, show, update, destroy, restore)
2. State transitions (advance, revert)
3. Build management (tasks, build dispatch, retry)
4. Event streaming (SSE endpoints)
5. Annotations (update, approve)
6. Export (various formats)
7. Plan management (revision, acceptance)
8. Task provider sync
9. Discovery phase management
10. Summary generation

**Recommendation:** Split into focused controllers:
- `InterrogationSessionController` -- CRUD only
- `InterrogationLifecycleController` -- state transitions, advance, revert
- `InterrogationBuildController` -- build task management
- `InterrogationExportController` -- exports
- `InterrogationAnnotationController` -- annotations

### 4.3 AgentJobController Quality

At 688 lines, `AgentJobController` is moderately sized but well-structured:
- Uses Form Requests (`StoreAgentJobRequest`, `UpdateAgentJobRequest`) -- good
- Uses `AuditLogger` for change tracking -- excellent
- Uses `ErrorEnvelope` for consistent error responses -- good
- Has `transformJob()` private method (should be an API Resource)
- `runNow()` at 95 lines is the most complex method -- could extract to a service

---

## 5. Security Observations

### 5.1 Good Practices
- `EnvPolicy` validates environment overrides at execution time (defense-in-depth)
- `DatabaseDestructionGuard` prevents accidental data loss in console
- `ReplayProtection` middleware for messenger webhooks
- `IdempotencyKeyGenerator` for message deduplication
- `PlanPayloadGuard`/`QuestionPayloadGuard` for input validation
- Rate limiters on all API groups
- `AuditLogger` provides comprehensive audit trail

### 5.2 Concerns

**Mass Assignment: 55 of 73 models use `$guarded = []`**

Only 18 models (Org/*, Runtime/*, User, Team) use explicit `$fillable`. The remaining 55 models use `$guarded = []`, meaning every column is mass-assignable. While internal-facing, this removes a critical safety net for:
- `CredentialVault` -- credential storage model should restrict mass-assignment
- `AgentAuditLog` -- audit logs should be append-only, not mass-updatable
- `ConnectorAccount` -- contains OAuth tokens and webhook secrets

The inconsistency itself is also a code quality issue -- the Org and Runtime subdomains follow `$fillable` while the rest use `$guarded = []`.

**LIKE queries with user input** (7 controllers, 14 occurrences):
```php
$builder->where('name', 'like', "%{$q}%")
    ->orWhere('description', 'like', "%{$q}%");
```
While not SQL injection (parameterized by Eloquent), the `%` and `_` characters in `$q` are not escaped, allowing users to craft expensive wildcard patterns. Found in: `AgentJobController`, `InterrogationSessionController`, `DelegationGraphController`, `DocsPageController`, `DelegateeProfileController`, `AuditLogController`.

**`env()` usage outside config files** (`AgentRestartCommand.php:123-124`):
```php
$includeDevServices = config('agent_restart.include_npm_dev', env('AGENT_RESTART_NPM_DEV', false));
```
Using `env()` outside config files fails when config is cached (`php artisan config:cache`). Found in `AgentRestartCommand` (2 occurrences), `AgentInstallCommand` (12 occurrences), `AgentBackupDatabaseCommand` (1 occurrence). The install command is an edge case (runs before config exists), but the restart command should only use `config()`.

**Broad exception catching** (13 occurrences):
```php
} catch (\Exception $e) {
```
Found in messenger adapters, gateway workers, credential validators, and form requests. While appropriate in gateway workers (resilience), the messenger adapters and validators should catch specific exception types to avoid swallowing unexpected errors.

**License middleware missing on Org/Delegation routes** (`routes/api.php:321, 379`):
Authenticated API routes at line 75 apply `['auth:sanctum', 'license']`, but the Org routes (line 321) and Delegation routes (line 379) only apply feature-flag middleware, skipping the `'license'` check. Users without a valid license can access these entire feature sets. This is a licensing enforcement bypass.

**Missing authorization on sensitive controllers:**
- `ConfigurationController` (lines 54-96): Any authenticated user can update `.env` via `file_put_contents()` with no authorization gate
- `DebugPanelController` (lines 17-73): Exposes Redis queue sizes, database driver, debug mode to all authenticated users
- `DeadLetterController`: No authorization on `retry()` or `destroy()` -- assumes user owns connector
- `PairingController` (lines 47-75): No authorization on `approve()` or `revoke()`

**`ConfigurationController` writes `.env` directly** (`ConfigurationController.php:114-138`):
```php
file_put_contents(base_path('.env'), $contents);
```
Environment file manipulation via regex without proper escaping. Combined with the missing authorization gate above, this is the highest-severity security finding.

**Inconsistent error response formats** (3+ formats across 70+ controllers):
- Standard: `ErrorEnvelope::make()` → `{error: {code, message, details}}`
- Raw: `response()->json(['error' => '...'], 500)` (ChatSessionController)
- Nested: `response()->json(['error' => ['message' => ...]])` (CredentialsController)
- Clients must handle multiple formats. Standardize on `ErrorEnvelope`.

**`file_get_contents` on user-provided path** (`AgentJobController.php:680`):
```php
$content = @file_get_contents($path);
```
The `readTaskMarkdownContent()` method reads from `$job->task_markdown_path` which originates from user input. While likely validated elsewhere via `allowed_task_markdown_bases`, this is a defense-in-depth gap.

---

## 6. Laravel Best Practices

### 6.1 Strengths
- **Form Requests** used consistently for validation (`StoreAgentJobRequest`, `UpdateAgentJobRequest`, etc.)
- **Policies** for authorization (9 policies registered in AppServiceProvider)
- **Events & Listeners** for cross-cutting concerns (delegation, broadcasting)
- **DTOs** for data transfer (14 in `app/DTOs/`)
- **Enums** partially adopted (16 backed enums in `app/Enums/`)
- **Queue configuration** with Horizon supervisors per domain

### 6.2 Improvements Needed

**API Resources:** Only 3 API Resources exist. The remaining 66+ controllers return hand-built arrays via private `transform*()` methods. This:
- Duplicates serialization logic
- Makes response format changes tedious
- Misses conditional relationship loading optimization

**Service Providers:** `AppServiceProvider` is a monolith. Laravel encourages domain-specific providers.

**Model Constants vs Enums:** Models like `AgentJobRun` define status values as string constants:
```php
const STATUS_QUEUED = 'queued';
const STATUS_STARTING = 'starting';
```
While `app/Enums/Runtime/` shows proper enum usage. Inconsistent approach across the codebase.

---

## 7. Positive Patterns Worth Preserving

1. **Atomic State Transitions** -- Using WHERE-guarded UPDATE queries to prevent race conditions is excellent. The pattern just needs DRY extraction.

2. **ErrorEnvelope** -- Consistent error response format used 185 times across 29 files. Provides structured error codes, messages, and validation details.

3. **ToolGateway Pipeline** -- Clean chain: adapter lookup -> policy check -> authorization -> approval gate -> execution -> audit. Well-separated concerns within the gateway.

4. **Event-Driven Delegation** -- `DelegationCoordinator` + `DelegationRecoveryHandler` + `DelegationBroadcastSubscriber` as separate subscribers is clean separation.

5. **Guard Classes** -- `PlanPayloadGuard`, `QuestionPayloadGuard`, `EnvPolicy`, `DatabaseDestructionGuard`, `InterrogationBuildCommandGuard` -- defensive validation at system boundaries.

6. **Compliance Architecture** -- `OrchestrationPolicyServiceContract` with advisory/strict modes and gate-based evaluation is well-designed and extensible.

---

## 8. Prioritized Recommendations

### Tier 1: Critical (Address within 2 sprints)

| # | Finding | Action | Files Affected |
|---|---------|--------|----------------|
| 1 | `ConfigurationController` writes `.env` with no authorization | Add admin-only gate; inject `EnvManager` service instead of raw `file_put_contents` | 1 controller |
| 2 | License middleware missing on Org/Delegation routes | Add `'license'` middleware to `routes/api.php` org (line 321) and delegation (line 379) route groups | routes/api.php |
| 3 | `InterrogationSessionController` god class | Split into 5 focused controllers | 1 + routes |
| 4 | 5 duplicated state transition services | Extract generic `AtomicStateTransition` trait | 5 services |
| 5 | `SessionProcessManager` turn response duplication | Extract shared `processTurnLoop()` method | 1 file, ~150 LOC saved |
| 6 | `ExecuteAgentRunJob` 13 responsibilities | Refactor to pipeline/step pattern | 1 file -> 5-6 classes |
| 7 | `AppServiceProvider` monolith | Split into domain-specific providers | 1 -> 3-4 providers |

### Tier 2: High (Address within 4 sprints)

| # | Finding | Action | Files Affected |
|---|---------|--------|----------------|
| 8 | Missing authorization on `DebugPanelController`, `DeadLetterController`, `PairingController` | Add authorization gates/policies | 3 controllers |
| 9 | 55 models use `$guarded = []` | Add explicit `$fillable` to security-sensitive models (`CredentialVault`, `AgentAuditLog`, `ConnectorAccount`) at minimum; standardize on `$fillable` project-wide | 55 models |
| 10 | 29+ `app()` service location calls | Replace with constructor/method injection | ~20 files |
| 11 | `OfficeStateController` fat controller (467 LOC) | Extract to `OfficeStateService` with injected repositories | 1 controller |
| 12 | Missing API Resources + inconsistent error formats | Create resources and standardize all errors on `ErrorEnvelope` | ~15 controllers |
| 13 | String constants vs enums inconsistency | Migrate model constants to backed enums | ~10 models |

### Tier 3: Medium (Address opportunistically)

| # | Finding | Action | Files Affected |
|---|---------|--------|----------------|
| 14 | Stream parsing duplication (CliRuntimeExecutor + SessionProcessManager) | Extract shared `StreamEventParser` service | 2 files, ~120 LOC saved |
| 15 | Container service locator in `ChatActionExecutor` | Replace with handler factory | 1 file |
| 16 | `RunEventWriter` mixed concerns | Extract redactor, detector, broadcaster | 1 -> 4 classes |
| 17 | `VerifyWebhookSignature` middleware does business logic (63-line account resolution) | Extract `ConnectorAccountResolver` service | 1 middleware |
| 18 | `metadata_json` array merge pattern | Add model accessors/methods | ~15 files |
| 19 | Transient model mutation in jobs | Use explicit parameter passing | 2-3 files |
| 20 | LIKE query wildcard escaping | Add `escapeLikeParameter()` helper | 7 controllers |
| 21 | `TaskManagementProviderManager` closed | Use Laravel Manager pattern with `extend()` | 1 file |
| 22 | `env()` calls outside config files | Move to config values (except AgentInstallCommand) | 2 commands |
| 23 | Broad `catch (\Exception)` | Narrow to specific exception types | 10 files |
| 24 | Interface location inconsistency | Standardize on `app/Contracts/{Domain}/` or co-location | 19 interfaces |
| 25 | `WorkflowBudgetEnforcer` large transaction with side effects | Use events dispatched after commit | 1 file |
| 26 | Provider-specific knowledge in `ChatSessionManager` | Move to connector adapters | 1 file |
| 27 | Runtime turn job duplication (message sending + progress callbacks) | Extract `RuntimeTurnResponseHandler` and `RuntimeProgressCallbackBuilder` | 3 files, ~155 LOC saved |
| 28 | Memory job feature flag double-check | Extract `MemoryFeatureGuard` trait | 5 jobs |
| 29 | Missing `failed()` handlers | Add domain-specific failure handling | 7 jobs |
| 30 | Missing idempotency guards in jobs | Add duplicate-execution checks | 4 jobs |
| 31 | `metadata_json` untyped grab-bag | Introduce typed state DTO or dedicated columns | ExecuteAgentRunJob |
| 32 | JSON response parsing duplication (OpenAI/Anthropic adapters) | Extract `JsonResponseParser` trait | 2 files, ~35 LOC saved |
| 33 | `HybridRetriever` tightly coupled search strategies | Extract `SearchStrategy` interface | 1 -> 4 classes |
| 34 | Magic numbers in `RunEventWriter` and `HybridRetriever` | Replace with named constants | 2 files |
| 35 | Pagination response duplication | Create reusable pagination macro or base resource | ~8 controllers |
| 36 | `AgentRunController::stop()` at 186 lines | Extract `RunStopService` | 1 controller |
| 37 | Listener `fresh()` defensive pattern repeated 5+ times | Centralize in event base class or guard method | 3 listener files |
| 38 | Route parameter naming inconsistency (`{id}` vs `{graphId}`, `{taskId}`) | Standardize naming convention | routes/api.php |

---

## Appendix: Architecture Inventory

### Directory Structure Quality

```
app/
├── Actions/         # Fortify actions only - underutilized pattern
├── Console/         # Commands with domain grouping
├── Contracts/       # Only 4 interfaces - should grow
├── DTOs/            # 14 DTOs in Messenger/ and Runtime/ - good
├── Enums/           # 16 backed enums - partial adoption
├── Events/          # Well-organized broadcast events
├── Exceptions/      # Domain-specific exceptions - good
├── Http/            # Controllers, Middleware, Requests, Resources
├── Jobs/            # Domain-grouped (Memory/, Messenger/, RepoAnalysis/, Agent/)
├── Listeners/       # Event subscribers for delegation, docs
├── Logging/         # Custom log processors
├── Messenger/       # Slash commands, gateway workers
├── Models/          # Eloquent models
├── Notifications/   # Queued notifications
├── Observers/       # Model observers
├── Policies/        # Authorization policies
├── Providers/       # Service providers (too few)
├── Repositories/    # Only 2 - underutilized
├── Rules/           # Custom validation rules
├── Services/        # Business logic services
└── Support/         # Domain support classes (largest namespace)
```

### Key Metrics

| Metric | Value |
|--------|-------|
| Total PHP files | 720 |
| Total LOC (app/) | ~104,000 |
| Controllers | 69 |
| Jobs | 39 |
| Models | 73 (55 `$guarded = []`, 18 `$fillable`) |
| Interfaces | 19 (scattered across 4 locations) |
| Enums | 16 (partial adoption -- 100+ string constants remain) |
| DTOs | 14 |
| Files > 800 LOC | 12 |
| Largest file | 4,124 LOC |
| Service Providers | 5 (App, Fortify, Horizon, Messenger, Memory) |
| `env()` outside config | 15 occurrences in 3 commands |
| Broad `catch (\Exception)` | 13 occurrences in 10 files |

---

*Report generated 2026-03-06. Review methodology: static analysis of all `app/` PHP files with focus on structural patterns, SOLID compliance, and Laravel conventions.*
