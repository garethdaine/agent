# Code Quality Review Report

**Project:** Agent Scheduler (Laravel 12 / PHP 8.3)
**Date:** 2026-03-06
**Scope:** Full application — `app/`, `config/`, `tests/`
**Focus Areas:** SOLID, Laravel Best Practices, DRY, Design Patterns, Code Quality, Bugs, Security
**Graph:** SOLID Analysis | Task ID: 100 | Attempt: 1

---

## STAR Pre-Execution

### SITUATION
Large Laravel 12 / PHP 8.3 application with 75+ models, 69+ controllers, 90+ support classes, and 65+ service classes. Includes memory system with Neo4j, delegation graphs, messenger integrations, runtime CLI execution, telemetry, and compliance workflows. Previous SOLID analysis (Task 97) identified 66 violations. This review expands scope to bugs, security, DRY, and additional quality concerns.

### TASK
Produce a comprehensive code quality report covering SOLID violations, potential bugs, security vulnerabilities, DRY violations, Laravel anti-patterns, PHP 8.3 usage gaps, and maintainability concerns. All findings must be actionable with severity ratings.

### ACTION
1. Enumerated all source files across app/, config/, tests/ directories
2. Reviewed critical security-sensitive code: ConfigurationController, CredentialVault, FsToolAdapter, AttachmentHandler, SessionProcessManager, EnvPolicy
3. Analyzed 55+ models for mass assignment, business logic leakage, and missing enums
4. Reviewed job/service architecture for error handling, DRY, and SOLID compliance
5. Searched for dangerous patterns: raw SQL, superglobals, exec calls, file operations
6. Cross-referenced with existing SOLID analysis and identified net-new findings

### RESULT
59 net-new findings identified (beyond the 66 from Task 97). Includes 5 Critical (security), 12 High, 34 Medium, and 8 Low severity items. Priority remediation roadmap provided.

---

## Executive Summary

| Category | Findings | Critical | High | Medium | Low |
|----------|---------|----------|------|--------|-----|
| Security / Authorization | 17 | 5 | 7 | 4 | 1 |
| Bugs / Logic Errors | 14 | 0 | 2 | 10 | 2 |
| DRY Violations | 7 | 0 | 1 | 5 | 1 |
| Laravel Anti-Patterns | 11 | 0 | 1 | 8 | 2 |
| PHP 8.3 Usage Gaps | 4 | 0 | 0 | 3 | 1 |
| Performance / Reliability | 2 | 0 | 0 | 2 | 0 |
| Test Coverage Gaps | 4 | 0 | 1 | 2 | 1 |
| **Total** | **59** | **5** | **12** | **34** | **8** |

---

## Critical Findings (Security)

### SEC-1: `.env` File Manipulation via API (CRITICAL)
**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php:114-138`

The `writeEnvValues()` method directly reads and writes the `.env` file via `file_get_contents`/`file_put_contents` from an API endpoint. Issues:

1. **No atomic write**: Concurrent requests can corrupt the `.env` file (partial writes)
2. **No file locking**: Race condition between read and write
3. **Value injection**: Line 124 only escapes values with spaces, but doesn't escape values containing `\n`, `"`, or `#` characters — an attacker could inject additional env variables:
   ```
   value = "foo\nSECRET_KEY=stolen"
   ```
4. **No authorization check visible**: The controller doesn't show policy/gate enforcement

**Remediation:**
- Extract to `EnvironmentConfigurationManager` with `flock()` for atomic writes
- Sanitize values: strip newlines, escape quotes, reject `#` characters
- Add explicit admin authorization gate
- Consider storing config overrides in database instead of `.env`

---

### SEC-2: SSRF via Attachment Download URL (CRITICAL)
**File:** `app/Services/Messenger/AttachmentHandler.php:66-81`

The `download()` method accepts arbitrary URLs from messenger webhooks and makes HTTP requests to them without SSRF protection:

```php
$response = $this->getHttpClient()->get($url);
```

An attacker controlling a messenger webhook payload could target internal services (`http://169.254.169.254/latest/meta-data/`, `http://localhost:6379/`, etc.).

**Remediation:**
- Validate URL scheme (`https` only in production)
- Block private/internal IP ranges (10.x, 172.16-31.x, 192.168.x, 169.254.x, localhost, `[::1]`)
- DNS resolution check before connecting (prevent DNS rebinding)
- Consider using a download proxy service

