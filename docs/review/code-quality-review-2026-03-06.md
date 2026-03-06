# Code Quality Review - Full Application

**Date**: 2026-03-06 (Updated: Pass 3 - Task 82)
**Graph**: SOLID Analysis | Task ID: 82 | Attempt: 1
**Scope**: Full application - Services, Jobs, Commands, Support, Middleware, Providers, Routing, Config, Frontend
**Focus**: SOLID, Laravel Best Practices, DRY, Design Patterns, Code Quality, Bugs, Security

---

## Executive Summary

Reviewed 90+ PHP files and 5 frontend Vue/JS files across all application layers. The codebase has strong foundations: good use of DTOs, service layer separation, consistent authorization patterns, and modern PHP 8.3 features. However, **28 Critical/High severity issues** require immediate attention, primarily around race conditions, command injection vectors, process resource management, type safety gaps, cross-tenant data leaks, and frontend reactivity bugs.

| Severity | Count |
|----------|-------|
| Critical | 15 |
| High | 24 |
| Medium | 28 |
| Low | 16 |
| **Total** | **83** |

---

## Critical Findings

### C-01: Command Injection via ChatIntentParser
**File**: `app/Services/Messenger/ChatIntentParser.php:200-208`
**Category**: Security

CLI process execution passes user-controlled `$prompt` directly:
```php
$result = Process::timeout(30)->run([
    $this->executable(), '-p', '--output-format', 'json',
    '--json-schema', $this->actionSchema(),
    $prompt,  // User-controlled content
]);
```
While array form prevents shell injection, the prompt content itself could exploit the Claude CLI through prompt injection.

**Recommendation**: Validate/sanitize prompt content. Use JSON stdin for safe data exchange.

---

### C-02: Insufficient Dangerous Pattern Validation (Blacklist Bypass)
**File**: `app/Services/Messenger/ChatActionPolicyValidator.php:193-203`
**Category**: Security

Regex-based dangerous command matching is trivially bypassed:
```php
$dangerousPatterns = [
    '/\brm\s+-rf\s+\//' => 'Removing root directory is not allowed',
    '/\bdd\s+.*of=\/dev\//' => 'Direct device writes are not allowed',
];
```
Bypassable via shell quoting, aliases, alternative tools (`shred`, `find -delete`), or encoding.

**Recommendation**: Switch to whitelist approach. Use a proper command parser or allow-list of specific executables.

---

### C-03: Command Template Injection in CommandPolicy
**File**: `app/Support/Agent/CommandPolicy.php:58-111`
**Category**: Security

Template placeholders like `{{task_markdown_path}}` are validated for shell operators but not for quote escaping or newline injection:
```php
template = "executable {{task_markdown_path}}"
// Malicious path: /tmp/file'; rm -rf /; echo '
```

**Recommendation**: Use array-based command execution instead of shell string templates. Validate resolved paths for special characters.

---

### C-04: Instance Fingerprint Race Condition
**File**: `app/Support/Agent/InstanceFingerprint.php:24-39`
**Category**: Bug / Data Integrity

`getOrCreateSalt()` has a check-then-create race condition:
```php
$existing = AgentSystemState::where('key', self::SALT_KEY)->first();
if ($existing) { return $existing->value; }
$salt = Str::random(64);
AgentSystemState::create([...]); // No unique constraint, no lock
```

**Recommendation**: Use `firstOrCreate()` with database unique constraint on `key`.

---

### C-05: TOCTOU Race in Escalation Incident Creation
**File**: `app/Services/Escalation/IncidentLifecycleService.php:76-98`
**Category**: Bug / Race Condition

Unique constraint check followed by create allows concurrent duplicates:
```php
try {
    $incident = EscalationIncident::query()->create([...]);
} catch (QueryException $exception) {
    if (! $this->isUniqueViolation($exception)) { throw $exception; }
}
```

**Recommendation**: Use `firstOrCreate()` or proper pessimistic locking.

---

### C-06: Process Resource Leaks in ExecuteAgentRunJob
**File**: `app/Jobs/ExecuteAgentRunJob.php:233-393`
**Category**: Bug / Resource Leak

Process started with no guaranteed cleanup on exception before finalization:
- `signalProcess()` sends signals to potentially zombie/reused PIDs
- Temporary file cleanup (line 981-986) only runs after normal completion

**Recommendation**: Implement `finally` block for process cleanup. Verify PID ownership before signaling.

