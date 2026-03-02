# Implementation Plan

Derived from discovery session 13.

# Implementation Plan: Resolve All Production Stub/Placeholder Code

## Overview

This plan addresses 11 implementation gaps in a Laravel/Vue.js agent management system where placeholder/stub code must be replaced with production-ready implementations. All changes maintain backward compatibility and preserve existing test coverage.

---

## Section 1: Messenger Handler Consolidation

### 1.1 Update ChatActionExecutor to Use Consolidated Handlers

**File:** `app/Services/Messenger/ChatActionExecutor.php`

**Changes:**
1. Replace import statements to reference `App\Messenger\ChatAction\Handlers\*` classes
2. Replace `ActionHandlerInterface` with `ChatActionHandlerInterface`
3. Update handler map to reference consolidated handler classes:
   - `JobsListHandler`, `JobsCreateHandler`, `JobsUpdateHandler`, `JobsDeleteHandler`
   - `RunsListActiveHandler`, `RunsStopHandler`, `RunsRetryHandler`, `RunsRunNowHandler`, `RunsSteerHandler`
4. Modify `execute()` method to construct `ChatActionContext` before calling handler
5. Update `resolveHandler()` return type to `?ChatActionHandlerInterface`
6. Adapt result handling to use `ChatActionResult` instead of `ActionResult`

**Context Construction Logic:**
```php
$context = new ChatActionContext(
    user: $user,
    parameters: $action->parameters ?? [],
    action: $action->action_type,
    targetJob: $this->resolveTargetJob($action),
    confirmed: $action->confirmed ?? false,
    targetRun: $this->resolveTargetRun($action)
);
```

**Helper Methods to Add:**
- `resolveTargetJob(ChatAction $action): ?AgentJob` — Load job from parameters[job_id] if present
- `resolveTargetRun(ChatAction $action): ?AgentJobRun` — Load run from parameters[run_id] if present

**Result Conversion:**
- Map `ChatActionResult::isSuccess()` to `ActionResult::success()/failure()`
- Preserve message and data payloads

### 1.2 Delete Stub Handler Files

**Files to Delete:**
- `app/Services/Messenger/ActionHandlers/JobsListHandler.php`
- `app/Services/Messenger/ActionHandlers/JobsCreateHandler.php`
- `app/Services/Messenger/ActionHandlers/JobsUpdateHandler.php`
- `app/Services/Messenger/ActionHandlers/JobsDeleteHandler.php`
- `app/Services/Messenger/ActionHandlers/RunsListActiveHandler.php`
- `app/Services/Messenger/ActionHandlers/RunsStopHandler.php`
- `app/Services/Messenger/ActionHandlers/RunsRetryHandler.php`
- `app/Services/Messenger/ActionHandlers/RunsRunNowHandler.php`
- `app/Services/Messenger/ActionHandlers/RunsSteerHandler.php`
- `app/Services/Messenger/ActionHandlers/ActionHandlerInterface.php`

**Verification:**
- Run existing tests in `tests/Unit/Messenger/Handlers/*` to confirm consolidated handlers pass
- Confirm no references remain to `App\Services\Messenger\ActionHandlers` namespace

---

## Section 2: Messenger Policy Enforcement

### 2.1 Implement Ownership Validation in ChatActionPolicyValidator

**File:** `app/Services/Messenger/ChatActionPolicyValidator.php`

**Method:** `validateUserPermissions()` (lines 96-115)

**Replace placeholder logic with:**
- Return `PolicyValidationResult::allowed()` for all authenticated users
- Remove TODO comment at line 106

**Method:** `validateResourceOwnership()` (lines 120-167)

**Replace placeholder logic with actual ownership checks:**

```php
private function validateResourceOwnership(
    ChatAction $action,
    User $user
): PolicyValidationResult {
    $actionType = ChatActionType::tryFrom($action->action_type);
    $parameters = $action->parameters ?? [];

    if ($actionType === null) {
        return PolicyValidationResult::allowed();
    }

    $ownershipActions = [
        ChatActionType::JOBS_UPDATE,
        ChatActionType::JOBS_DELETE,
        ChatActionType::RUNS_STOP,
        ChatActionType::RUNS_RETRY,
        ChatActionType::RUNS_STEER,
    ];

    if (!in_array($actionType, $ownershipActions, true)) {
        return PolicyValidationResult::allowed();
    }

    $jobId = $parameters['job_id'] ?? null;
    $runId = $parameters['run_id'] ?? null;

    if ($jobId !== null) {
        $job = AgentJob::find($jobId);
        if (!$job || $job->user_id !== $user->id) {
            return PolicyValidationResult::denied('You do not own this resource');
        }
    }

    if ($runId !== null) {
        $run = AgentJobRun::with('job')->find($runId);
        if (!$run || !$run->job || $run->job->user_id !== $user->id) {
            return PolicyValidationResult::denied('You do not own this resource');
        }
    }

    return PolicyValidationResult::allowed();
}
```