---

### SEC-3: Command Injection via `clamdscan` Path (CRITICAL)
**File:** `app/Services/Messenger/AttachmentHandler.php:131`

```php
$result = Process::run("clamdscan --no-summary {$path}");
```

The `$path` variable is constructed from user-controlled `$filename` at line 69:
```php
$safeName = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
```

While `preg_replace` sanitizes most dangerous characters, the path is still interpolated into a shell command string rather than passed as an array argument.

**Remediation:**
- Use `Process::run(['clamdscan', '--no-summary', $path])` (array form) to prevent shell interpretation
- Or use `escapeshellarg($path)` as a defense-in-depth measure

---

### SEC-4: Download Signature Uses APP_KEY Directly as Both Data and Key (CRITICAL)
**File:** `app/Services/Messenger/AttachmentHandler.php:293-298`

```php
private function generateDownloadSignature(ChatAttachment $attachment, int $ttlMinutes): string
{
    $expires = now()->addMinutes($ttlMinutes)->timestamp;
    $data = $attachment->id.$expires.config('app.key');
    return hash_hmac('sha256', $data, config('app.key'));
}
```

The `APP_KEY` is used both as part of the HMAC data AND as the HMAC key. This is a cryptographic anti-pattern (length extension vulnerability potential). Additionally, there's no verification method visible — the signature generation exists but validation may be missing or inconsistent.

**Remediation:**
- Remove `config('app.key')` from the data portion: `$data = $attachment->id . $expires`
- Use Laravel's built-in URL signing (`URL::signedRoute()`) instead of custom HMAC
- Ensure corresponding signature verification exists and checks expiry

---

### SEC-5: Mass Assignment on All 55+ Models (`$guarded = []`) (CRITICAL)
**Files:** 55 models including `CredentialVault.php`, `AgentJobRun.php`, `ConnectorAccount.php`, `User.php` etc.

Every model in the application uses `$guarded = []` (unguarded), meaning any attribute can be set via mass assignment. While this is convenient during development, it poses a significant risk if any controller uses `Model::create($request->all())` or similar patterns without explicit field filtering.

High-risk models:
- `CredentialVault` — stores encrypted secrets
- `ConnectorAccount` — stores webhook credentials
- `AgentJobRun` — controls execution state
- `User` — controls authentication and roles

**Remediation:**
- Convert high-risk models to explicit `$fillable` arrays
- At minimum, add `$guarded = ['id']` to prevent ID injection
- Audit all `::create()` and `->fill()` call sites for unvalidated input

---

## High-Severity Findings

### SEC-6A: IDOR Vulnerabilities — Missing Ownership Scoping (HIGH)
**Files:**
- `app/Http/Controllers/Api/V1/Org/OrgAgentController.php:66-109` (show/update/destroy)
- `app/Http/Controllers/Api/V1/MessengerConnectorController.php:188-347` (show/update/destroy)

Controllers find resources by ID without verifying user ownership before authorization. `$this->authorize()` is called after `find()`, allowing authenticated users to enumerate other users' resources and potentially trigger timing-based information leaks.

```php
$profile = OrgAgentProfile::find($id);  // No user scoping
$this->authorize('view', $profile);      // Too late
```

**Remediation:**
- Scope all queries with user ownership: `OrgAgentProfile::forUser($user)->findOrFail($id)`
- Or use route model binding with a global scope

---

### SEC-6B: Weak Form Request Authorization (HIGH)
**Files:** `StoreAgentJobRequest.php:20-23`, `UpdateAgentJobRequest.php:20-23`, `StoreOrgAgentRequest.php:12-15`, `SubmitAnswerRequest.php:10-13`

All form request `authorize()` methods only check `$this->user() !== null` — any authenticated user passes. No ownership or role validation.

**Remediation:** Implement proper authorization: `return $this->user()->can('create', AgentJob::class);`

---

### SEC-6C: Missing Authorization on Sensitive Endpoints (HIGH)
**Files:**
- `app/Http/Controllers/Api/V1/DebugPanelController.php` — exposes diagnostics without authorization
- `app/Http/Controllers/Api/V1/ComplianceController.php:17-24` — status endpoint unprotected
- `app/Http/Controllers/Api/V1/Messenger/DeadLetterController.php` — no authorization checks

**Remediation:** Add `$this->middleware('can:view-debug-panel')` or equivalent gate checks.

---