---

### C-07: Race Conditions in Message Idempotency
**File**: `app/Jobs/ProcessInboundMessage.php:110-117`
**Category**: Bug / Race Condition

```php
if (ChatMessage::query()->where('idempotency_key', $idempotencyKey)->exists()) {
    return; // Race window before create
}
$message = ChatMessage::create([...]); // Could still violate unique
```

**Recommendation**: Use `firstOrCreate()` or `updateOrInsert()`.

---

### C-08: Unguarded .env File Modification
**File**: `app/Console/Commands/AgentInstallCommand.php:1139-1151`
**Category**: Security / Data Integrity

Direct `.env` modification with no atomic write, no backup, and potential newline injection from `--license-key` option:
```php
$content = file_get_contents($envPath);
file_put_contents($envPath, $content); // No lock, no atomic rename
```

**Recommendation**: Use atomic write with `LOCK_EX` + temp file rename pattern.

---

### C-09: Credential Exposure via Environment Variables
**File**: `app/Services/Runtime/CliRuntimeExecutor.php:72-77`
**Category**: Security

API key passed via process environment without proper isolation:
```php
$env = array_merge(
    array_filter($parentEnv, ...),
    ['ANTHROPIC_API_KEY' => $apiKey]
);
```

**Recommendation**: Use dedicated IPC for credentials. Consider encrypted environment file approach.

### C-10: Missing FeatureFlagManager Import in RunEventWriter
**File**: `app/Support/Agent/RunEventWriter.php:76`
**Category**: Bug

`FeatureFlagManager::class` is referenced but never imported at the top of the file. This will cause a `Class not found` error at runtime whenever memory integration is triggered:
```php
if (app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
```

**Recommendation**: Add `use App\Support\Agent\FeatureFlagManager;` import or use fully-qualified class name.

---

### C-11: Frontend Reactivity Dead Code - In-Place Mutation Breaks Deep Watcher
**Files**: `resources/js/Composables/Office/useOfficeRealtime.js`, `resources/js/Pages/Agent/Office/AgentOffice.vue`
**Category**: Bug

`useOfficeRealtime` mutates agent objects in-place (`agent.status = newStatus`), which means Vue's deep watcher in `AgentOffice.vue` receives identical `old` and `new` values. All visual transition effects (particles, speech bubbles, zone movement, success/failure animations) comparing old vs new values are effectively dead code.

**Recommendation**: Produce new objects instead of mutating: `agents.value = agents.value.map(a => a.id === id ? { ...a, status: newStatus } : a)`.

---

### C-12: License Bypass via URL Manipulation
**File**: `app/Services/Agent/LicenseService.php:53-54`
**Category**: Security

`str_ends_with($host, $suffix)` without dot boundary means a bypass domain of `.test` would also match `malicioustest`. Additionally, `app.url` is a config value that could be manipulated by admin users:
```php
if ($host === $suffix || str_ends_with($host, $suffix)) {
    return true;
}
```

**Recommendation**: Require dot boundary: `str_ends_with($host, '.'.$suffix) || $host === ltrim($suffix, '.')`.

---

### C-13: Idempotency Key Race in SendOutboundMessage
**File**: `app/Jobs/Messenger/SendOutboundMessage.php:92-129`
**Category**: Bug / Race Condition

Idempotency key is created AFTER the message is successfully sent, then written to DB. If the DB write fails on retry, a duplicate message is sent because the guard key was never persisted.

**Recommendation**: Write idempotency key BEFORE send in a transaction. Mark as `confirmed` after successful delivery. Check key existence before send.

---

### C-14: Task Status Race in ExecuteRepoAnalysisTaskJob
**File**: `app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php:100-107`
**Category**: Bug / Race Condition

Task status updated to `running` without pessimistic lock. Concurrent workers can pick up and execute the same task simultaneously, causing duplicate work and potential data corruption.

```php
// Current (UNSAFE):
$task->update(['status' => 'running']);

// Fix:
DB::transaction(function () use ($task) {
    $task = $task->lockForUpdate()->fresh();
    if ($task->status !== 'pending') return;
    $task->update(['status' => 'running']);
});
```

---

### C-15: Silent Data Loss in MemoryWorkingBufferJob
**File**: `app/Jobs/Memory/MemoryWorkingBufferJob.php:36`
**Category**: Bug / Data Loss

`$tries = 0` (fire-and-forget) with no `failed()` method means memory working buffer data is permanently lost on any failure with zero observability.