**Method:** `validateAgentPolicies()` (lines 172-216)

**Remove TODO at line 189** — Keep existing dangerous pattern checks as basic safety

**Imports to Add:**
- `use App\Models\AgentJob;`
- `use App\Models\AgentJobRun;`

---

## Section 3: Compliance Metrics Endpoint

### 3.1 Implement Real Metrics in ComplianceController

**File:** `app/Http/Controllers/Api/V1/ComplianceController.php`

**Method:** `metrics()` (line 26)

**Replace placeholder with tenant-scoped aggregations:**

```php
public function metrics(): JsonResponse
{
    $tenantId = auth()->user()?->tenant_id;
    
    $totalJobs = AgentJob::where('tenant_id', $tenantId)->count();
    $totalRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('tenant_id', $tenantId))->count();
    
    $successCount = AgentJobRun::whereHas('job', fn($q) => $q->where('tenant_id', $tenantId))
        ->where('status', AgentJobRun::STATUS_SUCCEEDED)->count();
    $failCount = AgentJobRun::whereHas('job', fn($q) => $q->where('tenant_id', $tenantId))
        ->where('status', AgentJobRun::STATUS_FAILED)->count();
    
    $activeRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('tenant_id', $tenantId))
        ->whereIn('status', [AgentJobRun::STATUS_PENDING, AgentJobRun::STATUS_RUNNING])->count();
    
    return response()->json([
        'total_jobs' => $totalJobs,
        'total_runs' => $totalRuns,
        'success_rate' => $totalRuns > 0 ? round($successCount / $totalRuns, 4) : null,
        'failure_rate' => $totalRuns > 0 ? round($failCount / $totalRuns, 4) : null,
        'active_runs' => $activeRuns,
    ]);
}
```

**Imports to Add:**
- `use App\Models\AgentJob;`
- `use App\Models\AgentJobRun;`

---

## Section 4: Remove Compliance Tenant Override Capability

### 4.1 Simplify ComplianceFlagResolver

**File:** `app/Support/Compliance/ComplianceFlagResolver.php`

**Method:** `getTenantOverride()` (lines 124-129)

**Replace with explicit null return:**
```php
protected function getTenantOverride(?int $tenantId, string $key): mixed
{
    // Tenant-specific compliance overrides are not supported.
    // All tenants follow system defaults.
    return null;
}
```

**Alternative:** Remove method entirely and update `resolve()` to skip tenant override lookup:
```php
public function resolve(string $key, ?int $tenantId = null): mixed
{
    // Tenant overrides not supported — always use global value
    return $this->getGlobalValue($key);
}
```

---

## Section 5: Delegation Reconciler Auto-Retry

### 5.1 Implement Retry Logic for Blocked Tasks

**File:** `app/Support/Delegation/DelegationReconciler.php`

**Method:** `retryBlockedTasks()` (lines 83-97)

**Replace no-op block with retry implementation:**

```php
private function retryBlockedTasks(): void
{
    $blockedTasks = DelegationTask::query()
        ->where('status', DelegationTask::STATUS_BLOCKED)
        ->whereHas('graph', fn($q) => $q->whereIn('status', DelegationGraph::ACTIVE_STATUSES))
        ->get();

    foreach ($blockedTasks as $task) {
        $metadata = $task->metadata_json ?? [];
        $retryCount = $metadata['retry_count'] ?? 0;
        $lastRetryAt = isset($metadata['last_retry_at']) 
            ? Carbon::parse($metadata['last_retry_at']) 
            : null;

        // Check if retry is due based on backoff schedule
        $delays = [60, 300, 900]; // 1 min, 5 min, 15 min
        
        if ($retryCount >= 3) {
            // Max retries exceeded — mark permanently failed
            $task->update([
                'status' => DelegationTask::STATUS_FAILED,
                'finished_at' => now(),
                'error_code' => 'MAX_RETRIES_EXCEEDED',
                'error_summary' => 'Failed after 3 retry attempts',
            ]);
            continue;
        }

        $requiredDelay = $delays[$retryCount] ?? 900;
        if ($lastRetryAt && $lastRetryAt->addSeconds($requiredDelay)->isFuture()) {
            continue; // Not yet time for next retry
        }

        // Dispatch retry via DelegateeAssigner
        app(DelegateeAssigner::class)->assign($task);

        // Update retry metadata
        $task->update([
            'metadata_json' => array_merge($metadata, [
                'retry_count' => $retryCount + 1,
                'last_retry_at' => now()->toISOString(),
            ]),
        ]);
    }
}
```