### BUG-1: Replay Protection Race Condition (HIGH)
**File:** `app/Http/Middleware/Messenger/ReplayProtection.php:36-48`

```php
if (Cache::has($cacheKey)) {
    return $this->duplicateResponse();
}
Cache::put($cacheKey, true, $ttlSeconds);
```

TOCTOU race condition: Between `Cache::has()` and `Cache::put()`, a duplicate request can pass through. Under high concurrency, duplicate webhook events will be processed.

**Remediation:**
- Use `Cache::add()` which is atomic (returns `false` if key exists):
  ```php
  if (!Cache::add($cacheKey, true, $ttlSeconds)) {
      return $this->duplicateResponse();
  }
  ```

---

### BUG-2: ConfigurationController Key Mapping Mismatch (HIGH)
**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php:58-74`

Line 60 converts dots to underscores for validation: `str_replace('.', '_', $key)`
Line 68 converts underscores back to dots: `str_replace('_', '.', $flatKey)`

This is lossy — config keys containing underscores (like `agent_streaming_enabled`) will be incorrectly converted. The `configKeyToEnv` match at line 100 uses dot-separated keys like `runtime.default.mode` but the actual config keys use mixed separators like `runtime.default_mode`.

**Remediation:**
- Use a bidirectional mapping array instead of string replacement
- Or use a dedicated separator that doesn't conflict (e.g., `__` for nesting)

---

### SEC-6: FsToolAdapter Path Traversal — Incomplete realpath Validation (HIGH)
**File:** `app/Services/Runtime/Adapters/FsToolAdapter.php:99`

The `validatePathWithinWorkspace` method is called with the raw `$path` from user input, but for write/delete operations, the file may not exist yet — `realpath()` returns `false` for non-existent paths. If the base class implementation relies on `realpath()`, symlink-based or `../` traversal for new file creation could bypass the workspace boundary.

**Remediation:**
- For write/move/delete operations on non-existent paths, resolve the parent directory's realpath and validate the target would fall within workspace
- Add explicit symlink resolution check

---

### SEC-7: SessionProcessManager — Orphan Process Kill Without Ownership Verification (HIGH)
**File:** `app/Services/Runtime/SessionProcessManager.php:68-74`

```php
$pid = (int) (Cache::get(self::PROCESS_PREFIX.$runtimeSessionId) ?? 0);
if ($pid > 0 && posix_kill($pid, 0)) {
    posix_kill($pid, 15);  // SIGTERM
```

PIDs can be recycled by the OS. If the original process has exited and its PID has been reassigned to an unrelated process, this code kills the wrong process. The only check is `posix_kill($pid, 0)` which confirms the PID exists, not that it's the expected process.

**Remediation:**
- Store process start time alongside PID in cache
- Verify `/proc/$pid/stat` start time matches before sending signals
- Or use process groups and `posix_kill(-$pgid, $signal)`

---

### DRY-1: Duplicated Metadata Merge Pattern in ExecuteAgentRunJob (HIGH)
**File:** `app/Jobs/ExecuteAgentRunJob.php`

The pattern `(array) ($run->metadata_json ?? [])` appears 12 times. The metadata merge-and-save pattern appears in `updateMetadata()`, `finalizeTerminal()`, `recordComplianceMetadata()`, and `failRunSafely()` — each with slightly different merge logic.

Lines: 109, 267, 332, 377, 413, 437, 453, 535, 536, 802, 877, 943

**Remediation:**
- Extract a `RunMetadataManager` that encapsulates read-modify-write with consistent merge semantics
- Use a single `mergeMetadata(AgentJobRun $run, array $patch): void` method

---

### SEC-8A: Webhook Secret Null When Webhooks Enabled (HIGH)
**File:** `config/agent.php:266-271`

```php
'webhooks' => [
    'enabled' => env('AGENT_WEBHOOKS_ENABLED', false),
    'secret' => env('AGENT_WEBHOOK_SECRET'),  // null if unset
],
```

If webhooks are enabled without setting `AGENT_WEBHOOK_SECRET`, the secret is `null`, making signature verification trivially bypassable.

**Remediation:** Add boot-time validation that throws if webhooks are enabled without a non-empty secret.

---

### SEC-8: Neo4j Default Credentials in Config (HIGH)
**File:** `app/Support/Memory/Neo4jGraphStore.php:302-305`

```php
$username = config('memory.neo4j.username', 'neo4j');
$password = config('memory.neo4j.password', 'password');
```

Default password `'password'` is a security risk if the config is not explicitly set.

**Remediation:**
- Use `null` as default and require explicit configuration
- Add a health check warning when default credentials are detected
- Never commit default passwords in source code

---

### TEST-1: No Integration Tests for ConfigurationController .env Writes (HIGH)
**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php`

The `update()` endpoint directly modifies the production `.env` file with no test coverage for edge cases (concurrent writes, newline injection, missing file, permission errors).

**Remediation:**
- Add feature tests with a temporary `.env` file
- Test injection vectors, concurrent access, and error handling
- Consider mocking the file operations for unit testing

---

## Medium-Severity Findings

### BUG-3: MemoryContextBuilder Double Feature Flag Check (MEDIUM)
**File:** `app/Support/Memory/MemoryContextBuilder.php:47` and `app/Jobs/ExecuteAgentRunJob.php:156`

The memory enabled flag is checked in `ExecuteAgentRunJob::handle()` before calling `buildContext()`, and then `buildContext()` checks it again internally. While not a bug per se, it creates confusion about where the responsibility lies and could mask issues if the flag changes between checks.

**Remediation:**
- Remove the internal check in `buildContext()` — let the caller be responsible for gating

---

### BUG-4: ExecuteAgentRunJob Temp File Cleanup Only on Success Path (MEDIUM)
**File:** `app/Jobs/ExecuteAgentRunJob.php:493,981-986`

`cleanupEnhancedTaskFile()` is called at line 493 inside the try block, after `finalizeTerminal()`. If an exception occurs during the monitoring loop (caught at line 494), the `catch` block calls `failRunSafely()` but never calls `cleanupEnhancedTaskFile()`. Temp files will leak in `/tmp/` on exception paths.

**Remediation:**
- Move cleanup to a `finally` block:
  ```php
  } catch (\Throwable $throwable) {
      report($throwable);
      $this->failRunSafely($run, $transitions, $throwable);
  } finally {
      $this->cleanupEnhancedTaskFile();
  }
  ```

---

### BUG-5: Neo4j Cypher $depth Variable Not Parameterized (MEDIUM)
**File:** `app/Support/Memory/Neo4jGraphStore.php:184-188`

```php
$cypher = <<<CYPHER
    MATCH path = (start)-[*1..$depth]-(related:Entity)
CYPHER;
```

The `$depth` variable is interpolated into the Cypher string via PHP heredoc (note: not nowdoc). While `$depth` is clamped to `[1,5]` at line 182, this is a Cypher injection vector if the clamping is ever removed or bypassed. Cypher parameterization (`$depth`) should be used instead.

**Remediation:**
- Pass depth as a Cypher parameter and use `apoc.path.expand` or construct the range pattern safely
- Or switch to NOWDOC and build the query differently

---

### BUG-6: MemoryFormationJob Records Failure on Non-Exception Pipeline Failures (MEDIUM)
**File:** `app/Jobs/Memory/MemoryFormationJob.php:106-127`

When `$pipeline->process($run)` returns `$result->success === false`, the job records a failure and returns without throwing. This means the job is marked as "completed" by the queue system despite the pipeline failing. The retry mechanism (`$tries = 5`) will never trigger because no exception is thrown.

**Remediation:**
- Throw an exception for retryable failures so the queue system retries
- Only record permanent failures in the `failed()` method
- Keep the current behavior only for non-retryable failures (e.g., missing run)

---

### DRY-2: Approval/Permission/Clarification Metadata Resolution Pattern (MEDIUM)
**File:** `app/Jobs/ExecuteAgentRunJob.php:562-576,879-891`

The pattern for resolving `approval_required`, `permission_blocker_detected`, and `clarification_required` metadata flags is duplicated between `finalizeTerminal()` and `failRunSafely()`:

```php
if (($metadata['approval_required'] ?? false) === true) {
    $metadata['approval_required'] = false;
    $metadata['approval_resolved_at'] = $finishedAt->toIso8601String();
    $metadata['approval_resolution'] = $status;
}
// Repeated for permission_blocker and clarification
```

**Remediation:**
- Extract `resolveActiveFlags(array &$metadata, string $resolution, CarbonImmutable $at): void`

---

### DRY-3: Duplicated File Sanitization Pattern (MEDIUM)
**File:** `app/Services/Messenger/AttachmentHandler.php:69,162`

```php
$safeName = Str::uuid()->toString().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
```

This exact pattern appears in both `download()` and `store()`.

**Remediation:**
- Extract `private function sanitizeFilename(string $filename): string`

---

### DRY-4: Repeated `(array) ($run->metadata_json ?? [])` Cast Pattern (MEDIUM)
**Files:** Multiple files across the codebase

This defensive cast appears 20+ times across `ExecuteAgentRunJob`, `RunStateTransitionService`, and other job/service files.

**Remediation:**
- Add a helper method on the `AgentJobRun` model: `public function getMetadataArray(): array`
- Or add an accessor that always returns an array

---

### LAR-1: Using `$_ENV` Directly Instead of `config()` (MEDIUM)
**Files:** `ExecuteAgentRunJob.php:506`, `CliRuntimeExecutor.php:73`, `ClaudeAdapter.php:837`, `CodexAdapter.php:848`

Multiple files pass `$_ENV` directly to subprocess environments. This bypasses Laravel's config layer and breaks the framework's abstraction.

**Remediation:**
- Document why raw `$_ENV` is intentionally used here (legitimate: subprocess isolation requires the actual process environment, not just Laravel config values)
- Add a clear comment explaining this is intentional for subprocess environment propagation

---

### LAR-2: CredentialVault Uses `$guarded = []` with Sensitive Data (MEDIUM)
**File:** `app/Models/CredentialVault.php:17`

This model stores encrypted credentials but has no mass assignment protection. A single unvalidated `CredentialVault::create($data)` could allow an attacker to set `encrypted_value` directly (bypassing `setDecryptedValue()`), or override `user_id` to hijack another user's credentials.

**Remediation:**
- Switch to `$fillable = ['user_id', 'provider', 'key', 'metadata']` — never allow direct mass-assignment of `encrypted_value`

---

### LAR-3: Direct Artisan Call in HTTP Request (MEDIUM)
**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php:84`

```php
Artisan::call('config:clear');
```

Running artisan commands synchronously in an HTTP request can cause timeout issues and creates coupling between the HTTP layer and the console layer.

**Remediation:**
- Dispatch config clearing as a queued job
- Or use `Config::clearResolvedInstances()` for the current request

---

### LAR-4: Model Business Logic — CredentialVault Encryption (MEDIUM)
**File:** `app/Models/CredentialVault.php:33-51`

Encryption/decryption logic (`getDecryptedValue`/`setDecryptedValue`) belongs in a service, not the model. This makes the model responsible for cryptographic operations and harder to test in isolation.

**Remediation:**
- Extract to `CredentialEncryptionService` with `encrypt(string): string` and `decrypt(string): ?string`

---

### LAR-5: Missing Return Type on Config Cast Method (MEDIUM)
**File:** Multiple models define `casts()` without explicit return type (PHP 8.3 improvement)

While functional, modern PHP 8.3 style favors property-based casts or explicit return types.

---

### PHP-1: String Constants Instead of Backed Enums (MEDIUM)
**Files:** 12+ models including `AgentJobRun`, `DelegationGraph`, `DelegationTask`, `InterrogationSession`, `ChatAction`

Status constants like `STATUS_QUEUED = 'queued'`, `STATUS_RUNNING = 'running'` are defined as class string constants rather than PHP 8.1+ backed enums. This was already flagged in Task 97 but remains the single highest-impact PHP modernization opportunity.

**Remediation (priority list):**
1. `AgentJobRun` statuses → `AgentJobRunStatus: string` enum
2. `DelegationTask` statuses → `DelegationTaskStatus: string` enum
3. `InterrogationSession` statuses → `InterrogationSessionStatus: string` enum

---

### PHP-2: Missing `readonly` on Value Objects (MEDIUM)
**Files:** `app/DTOs/Messenger/ProviderResponse.php`, `app/Support/Delegation/ValidationResult.php`, `app/Support/Delegation/AssignmentResult.php`, `app/Support/Delegation/EnforcementResult.php`

DTO classes that should be immutable don't use PHP 8.2+ `readonly class` declaration.

**Remediation:**
- Add `readonly` class modifier to all DTOs and value objects

---

### PHP-3: Missing `declare(strict_types=1)` (MEDIUM)
**Files:** ~80% of files in `app/` lack strict types declaration

Only the newer memory-related files consistently use `declare(strict_types=1)`. This means implicit type coercion can mask bugs throughout the codebase.

**Remediation:**
- Add `declare(strict_types=1)` to all PHP files (can be automated with PHP-CS-Fixer)

---

### SEC-9: AttachmentHandler URL Logging Exposes Internal URLs (MEDIUM)
**File:** `app/Services/Messenger/AttachmentHandler.php:75`

```php
throw new \RuntimeException("Failed to download attachment from {$url}: {$response->status()}");
```

If the URL contains authentication tokens (common in messenger file URLs like Slack's `files.slack.com/...?token=xoxb-...`), this error message will be logged with the token.

**Remediation:**
- Redact query parameters from URLs before logging
- Use `parse_url($url, PHP_URL_PATH)` in error messages

---

### SEC-10: Replay Protection Cache Key Allows Cross-Account Collision (MEDIUM)
**File:** `app/Http/Middleware/Messenger/ReplayProtection.php:74-76`

```php
return "messenger:event_dedupe:{$account->id}:{$eventId}";
```

If `$eventId` contains `:` characters, it could theoretically collide with another account's cache key. While unlikely, the delimiter should be escaped or a hash-based key used.

**Remediation:**
- Use `hash('xxh3', $account->id . ':' . $eventId)` as the cache key suffix

---

### TEST-2: No Test for FsToolAdapter Path Traversal (MEDIUM)
**File:** `app/Services/Runtime/Adapters/FsToolAdapter.php`

The filesystem adapter handles path validation and workspace boundary enforcement but has no dedicated test file verifying traversal attacks (`../../etc/passwd`, symlink attacks, null bytes, etc.).

**Remediation:**
- Create `tests/Feature/Runtime/FsToolAdapterSecurityTest.php` with path traversal test cases

---

### TEST-3: MemoryFormationJob Retry Logic Not Tested (MEDIUM)
**File:** `tests/Feature/Memory/MemoryFormationJobTest.php`

The job records failures but the test may not verify that the queue retry mechanism works correctly when `$result->success === false` (see BUG-6).

---

## Low-Severity Findings

### BUG-7: DelegationGraphBuilder computeSequenceOrders Inefficient Fixed-Point (LOW)
**File:** `app/Support/Delegation/DelegationGraphBuilder.php:289-313`

The sequence order computation uses a `while ($changed)` loop that re-scans all tasks each iteration. For a DAG of depth D with N tasks, this is O(D*N) in the worst case. Since cycle detection already runs Kahn's algorithm, the topological order could be captured there and reused for O(N) depth computation.

**Remediation:**
- Capture topological order from `detectCycles` and compute depths in a single pass

---

### BUG-8: Neo4j purgeUser Silently Fails (LOW)
**File:** `app/Support/Memory/Neo4jGraphStore.php:269-290`

If Neo4j client is null (connection failed), `purgeUser()` silently returns without error. For a GDPR compliance operation, this should fail loudly.

**Remediation:**
- Throw an exception when client is null during purge operations
- Or return a result indicating success/failure

---

### DRY-5: Repeated UUID File Naming Pattern (LOW)
**Files:** `AttachmentHandler.php`, `MemoryContextBuilder.php`, `ExportService.php`

The pattern `Str::uuid()->toString() . '_' . sanitized_name` appears in multiple file storage locations.

---

### LAR-6: Missing Horizon Tag Method on ExecuteAgentRunJob (LOW)
**File:** `app/Jobs/ExecuteAgentRunJob.php`

Unlike `MemoryFormationJob` which has a `tags()` method, `ExecuteAgentRunJob` doesn't define Horizon tags. This makes monitoring harder.

---

### LAR-7: Feature Flag Checked via Service Locator Pattern (LOW)
**Files:** Multiple — `app(FeatureFlagManager::class)->enabled(...)` throughout

The `FeatureFlagManager` is resolved via `app()` service locator rather than constructor injection in most places.

**Remediation:**
- Inject `FeatureFlagManager` via constructor where possible

---

### PHP-4: Using `array_merge` Instead of Spread Operator for Simple Merges (LOW)
**File:** `app/Jobs/ExecuteAgentRunJob.php:580`

```php
$payload = array_merge($extra, ['finished_at' => $finishedAt, 'duration_ms' => $durationMs]);
```

Could use the spread operator for clarity: `$payload = [...$extra, 'finished_at' => $finishedAt, ...]`

---

### TEST-4: No Test for Concurrent .env Writes (LOW)
The ConfigurationController env write logic has no concurrency tests.

---

### SEC-11: Neo4j URI Logged on Connection Failure (LOW)
**File:** `app/Support/Memory/Neo4jGraphStore.php:315-320`

The `$e->getMessage()` from connection failure may contain the full bolt URI with credentials.

**Remediation:**
- Catch and redact credentials from error messages before logging

---

## Additional Findings from Service/Job Deep Dive

### BUG-9: Silent Exception Swallowing in WorkingMemoryBuffer (MEDIUM)
**File:** `app/Support/Memory/WorkingMemoryBuffer.php:69-71, 115-117`

Empty catch blocks (`catch (\Throwable) {}`) swallow all errors without any logging. Corrupted memory state persists undetected and debugging becomes impossible.

**Remediation:** Log at DEBUG level minimum; add metrics counters for failure rates.

### BUG-10: Non-Deterministic Idempotency Key for ChatMessage (MEDIUM)
**File:** `app/Jobs/Runtime/ProcessRuntimeTurnJob.php:247-264`

`'idempotency_key' => hash('sha256', Str::uuid()->toString())` — using a random UUID defeats the purpose of idempotency. If the job retries, a new UUID generates a new key, creating duplicate messages.

**Remediation:** Use deterministic key: `hash('sha256', $chatSessionId . $direction . $content . $timestamp)`

### BUG-11: Compaction Boundary Corruption Loads Full History (MEDIUM)
**File:** `app/Services/Messenger/ChatSessionManager.php:64-83`

If `compaction_boundary_message_id` references a deleted message, the boundary query returns null and all messages are loaded — defeating compaction.

**Remediation:** Guard for null boundary; reset compaction state or log warning.

### DRY-6: Duplicated Connector Resolution Pattern Across Runtime Jobs (MEDIUM)
**Files:** `ProcessRuntimeTurnJob.php`, `RuntimeTurnCompletedJob.php`, `ResumeRuntimeTurnJob.php`

All three jobs duplicate identical ConnectorAccount → adapter → session resolution chains (5+ lines each).

**Remediation:** Extract `RuntimeConnectorResolver` service.

### PERF-1: No Query Timeout on Neo4j Graph Traversals (MEDIUM)
**File:** `app/Support/Memory/Neo4jGraphStore.php:174-228`

`queryRelated()` has hardcoded LIMIT 50 but no execution timeout. Dense graphs could cause expensive full-graph scans.

**Remediation:** Add configurable query timeout (10-30s); log slow queries.

### REL-1: WebhookDeliveryService Doesn't Retry on 5xx (MEDIUM)
**File:** `app/Services/Agent/WebhookDeliveryService.php:27-31`

`->retry(2, 1000)` only retries on network errors, not 5xx HTTP responses. Transient 503s cause permanent webhook delivery failures.

**Remediation:** Add custom retry handler that also retries on 5xx status codes.

### SEC-12: ErrorEnvelope Details Not Sanitized (MEDIUM)
**File:** `app/Support/Agent/ErrorEnvelope.php`

`ErrorEnvelope::make()` passes `$details` directly to the JSON response with no sanitization. If a controller catches an exception containing config values (API keys, secrets), these leak in the API response.

**Remediation:** Add a sanitization layer that strips sensitive keys from `$details` before serializing.

### BUG-12: Mutable Public Static Arrays on Memory Models (MEDIUM)
**Files:** `MemoryConversationLog.php:57,69`, `MemoryFormationFailure.php:49`, `MemoryCoreBlock.php:45,55`, `MemoryConsolidationLog.php:46`

`public static array` properties (e.g., `$validRoles`, `$validTypes`) can be mutated at runtime by any code, silently changing validation behavior across the application.

**Remediation:** Convert to private constants or immutable getter methods: `public static function getValidRoles(): array`

### BUG-13: AgentJob Boot Validation Order Incorrect (MEDIUM)
**File:** `app/Models/AgentJob.php:26-43`

In the `saving` event, workflow_key is normalized, then potentially generated, then validated. But if generation fails or returns an invalid key, the validation at the end throws after the key is already assigned.

**Remediation:** Validate after all normalization/generation steps, before assignment.

### BUG-14: N+1 Query Risk in AgentJobRun::scopeForUser (MEDIUM)
**File:** `app/Models/AgentJobRun.php:96`

Scope executes nested `$user->allTeams()->pluck('id')` and `AgentJob::query()->forUser($user)->pluck('id')` — triggers additional queries each time the scope is applied.

**Remediation:** Use subquery joins or cache team/job IDs per request.

### LAR-8: Direct `$request->validate()` in 15+ Controllers (MEDIUM)
**Files:** `ChatSessionController.php:63`, `CredentialsController.php:41`, `ConfigurationController.php:58`, `ConnectorPolicyController.php:23`, and many Interrogation controllers

Controllers perform inline validation instead of using form requests. This mixes validation with business logic (SRP), prevents `authorize()` from running, and duplicates rules across endpoints.

**Remediation:** Create dedicated form request classes for all validated endpoints.

### LAR-9: Inconsistent API Error Response Format (MEDIUM)
**Files:** `ChatSessionController.php:69,77`, `AgentJobController.php:165-168`

Some endpoints use `ErrorEnvelope::make()`, others use raw `response()->json(['error' => ...])`. Clients must handle multiple response structures.

**Remediation:** Enforce `ErrorEnvelope` across all endpoints via middleware or base controller.

### LAR-10: Controllers Catching and Hiding Exceptions (MEDIUM)
**Files:** `OrgAgentController.php:24-53`, `OrgCouncilController.php:28-52`

Broad `catch (Throwable)` blocks return empty arrays, making errors indistinguishable from "no data".

**Remediation:** Return error responses or let exceptions propagate to the handler.

---

## Cross-Cutting Patterns

### Pattern 1: Defensive Metadata Casting
The `(array) ($run->metadata_json ?? [])` pattern appears 20+ times and should be centralized as a model accessor.

### Pattern 2: Feature Flag Gating
Memory feature flag is checked at 3 levels (caller, builder, job), creating confusion about responsibility. Adopt single-check-at-boundary pattern.

### Pattern 3: Process Execution Security
Three separate code paths execute external processes (`ExecuteAgentRunJob`, `CliRuntimeExecutor`, `SessionProcessManager`). Each has different environment handling, signal management, and timeout behavior. A unified `ProcessExecutionService` could reduce duplication and ensure consistent security practices.

### Pattern 4: File Operation Safety
Raw `file_get_contents`/`file_put_contents` calls appear in 15+ locations without consistent error handling, locking, or permission checks. A `SafeFileOperations` utility with atomic writes and proper locking would reduce risk.

---

## Priority Remediation Roadmap

### Immediate (Critical — Week 1)
1. **SEC-1**: Extract `.env` manipulation to safe service with atomic writes
2. **SEC-2**: Add SSRF protection to attachment downloads (block internal IPs)
3. **SEC-3**: Fix command injection — use array form for `clamdscan`
4. **SEC-4**: Replace custom HMAC with Laravel's signed URLs
5. **SEC-5**: Convert `CredentialVault`, `ConnectorAccount`, `AgentJobRun` to explicit `$fillable`

### Short-term (High — Week 2-3)
6. **SEC-6A**: Fix IDOR vulnerabilities — add ownership scoping to OrgAgent and Connector controllers
7. **SEC-6B**: Strengthen form request `authorize()` methods with proper policy checks
8. **SEC-6C**: Add authorization to DebugPanel, Compliance status, and DeadLetter endpoints
9. **BUG-1**: Fix replay protection race condition with `Cache::add()`
10. **BUG-2**: Fix ConfigurationController key mapping lossy conversion
11. **SEC-6**: Fix FsToolAdapter path validation for non-existent paths
12. **SEC-7**: Add process start time verification before killing orphans
13. **DRY-1**: Extract `RunMetadataManager` from `ExecuteAgentRunJob`
14. **TEST-1**: Add ConfigurationController integration tests

### Medium-term (Medium — Sprint backlog)
12. **BUG-4**: Move temp file cleanup to `finally` block
13. **BUG-5**: Parameterize Neo4j depth variable
14. **BUG-6**: Fix MemoryFormationJob to throw on retryable failures
15. **PHP-1**: Convert top 3 model status constants to backed enums
16. **PHP-3**: Add `declare(strict_types=1)` project-wide
17. **DRY-2/3/4**: Extract duplicate patterns
18. **TEST-2/3**: Add security and retry tests

### Ongoing (Low)
19. Add `readonly` to DTO classes
20. Inject `FeatureFlagManager` via constructor
21. Add Horizon tags to all jobs
22. Fix Neo4j credential logging