**Recommendation**: Set `$tries >= 3` with backoff. Add `failed()` handler with logging/alerting.

---

## High Severity Findings

### H-01: Webhook Signature Verification O(n) Fallback
**File**: `app/Http/Middleware/Messenger/VerifyWebhookSignature.php:127-144`
**Category**: Performance / DoS

When account resolution fails, iterates ALL active accounts for signature verification - computationally expensive HMAC per account.

**Recommendation**: Add cache-based cooldown for failed IPs. Limit fallback iteration count.

---

### H-02: Unsafe File Operations in AttachmentHandler
**File**: `app/Services/Messenger/AttachmentHandler.php:131, 204`
**Category**: Security

`unlink()` and `rename()` without error handling or path traversal validation. Uses deprecated `mime_content_type()`.

**Recommendation**: Validate paths don't escape storage. Use `finfo_file()`. Add exception handling.

---

### H-03: Null Safety Gaps in AttachmentHandler
**File**: `app/Services/Messenger/AttachmentHandler.php:155-189`
**Category**: Bug

Missing null checks on model relationships:
```php
$session = $message->session;  // No null check
$userId = $session->user_id;   // NPE if session is null
```

**Recommendation**: Use null-safe operator: `$message->session?->user_id ?? throw new Exception()`

---

### H-04: N+1 Query in RecalculateTrustScoresJob
**File**: `app/Jobs/RecalculateTrustScoresJob.php:26-36`
**Category**: Performance

Individual `$profile->update()` per chunked profile instead of batch:
```php
$profiles->each(fn($profile) => $profile->update([...])); // N queries
```

**Recommendation**: Use `DB::table()->upsert()` for batch updates.

---

### H-05: BillingUsageService Empty String vs Null Check
**File**: `app/Services/Billing/BillingUsageService.php:21, 47`
**Category**: Bug

`config()` returns `null` when key missing, but code checks for empty string:
```php
$meter = config('billing.meters.runs', 'agent_runs');
if ($meter === '' || $meter === 'price_monthly') { return; }
```

**Recommendation**: Check for `null`: `if ($meter === null || $meter === '' || ...)`

---

### H-06: Unsafe JSON Decoding Without Depth Limit
**File**: `app/Services/Runtime/CliRuntimeExecutor.php:194-197`
**Category**: Security / DoS

```php
$decoded = json_decode($line, true); // No depth limit, no error flag
```

**Recommendation**: Use `json_decode($line, true, 32, JSON_THROW_ON_ERROR)`

---

### H-07: PID Reuse Risk in SessionProcessManager
**File**: `app/Services/Runtime/SessionProcessManager.php:71-72`
**Category**: Bug

`posix_kill($pid, 0)` checks process existence but not ownership - could kill wrong process on PID reuse.

**Recommendation**: Verify process ownership via `/proc/<pid>/` before signaling.

---

### H-08: Silent Exception Swallowing in MemoryWorkingBufferJob
**File**: `app/Jobs/MemoryWorkingBufferJob.php:61-76`
**Category**: Bug / Observability

With `$tries = 0`, failures are completely invisible:
```php
try { $buffer->append(...); }
catch (Throwable $e) { Log::debug(...); } // No re-throw, no alert
```

**Recommendation**: Log at warning level minimum. Consider `$tries >= 1` with backoff.

---

### H-09: Database Transaction Deadlock Risk
**File**: `app/Jobs/GenerateInterrogationBuildTasksJob.php:69-119`
**Category**: Bug

Delete-then-insert within transaction without row-level locking:
```php
DB::transaction(function () use ($session, $tasks): void {
    InterrogationBuildTask::query()->where(...)->delete(); // No lock
```

**Recommendation**: Use `FOR UPDATE` lock or `SKIP LOCKED` pattern.

---

### H-10: Uninitialized Timeout Property
**File**: `app/Jobs/ProcessRuntimeTurnJob.php:31`
**Category**: Bug

```php
public int $timeout; // Set in constructor, but uninitialized at property level
```

**Recommendation**: Add default value: `public int $timeout = 120;`

---

### H-11: Non-Idempotent Status Updates
**File**: `app/Jobs/DelegationAttemptCompletedJob.php:40-67`
**Category**: Bug

Updates attempt status without checking if already terminal. Retries could overwrite completed to failed.

**Recommendation**: Check `$attempt->status->isTerminal()` before updating.