### 5.2 Implement Retry Logic for Stuck Graphs

**Method:** `handleStuckGraphs()` (lines 105-129)

**Replace no-op block with completion detection:**

```php
private function handleStuckGraphs(): void
{
    $stuckGraphs = DelegationGraph::query()
        ->where('status', DelegationGraph::STATUS_RUNNING)
        ->whereDoesntHave('tasks', function ($query) {
            $query->whereNotIn('status', [
                DelegationTask::STATUS_SUCCEEDED,
                DelegationTask::STATUS_FAILED,
                DelegationTask::STATUS_CANCELLED,
            ]);
        })
        ->where('updated_at', '<', now()->subMinutes(5))
        ->get();

    foreach ($stuckGraphs as $graph) {
        $allSucceeded = $graph->tasks()->where('status', '!=', DelegationTask::STATUS_SUCCEEDED)->doesntExist();
        $anyFailed = $graph->tasks()->where('status', DelegationTask::STATUS_FAILED)->exists();
        
        if ($allSucceeded) {
            $graph->update(['status' => DelegationGraph::STATUS_SUCCEEDED, 'finished_at' => now()]);
        } elseif ($anyFailed) {
            $graph->update(['status' => DelegationGraph::STATUS_FAILED, 'finished_at' => now()]);
        } else {
            $graph->update(['status' => DelegationGraph::STATUS_PARTIAL, 'finished_at' => now()]);
        }
    }
}
```

**Imports to Add:**
- `use Carbon\Carbon;`
- `use App\Support\Delegation\DelegateeAssigner;`

---

## Section 6: NL Schedule Parser Clarification Flow

### 6.1 Return Clarification Response for Low Confidence

**File:** `app/Support/NlSchedule/NlScheduleParserService.php`

**Method:** `parse()` (lines 107-128)

**Replace queued status with clarification response:**

```php
// 5. Low confidence: return clarification required
$attempt = $this->repository->create(
    $user,
    $input,
    $timezone,
    'clarification_required',
    $result
);

$this->logRedacted('Low-confidence parse requires clarification', $input, [
    'attempt_id' => $attempt->id,
    'confidence' => $result->confidence,
    'ambiguous' => $result->ambiguous,
]);

return [
    'status' => 'clarification_required',
    'parse_attempt_id' => $attempt->id,
    'interpretation' => $result->toArray(),
    'alternatives' => $this->generateAlternatives($result),
    'message' => 'I understood this as "' . $result->humanReadable . '". Is this correct, or did you mean something else?',
];
```

**New Method to Add:**
```php
private function generateAlternatives(ScheduleParseResult $result): array
{
    // Return plausible alternative interpretations based on ambiguity flags
    $alternatives = [];
    
    if ($result->ambiguous['time'] ?? false) {
        $alternatives[] = ['type' => 'time', 'suggestion' => 'Specify AM or PM explicitly'];
    }
    if ($result->ambiguous['day'] ?? false) {
        $alternatives[] = ['type' => 'day', 'suggestion' => 'Specify exact day of week or date'];
    }
    if ($result->ambiguous['frequency'] ?? false) {
        $alternatives[] = ['type' => 'frequency', 'suggestion' => 'Clarify daily, weekly, or monthly'];
    }
    
    return $alternatives;
}
```

---

## Section 7: Trust Score Calculator

### 7.1 Replace Placeholder Metric Values

**File:** `app/Support/Delegation/TrustScoreCalculator.php`

**Method:** `aggregateMetrics()` (lines 75-125)

**Replace hardcoded placeholder values at lines 116-119:**