---

### H-12: EnvPolicy Regex Pattern Injection
**File**: `app/Support/Agent/EnvPolicy.php:23-34`
**Category**: Security

Config-driven regex used directly without validation - poisoned config could cause crashes or DoS via catastrophic backtracking.

**Recommendation**: Wrap `preg_match()` in try-catch. Validate regex on config load.

---

### H-13: DatabaseDestructionGuard Type Confusion
**File**: `app/Support/Agent/DatabaseDestructionGuard.php:24-34`
**Category**: Security

`strtolower()` on potentially non-string `DB_CONNECTION` returns false/null, bypassing guard.

**Recommendation**: Add `is_string()` assertion before comparison.

---

### H-14: LicenseService Nested Array Access NPE
**File**: `app/Services/Agent/LicenseService.php:95-102`
**Category**: Bug

```php
plan: $data['license']['plan'] ?? 'standard', // Fails if $data['license'] is null
```

**Recommendation**: `($data['license'] ?? [])['plan'] ?? 'standard'`

---

### H-15: Potential Memory Exhaustion in Event Loading
**File**: `app/Jobs/ExecuteAgentRunJob.php:695-730`
**Category**: Performance

Loads all run events into memory without pagination:
```php
$events = $run->events()->where('event_type', 'stdout')->orderBy('sequence')->get(['payload']);
```

**Recommendation**: Use `cursor()` or `chunk()` for large result sets.

---

### H-16: Cross-Tenant Data Leak in OfficeStateController
**File**: `app/Http/Controllers/Api/V1/OfficeStateController.php`
**Category**: Security

Queries for `OrgEscalation`, `EscalationIncident`, and `ConnectorAccount` lack user scoping, potentially exposing data from other tenants:
```php
OrgEscalation::query()->where('status', 'open')... // No user_id filter
```

**Recommendation**: Add `->where('user_id', $request->user()->id)` to all tenant-scoped queries.

---

### H-17: Stale License Cache Ignores Expiry Date
**File**: `app/Services/Agent/LicenseService.php:30-33`
**Category**: Bug

When a cached license has `expires_at` in the past, the cached "valid" status persists until TTL expires (default 3600s). A license can remain "valid" for up to an hour after actual expiration.

**Recommendation**: When hydrating from cache, check if `expiresAt` is in the past and force re-validation.

---

### H-18: Autonomous Mode Injection in AttemptSpawner
**File**: `app/Support/Delegation/AttemptSpawner.php:125-131`
**Category**: Security

`ensureAutonomousTemplate()` injects dangerous flags (`--dangerously-skip-permissions`, `--dangerously-bypass-approvals-and-sandbox`) using fragile `str_replace`:
```php
$template = str_replace(' -p ', ' --dangerously-skip-permissions -p ', $template);
```
The `str_replace(' -p ', ...)` fails if `-p` is at start/end of template or uses different whitespace.

**Recommendation**: Use proper argument parsing. Consider if these flags should be gated by config or require explicit opt-in.

---

### H-19: N8n Webhook Has No Authentication
**File**: `routes/api.php:73`
**Category**: Security

`Route::post('/n8n/webhook', ...)` accepts unauthenticated requests with no signature verification, rate limiting, or IP allowlisting.

**Recommendation**: Add webhook signature verification middleware or shared secret check.

---

### H-21: Missing ShouldBeUnique on Periodic Jobs
**Files**: `app/Jobs/RecalculateTrustScoresJob.php`, `app/Jobs/Org/OrgDispatchDueRitualsJob.php`
**Category**: Bug / Performance

Neither job implements `ShouldBeUnique`. If scheduler overlap or manual dispatch occurs, concurrent instances perform identical work. For `OrgDispatchDueRitualsJob`, this causes duplicate ritual dispatches.

**Recommendation**: Implement `ShouldBeUnique` with `uniqueId()` and `uniqueFor()`.

---

### H-22: ChatIntentParser God Class (500+ lines)
**File**: `app/Services/Messenger/ChatIntentParser.php`
**Category**: SRP Violation

Handles 8 distinct concerns: regex pattern matching, AI intent parsing, prompt building, attachment context, session history, JSON validation, process execution, MIME classification.

**Recommendation**: Split into `IntentPatternMatcher`, `AiIntentParser`, `IntentPromptBuilder`, `AttachmentContextBuilder`, `ChatIntentValidator`.

---