```php
private function aggregateMetrics($runs): StarMetrics
{
    $total = $runs->count();

    if ($total === 0) {
        return new StarMetrics(0, 0, 0, 0, 0, 0, 0, [], 0);
    }

    $starCompleted = 0;
    $successful = 0;
    $retrySuccessful = 0;
    $retryAttempted = 0;
    $failureModes = ['type_1' => 0, 'type_2' => 0, 'type_3' => 0];
    
    // New: track STAR component correctness
    $situationCorrect = 0;
    $taskCorrect = 0;
    $actionCorrect = 0;
    $resultCorrect = 0;
    $starEvaluated = 0;

    foreach ($runs as $run) {
        $metadata = $run->metadata_json ?? [];
        $summary = $metadata['reasoning_summary'] ?? [];

        if ($summary['all_completed'] ?? false) {
            $starCompleted++;
        }

        // Extract STAR component correctness from reasoning_summary
        if (isset($summary['situation_correct'])) {
            $starEvaluated++;
            $situationCorrect += $summary['situation_correct'] ? 1 : 0;
            $taskCorrect += ($summary['task_correct'] ?? false) ? 1 : 0;
            $actionCorrect += ($summary['action_correct'] ?? false) ? 1 : 0;
            $resultCorrect += ($summary['result_correct'] ?? false) ? 1 : 0;
        }

        if ($run->status === AgentJobRun::STATUS_SUCCEEDED) {
            $successful++;
            if (isset($metadata['retry_of_run_id'])) {
                $retrySuccessful++;
            }
        }

        if (isset($metadata['retry_of_run_id'])) {
            $retryAttempted++;
        }

        if (isset($metadata['failure_mode_hint']['type'])) {
            $type = 'type_' . $metadata['failure_mode_hint']['type'];
            $failureModes[$type] = ($failureModes[$type] ?? 0) + 1;
        }
    }

    return new StarMetrics(
        starCompletionRate: $starCompleted / $total,
        situationCorrectRate: $starEvaluated > 0 ? $situationCorrect / $starEvaluated : 0,
        taskCorrectRate: $starEvaluated > 0 ? $taskCorrect / $starEvaluated : 0,
        actionCorrectRate: $starEvaluated > 0 ? $actionCorrect / $starEvaluated : 0,
        resultCorrectRate: $starEvaluated > 0 ? $resultCorrect / $starEvaluated : 0,
        firstPassSuccessRate: ($successful - $retrySuccessful) / max(1, $total - $retryAttempted),
        recoveryRate: $retryAttempted > 0 ? $retrySuccessful / $retryAttempted : 0,
        failureModeDistribution: array_map(fn($c) => $c / max(1, $total), $failureModes),
        sampleSize: $total
    );
}
```

---

## Section 8: AI Critic Output Retrieval

### 8.1 Implement Multi-Source Fallback Chain

**File:** `app/Jobs/AiCriticCompletedJob.php`

**Method:** `getRunOutput()` (lines 127-132)

**Replace metadata-only fallback with multi-source chain:**

```php
private function getRunOutput(AgentJobRun $run): string
{
    // 1. Try canonical stdout file storage
    $stdoutPath = storage_path("app/runs/{$run->id}/stdout.log");
    if (file_exists($stdoutPath)) {
        $content = file_get_contents($stdoutPath);
        if (!empty(trim($content))) {
            return $content;
        }
    }

    // 2. Fall back to metadata_json['output']
    if (!empty($run->metadata_json['output'])) {
        return $run->metadata_json['output'];
    }

    // 3. Fall back to run artifacts
    if ($run->relationLoaded('artifacts') || $run->artifacts()->exists()) {
        $artifacts = $run->artifacts()->whereIn('type', ['text', 'log', 'output'])->get();
        $combined = $artifacts->pluck('content')->filter()->implode("\n");
        if (!empty(trim($combined))) {
            return $combined;
        }
    }

    // 4. No output found
    return '';
}
```

---

## Section 9: Inbound Attachment Processing

### 9.1 Implement Immediate Attachment Download

**File:** `app/Jobs/Messenger/ProcessInboundMessage.php`

**Method:** `extractAttachmentIds()` (lines 259-271)

**Replace provider-ID-only extraction with download and storage:**