### H-23: AttachmentHandler Mixed Responsibilities (300 lines)
**File**: `app/Services/Messenger/AttachmentHandler.php`
**Category**: SRP Violation

Combines: file downloading, validation, malware scanning, storage, quarantine, signature generation, URL generation.

**Recommendation**: Extract `FileDownloader`, `MalwareScanner`, `AttachmentStorage` services.

---

### H-24: Missing Transaction in ProcessRuntimeTurnJob
**File**: `app/Jobs/Runtime/ProcessRuntimeTurnJob.php:49-174`
**Category**: Bug / Data Integrity

Multi-step operation (fetch session -> execute -> send message -> persist -> compact) has no transaction wrapping. Partial failures leave inconsistent state.

**Recommendation**: Wrap critical persistence operations in a database transaction.

---

### H-20: RecalculateTrustScoresJob Gives Identical Scores Per Runner Type
**File**: `app/Jobs/RecalculateTrustScoresJob.php`
**Category**: Bug

`TrustScoreCalculator::calculate()` receives only `$profile->runner_type` as differentiator, meaning all profiles with the same runner type get identical scores. This is either a logic bug or massive inefficiency.

**Recommendation**: Pass profile-specific identifier to `calculate()`, or batch-update by runner_type.

---

## Medium Severity Findings

### M-01: SRP Violation - ExecuteAgentRunJob (987 lines)
**File**: `app/Jobs/ExecuteAgentRunJob.php`

Handles runtime validation, process execution, event writing, cost recording, billing, memory dispatch, retry logic, and failure classification.

**Recommendation**: Extract into `AgentProcessRunner`, `AgentCostRecorder`, `AgentFailureClassifier`.

---

### M-02: SRP Violation - ChatActionExecutor (265 lines)
**File**: `app/Services/Messenger/ChatActionExecutor.php`

Manages handler registry, validates policies, builds contexts, resolves resources, converts results, handles streaming.

**Recommendation**: Extract `ChatActionHandlerRegistry`, `ChatActionContextBuilder`, `ChatActionResultConverter`.

---

### M-03: SRP Violation - AgentInstallCommand
**File**: `app/Console/Commands/AgentInstallCommand.php`

Handles environment setup, license validation, preflight checks, user creation, connector configuration, and health checks.

**Recommendation**: Break into `agent:setup-env`, `agent:validate-license`, `agent:preflight-check`, etc.

---

### M-04: DIP Violation - Concrete Class Injection
**Files**: Multiple Services and Jobs

No interfaces for `FailureTaxonomyMapper`, `MemoryFormationPipeline`, `BuildTaskGenerator`, etc.

**Recommendation**: Create interfaces for testability and extensibility.

---

### M-05: OCP Violation - Hardcoded Tool Lists in ApprovalGate
**File**: `app/Services/Runtime/ApprovalGate.php:24-52`

35+ tools hardcoded in constants instead of config.

**Recommendation**: Move to `config/runtime.php`.

---

### M-06: Dead Code - ReplayParityService
**File**: `app/Services/Replay/ReplayParityService.php`

Stub always returns success. Never validates anything.

**Recommendation**: Implement or mark with `@todo` ticket reference.

---

### M-07: Race Condition in Memory Feature Flag
**File**: `app/Jobs/MemoryFormationJob.php:75-91`

Feature flag checked in `shouldQueue()` and again in `handle()` - flag could change between checks.

**Recommendation**: Use dispatch decision only; remove double-check.

---

### M-08: Implicit State Machine Transitions
**File**: `app/Jobs/OrgExecuteRitualJob.php:84-95`

Two sequential transitions (DRAFT->READY->RUNNING). If first succeeds but second fails, graph stuck in READY.

**Recommendation**: Make atomic or add recovery mechanism.

---

### M-09: Weak Type Checking in PolicyEngine Config
**File**: `app/Services/Runtime/PolicyEngine.php:46, 68`

Config values assumed to be arrays without validation.

**Recommendation**: Use type assertions at config access points.

---

### M-10: Inconsistent Feature Gate Patterns
**Files**: `app/Http/Middleware/OrgFeatureGate.php` vs `DelegationFeatureGate.php`

Org has fallback config check; Delegation doesn't. Method naming inconsistent (`enabled()` vs `isEnabled()`).

**Recommendation**: Standardize feature gate pattern across all middleware.

---

### M-11: PathPolicy Symlink Attack Vector
**File**: `app/Support/Agent/PathPolicy.php:15-52`

`realpath()` follows symlinks but doesn't verify the resolved path's parent directories aren't symlinked.

**Recommendation**: Check for symlinks in path components before comparison.

---

### M-12: N+1 Query in DelegationCoordinator
**File**: `app/Listeners/DelegationCoordinator.php:233-239`

Tasks loaded without eager-loading relationships, then accessed in loop.

**Recommendation**: Add `->with(['delegatee', 'dependencies', 'graph'])`.

---

### M-13: Unbounded Metadata JSON Growth
**File**: `app/Jobs/ExecuteAgentRunJob.php` (multiple locations)

Metadata JSON grows with each operation (launch_fingerprint, reasoning_summary, failure_mode_hint) without size limits.

**Recommendation**: Enforce max JSON size or prune old metadata entries.

---

### M-14: Missing State Verification in DelegationCoordinator
**File**: `app/Listeners/DelegationCoordinator.php:61-72`

Graph state checked after query already executed. `fresh()` could return null.

**Recommendation**: Use database-level constraints in the query itself.

---

### M-15: Incomplete Command Argument Validation
**File**: `app/Services/Messenger/CommandRouter.php:87-104`

No limit on argument count from `preg_split()`.

**Recommendation**: `array_slice($args, 0, 20)` to cap arguments.

---

### M-16: Unprotected N8n Webhook Route
**File**: `routes/api.php`

Public POST endpoint without rate limiting or signature verification.

**Recommendation**: Add `throttle` middleware or webhook signature verification.

---

### M-17: Validation Error Information Disclosure
**File**: `bootstrap/app.php:39-81`

Validation errors expose field names in production, leaking API schema.

**Recommendation**: Redact field names in production responses.

---

### M-18: Parameter Order Inconsistency
**File**: `app/Services/Messenger/ChatSessionManager.php:42, 64-82`

Inconsistent optional parameter ordering across similar methods.

**Recommendation**: Consistently place optional parameters at end.

---

### M-19: SRP Violation - RunEventWriter (~1000 lines)
**File**: `app/Support/Agent/RunEventWriter.php`

Handles event writing, output redaction, approval/permission/clarification/rate-limit/MCP pattern detection, broadcasting, snippet extraction, and binary detection. 13+ class constants for regex patterns alone.

**Recommendation**: Extract `OutputPatternDetector`, `OutputRedactor`, `OfficeBroadcaster` into focused classes.

---

### M-20: DRY Violation - Repeated Pattern Detection in RunEventWriter
**File**: `app/Support/Agent/RunEventWriter.php:132-154`

Four nearly identical blocks checking `!$isNonRuntimeSnippet && ($eventType === 'stdout' || $eventType === 'stderr') && preg_match(...)`:
```php
if (! $isNonRuntimeSnippet && ($eventType === 'stdout' || $eventType === 'stderr') && preg_match(self::APPROVAL_PATTERN, $chunk) === 1) { ... }
if (! $isNonRuntimeSnippet && ($eventType === 'stdout' || $eventType === 'stderr') && preg_match(self::PERMISSION_BLOCKER_PATTERN, $chunk) === 1) { ... }
// ... and two more
```

**Recommendation**: Extract into a pattern detection loop with pattern-to-handler mapping.

---

### M-21: `isValid()` Inconsistency With `validate()`
**File**: `app/Services/Agent/LicenseService.php:38-47`

`isValid()` only checks cache, never triggers remote validation. Can disagree with `validate()` which performs remote check when cache is empty.

**Recommendation**: Implement as `return $this->validate()->valid;` for consistency.

---

### M-22: HTTPS Not Enforced for License Validation URL
**File**: `app/Services/Agent/LicenseService.php:84`

The `validation_url` is configurable. If misconfigured with `http://`, license key and fingerprint are sent in plaintext.

**Recommendation**: Add guard: `if (!str_starts_with($url, 'https://')) { ... }`.

---

### M-23: Module-Level Shared State in Vue Composables
**File**: `resources/js/Composables/Office/useOfficeRealtime.js`

Reactive state declared at module level (outside the composable function) means all component instances share the same state. Works in single-page apps with one consumer, but breaks if composable is used by multiple components.

**Recommendation**: Move state declarations inside the composable function.

---

### M-24: GPU Memory Leak in useOfficeScene
**File**: `resources/js/Composables/Office/useOfficeScene.js`