```php
private function processAttachments(
    NormalizedMessage $message,
    ConnectorAccount $account,
    ChatMessage $chatMessage
): void {
    if (empty($message->attachments)) {
        return;
    }

    $adapter = app(ConnectorManager::class)->resolve($account->provider);
    $maxSize = config('messenger.attachments.max_size', 10 * 1024 * 1024); // 10MB
    $allowedTypes = config('messenger.attachments.allowed_types', ['image/*', 'application/pdf', 'text/*']);

    foreach ($message->attachments as $attachment) {
        try {
            // Fetch file content from provider
            $content = $adapter->downloadAttachment($account, $attachment->providerFileId);
            
            if ($content === null) {
                $this->createFailedAttachment($chatMessage, $attachment, 'Download failed');
                continue;
            }

            // Validate size
            if (strlen($content) > $maxSize) {
                $this->createFailedAttachment($chatMessage, $attachment, 'File too large');
                continue;
            }

            // Validate MIME type
            if (!$this->isAllowedType($attachment->mimeType, $allowedTypes)) {
                $this->createFailedAttachment($chatMessage, $attachment, 'File type not allowed');
                continue;
            }

            // Store file
            $disk = config('messenger.attachments.disk', 'local');
            $path = "attachments/{$chatMessage->id}/" . Str::uuid() . '.' . $attachment->extension;
            Storage::disk($disk)->put($path, $content);

            // Create attachment record
            MessageAttachment::create([
                'chat_message_id' => $chatMessage->id,
                'provider_file_id' => $attachment->providerFileId,
                'local_path' => $path,
                'disk' => $disk,
                'mime_type' => $attachment->mimeType,
                'size' => strlen($content),
                'filename' => $attachment->filename,
                'status' => 'stored',
            ]);

        } catch (\Throwable $e) {
            Log::warning('ProcessInboundMessage: Attachment download failed', [
                'message_id' => $chatMessage->id,
                'provider_file_id' => $attachment->providerFileId,
                'error' => $e->getMessage(),
            ]);
            $this->createFailedAttachment($chatMessage, $attachment, $e->getMessage());
        }
    }
}

private function createFailedAttachment(ChatMessage $message, $attachment, string $reason): void
{
    MessageAttachment::create([
        'chat_message_id' => $message->id,
        'provider_file_id' => $attachment->providerFileId,
        'status' => 'failed_to_download',
        'error_message' => $reason,
    ]);
}

private function isAllowedType(string $mimeType, array $allowed): bool
{
    foreach ($allowed as $pattern) {
        if (str_contains($pattern, '*')) {
            $prefix = str_replace('*', '', $pattern);
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        } elseif ($mimeType === $pattern) {
            return true;
        }
    }
    return false;
}
```

**Update `handle()` method:**
- After creating `ChatMessage`, call `$this->processAttachments($normalizedMessage, $account, $message)`
- Remove old `attachment_ids` column assignment or convert to null

**Imports to Add:**
- `use App\Models\MessageAttachment;`
- `use Illuminate\Support\Facades\Storage;`
- `use Illuminate\Support\Str;`

---

## Section 10: Slack Socket Worker Graceful Drain

### 10.1 Implement Pending Operation Tracking

**File:** `app/Messenger/Gateway/Workers/SlackSocketWorker.php`

**Add Properties:**
```php
private int $pendingOperations = 0;
private int $drainTimeoutSeconds = 30;
```

**Method:** `drain()` (lines 153-163)

**Replace flag-only implementation:**

```php
public function drain(): void
{
    Log::info('SlackSocketWorker draining', [
        'account_id' => $this->account->id,
        'pending_operations' => $this->pendingOperations,
    ]);

    $this->draining = true;
    $this->drainTimeoutSeconds = (int) config('messenger.gateway.drain_timeout', 30);

    // Create deferred for drain completion
    $deferred = new Deferred();
    $startTime = time();

    // Poll for completion
    $timer = $this->loop->addPeriodicTimer(0.1, function ($timer) use ($deferred, $startTime) {
        if ($this->pendingOperations === 0) {
            $this->loop->cancelTimer($timer);
            Log::info('SlackSocketWorker drain completed', [
                'account_id' => $this->account->id,
            ]);
            $deferred->resolve(true);
            return;
        }

        if ((time() - $startTime) >= $this->drainTimeoutSeconds) {
            $this->loop->cancelTimer($timer);
            Log::warning('SlackSocketWorker drain timeout', [
                'account_id' => $this->account->id,
                'pending_operations' => $this->pendingOperations,
            ]);
            $deferred->resolve(false);
            return;
        }
    });
}
```

**Update `dispatchEvent()` method:**
```php
private function dispatchEvent(array $payload): void
{
    $this->pendingOperations++;

    try {
        Log::debug('Dispatching event to ProcessInboundMessage', [
            'account_id' => $this->account->id,
            'event_type' => $payload['event']['type'] ?? 'unknown',
        ]);

        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $this->account->id,
            provider: $this->account->provider,
            payload: $payload
        )->afterCommit();
        
    } finally {
        // Decrement after dispatch (not after job completes - immediate decrement for socket layer)
        $this->pendingOperations--;
    }
}
```