Three.js geometries, materials, and textures are created but never disposed in cleanup. The `onUnmounted` handler calls `renderer.dispose()` but doesn't traverse the scene graph to dispose individual objects.

**Recommendation**: Add `scene.traverse(obj => { obj.geometry?.dispose(); obj.material?.dispose(); })` in cleanup.

---

### M-25: Full Scene Traversal Every Frame in useOfficeZones
**File**: `resources/js/Composables/Office/useOfficeZones.js:99-120`

`updateZoneOccupancy` iterates all agents and all zones on every animation frame, recalculating ceiling light intensities even when nothing has changed.

**Recommendation**: Only recalculate when agent positions change, or throttle to once per second.

---

### M-26: agentThoughts Keyed by run_id Instead of agent_id
**File**: `resources/js/Pages/Agent/Office/AgentOffice.vue:334-337`

Escalation handler uses `agentThoughts.value[latest.run_id]` but the map is otherwise keyed by `agent.id`. Creates phantom entries with UUID keys that render incorrectly in the template.

**Recommendation**: Use agent ID: `if (escalatedAgent) agentThoughts.value[escalatedAgent.id] = { ... }`.

---

### M-27: Magic Strings in RitualCouncilDeliberationListener
**File**: `app/Listeners/Org/RitualCouncilDeliberationListener.php:37, 75, 82`

Hardcoded strings `'Adversarial Review'` and `'Report Synthesis'` for task name matching. Fragile if task names change.

**Recommendation**: Use constants on the model or config.

---

### M-28: Silent Exception Swallowing in RitualCouncilDeliberationListener
**File**: `app/Listeners/Org/RitualCouncilDeliberationListener.php:56`

All `Throwable` caught with no logging:
```php
catch (Throwable) {
    // Best-effort -- don't block the delegation pipeline
}
```

**Recommendation**: Log at warning level for operational visibility.

---

## Low Severity Findings

| ID | Description | Files |
|----|-------------|-------|
| L-01 | Missing PHP 8.3+ `readonly` properties on immutable services | Multiple |
| L-02 | Inconsistent method naming (`getSchema()`/`schema()`, `isAllowed()`/`allowed()`) | Multiple |
| L-03 | Generic `array` type hints instead of specific shapes or DTOs | ChatIntentParser, others |
| L-04 | Overly broad `\Throwable` catching instead of specific exceptions | Multiple Services/Jobs |
| L-05 | Inconsistent error handling patterns (`report()` vs throw vs log-only) | Multiple Jobs |
| L-06 | Missing correlation IDs for cross-system tracing | Most Jobs |
| L-07 | Magic strings for statuses (`'yielded'`), prefixes (`'button:'`), limits | Multiple Jobs |
| L-08 | Config default values not consistently type-cast | `config/agent.php` |
| L-09 | OCP violation: CommandRouter `$handlers` array requires modification to extend | CommandRouter |
| L-10 | Swallowed exceptions without logging in AttachmentHandler | AttachmentHandler |
| L-11 | DelegationAttemptCompletedJob has `$backoff = 0` (immediate retries) | DelegationAttemptCompletedJob |
| L-12 | Deprecated `mime_content_type()` usage | AttachmentHandler |
| L-13 | Missing `ShouldBeUnique` on scheduled jobs to prevent overlapping runs (promoted to H-21 below) | RecalculateTrustScoresJob, OrgDispatchDueRitualsJob |
| L-14 | Service resolved via `app()` helper instead of method-level DI in job `handle()` | RecalculateTrustScoresJob:24 |
| L-15 | Hardcoded local paths in CodeReviewOrgSeeder (`/Users/garethdaine/Code/agent`) | CodeReviewOrgSeeder:128, 290, 340 |
| L-16 | DRY violation: duplicated INSERT INTO block in telemetry migration trigger function | Migration:162-184 vs 187-209 |

---

## SOLID Compliance Summary

| Principle | Grade | Key Issues |
|-----------|-------|------------|
| **Single Responsibility** | C | ExecuteAgentRunJob (987 lines), AgentInstallCommand, ChatActionExecutor all do too much |
| **Open/Closed** | B- | Hardcoded tool lists, handler registries require modification to extend |
| **Liskov Substitution** | B+ | Generally good; few interface issues |
| **Interface Segregation** | B | Missing interfaces for key services; no fat interface problems |
| **Dependency Inversion** | C+ | Multiple concrete class injections; no interfaces for major services |

---

## Positive Patterns

The codebase demonstrates strong foundations worth preserving:

- **DTOs & Result Objects**: `ProjectionRebuildStartResult`, `GateEvaluationResult`, `MemoryFormationResult` - immutable, type-safe
- **Authorization**: Consistent use of Laravel policies with `$this->authorize()`
- **Form Request Validation**: Proper delegation to dedicated Request classes
- **Focused Services**: `CanonicalCostCalculator`, `GateEvaluator`, `FailureTaxonomyMapper`, `PolicyEngine`
- **Clean Models**: Runtime models are well-designed data containers
- **Audit Trail**: `AuditLogger` provides centralized, consistent interface
- **Error Envelopes**: `ErrorEnvelope` for consistent API error responses
- **Modern PHP**: Good use of constructor property promotion, enums, match expressions, named arguments

---

## Priority Action Items

### Immediate (This Sprint)
1. **Fix FeatureFlagManager missing import** - runtime crash in RunEventWriter (C-10)
2. **Fix frontend reactivity bug** - in-place mutation kills all office visual transitions (C-11)
3. **Fix license bypass domain matching** - add dot boundary check (C-12)
4. **Fix idempotency race** in SendOutboundMessage - write key before send (C-13)
5. **Add pessimistic locking** to ExecuteRepoAnalysisTaskJob task claim (C-14)
6. **Fix silent data loss** in MemoryWorkingBufferJob - add retries and failed() handler (C-15)
7. Fix race conditions: InstanceFingerprint, IncidentLifecycleService, ProcessInboundMessage (C-04, C-05, C-07)
8. Harden command validation: switch to whitelist, fix template injection (C-02, C-03)
9. Add process cleanup `finally` block in ExecuteAgentRunJob (C-06)
10. **Fix cross-tenant data leak** in OfficeStateController (H-16)

### Short-Term (Next 2 Sprints)
11. Fix null safety gaps in AttachmentHandler and LicenseService (H-03, H-14)
12. **Fix stale license cache ignoring expiry date** (H-17)
13. **Secure N8n webhook endpoint** with auth/signature verification (H-19)
14. **Fix trust score calculation** - identical scores per runner type (H-20)
15. **Add ShouldBeUnique** to RecalculateTrustScoresJob and OrgDispatchDueRitualsJob (H-21)
16. **Add transaction wrapping** to ProcessRuntimeTurnJob critical operations (H-24)
17. Add batch updates for RecalculateTrustScoresJob (H-04)
18. Fix JSON decoding depth limits (H-06)
19. Add row-level locking to GenerateInterrogationBuildTasksJob (H-09)
20. Implement atomic .env writes (C-08)
21. Add rate limiting to webhook signature fallback (H-01)

### Medium-Term (Next Quarter)
22. Extract ExecuteAgentRunJob into focused sub-jobs (M-01)
23. **Extract RunEventWriter** into focused classes (M-19)
24. **Extract ChatIntentParser** into 5 specialized classes (H-22)
25. **Extract AttachmentHandler** into focused services (H-23)
26. **Fix GPU memory leak** in useOfficeScene (M-24)
27. Create service interfaces for DIP compliance (M-04)
28. Move hardcoded tool lists to config (M-05)
29. Standardize error handling and exception hierarchy (L-04, L-05)
30. Add correlation IDs across all jobs (L-06)

---

## Methodology

Pass 3 analysis performed across Services (65+ classes), Jobs (37 classes), Commands (24), Support (90+), Middleware (8+), Controllers (70+), Providers, Routes, Config, and Frontend Vue/JS composables (5 files). Each file read in full and evaluated for SOLID compliance, security vulnerabilities, bug potential, performance issues, Laravel best practices, and (for frontend) Vue reactivity correctness.

Three parallel review agents analyzed: Services layer (SOLID focus), Jobs & Commands layer (error handling, transaction safety), and Support/Controllers/Middleware layer (security, design patterns). Findings were cross-referenced and deduplicated with prior passes.

Severity ratings:
- **Critical**: Security vulnerability, data corruption risk, race condition with real-world exploit path, or runtime crash
- **High**: Bugs likely to manifest under load, cross-tenant data exposure, significant performance issues, or security hardening gaps
- **Medium**: Code quality issues affecting maintainability, SOLID violations, DRY violations, or minor security concerns
- **Low**: Style inconsistencies, missing modern PHP features, or minor best practice deviations