---

## Section 11: Final Cleanup

### 11.1 Verification Checklist

**Files Modified:**
1. `app/Services/Messenger/ChatActionExecutor.php` — Handler consolidation
2. `app/Services/Messenger/ChatActionPolicyValidator.php` — Ownership enforcement
3. `app/Http/Controllers/Api/V1/ComplianceController.php` — Real metrics
4. `app/Support/Compliance/ComplianceFlagResolver.php` — Remove tenant override
5. `app/Support/Delegation/DelegationReconciler.php` — Auto-retry logic
6. `app/Support/NlSchedule/NlScheduleParserService.php` — Clarification flow
7. `app/Support/Delegation/TrustScoreCalculator.php` — Real STAR metrics
8. `app/Jobs/AiCriticCompletedJob.php` — Multi-source output retrieval
9. `app/Jobs/Messenger/ProcessInboundMessage.php` — Attachment download
10. `app/Messenger/Gateway/Workers/SlackSocketWorker.php` — Graceful drain

**Files Deleted:**
- Entire `app/Services/Messenger/ActionHandlers/` directory (10 files)

### 11.2 Test Execution Order

1. Run `tests/Unit/Messenger/Handlers/*` — Verify consolidated handlers work
2. Run `tests/Unit/Messenger/Validation/ChatActionPolicyValidatorTest.php` — Verify ownership
3. Run `tests/Feature/Support/Delegation/DelegationReconcilerTest.php` — Verify retry logic
4. Run `tests/Feature/NlSchedule/NlScheduleParserServiceTest.php` — Verify clarification
5. Run `tests/Unit/Support/Delegation/TrustScoreCalculatorTest.php` — Verify STAR metrics
6. Run `tests/Unit/Messenger/Gateway/Workers/SlackSocketWorkerTest.php` — Verify drain
7. Run full test suite to confirm no regressions

### 11.3 Code Quality Audit

After implementation, search for and remove:
- `// TODO:` comments in all 11 affected files
- `// PLACEHOLDER` comments
- `// For now,` comments indicating stub behavior
- Any remaining fake data returns or hardcoded sample values

## Sections

- Section 1: Messenger Handler Consolidation
- Section 2: Messenger Policy Enforcement
- Section 3: Compliance Metrics Endpoint
- Section 4: Remove Compliance Tenant Override Capability
- Section 5: Delegation Reconciler Auto-Retry
- Section 6: NL Schedule Parser Clarification Flow
- Section 7: Trust Score Calculator
- Section 8: AI Critic Output Retrieval
- Section 9: Inbound Attachment Processing
- Section 10: Slack Socket Worker Graceful Drain
- Section 11: Final Cleanup


## Risks

- Handler consolidation may require adapter layer if ActionResult DTO differs significantly from ChatActionResult — verify field compatibility before deleting stub handlers
- Ownership validation adds database queries to every policy check — may need to optimize with eager loading in ChatActionExecutor
- ComplianceController metrics query performance on large datasets — consider adding database indexes on tenant_id and status columns
- DelegateeAssigner service may not exist — verify dependency exists or implement stub before wiring retry logic
- Attachment download happens synchronously in ProcessInboundMessage job — large files may cause job timeout; consider chunked downloads or separate job for large attachments
- SlackSocketWorker pendingOperations counter may drift if exceptions bypass finally block — add try-finally wrapper around all dispatch paths
- Trust score STAR metrics depend on reasoning_summary structure being populated by agent runs — degradation to zero values if upstream doesn't provide this data
- Clarification flow requires frontend changes to display alternatives and handle confirmation — API change may break existing mobile/web clients expecting 'queued' status


## Assumptions

- Consolidated handlers in App\Messenger\ChatAction\Handlers namespace are production-ready and pass all existing unit tests
- AgentJob model has user_id column and AgentJobRun has job relationship to AgentJob
- ChatActionResult and ActionResult DTOs are structurally compatible (success/failure boolean, message string, data array)
- DelegateeAssigner service exists or will be created as part of delegation engine implementation
- MessageAttachment model and migration exist or will be created alongside this implementation
- Provider adapters implement downloadAttachment method or equivalent file retrieval capability
- Run artifacts relationship exists on AgentJobRun model with content field
- Frontend/mobile clients can handle new 'clarification_required' status without breaking changes
- Storage disk configuration for attachments is already present in config/filesystems.php

