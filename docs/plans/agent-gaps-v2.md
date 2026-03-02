# Implementation Plan

Derived from discovery session 13.

# Implementation Plan: Resolve All Production Stub/Placeholder Code

## Overview

This plan addresses 11 implementation gaps in a Laravel/Vue.js agent management system. All gaps represent placeholder or stub code that must be replaced with production-ready implementations following a strict sequence to minimize regressions.

---

## Section 1: Messenger Handler Consolidation

### 1.1 Update ChatActionExecutor to Use Consolidated Handlers

**File:** `app/Services/Messenger/ChatActionExecutor.php`

**Changes:**
1. Replace imports from `App\Services\Messenger\ActionHandlers\*` with `App\Messenger\ChatAction\Handlers\*`
2. Replace import of `ActionHandlerInterface` with `ChatActionHandlerInterface`
3. Update handler map values to reference `App\Messenger\ChatAction\Handlers\*` classes
4. Modify `resolveHandler()` return type to `?ChatActionHandlerInterface`
5. Modify `execute()` method to construct `ChatActionContext` instead of passing raw parameters:
   - Create `ChatActionContext` with: `$user`, `$action->parameters ?? []`, `$action->action_type`, `$targetJob`, `$targetRun`, `$confirmed`
   - Resolve `$targetJob` by querying `AgentJob::find($parameters['job_id'])` when present
   - Resolve `$targetRun` by querying `AgentJobRun::find($parameters['run_id'])` when present
   - Extract `$confirmed` from `$action->parameters['confirmed'] ?? false`
6. Update handler invocation from `$handler->handle($action->parameters ?? [], $user)` to `$handler->handle($context)`
7. Remove `validate()` call since new handlers don't expose this method (validation is internal)
8. Update return type mapping: convert `ChatActionResult` to `ActionResult` for backward compatibility:
   - Map `ChatActionResult::isSuccess()` to `ActionResult::success()` or `ActionResult::failure()`
   - Map `ChatActionResult::getMessage()` and `ChatActionResult::getData()`

**Dependencies:** None (first task)

### 1.2 Wire Policy Validator from Consolidated Namespace

**File:** `app/Services/Messenger/ChatActionExecutor.php`

**Changes:**
1. Replace injection of `App\Services\Messenger\ChatActionPolicyValidator` with `App\Messenger\Validation\ChatActionPolicyValidator`
2. Update `validate()` calls to use the new validator's methods:
   - For job actions: call `$policyValidator->authorizeJobById($user, $parameters['job_id'])`
   - For run actions: call `$policyValidator->authorizeRunById($user, $parameters['run_id'])`
3. Map `AuthorizationResult` to `PolicyValidationResult` for executor compatibility:
   - `AuthorizationResult::isAuthorized()` → continue
   - `AuthorizationResult::getMessage()` → `PolicyValidationResult::denied()`

**Dependencies:** Section 1.1

### 1.3 Delete Stub Handlers

**Files to delete:**
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

**After deletion:** Remove empty directory `app/Services/Messenger/ActionHandlers/`

**Dependencies:** Section 1.2

---

## Section 2: Policy Enforcement Implementation

### 2.1 Remove Placeholder Code from Legacy Validator

**File:** `app/Services/Messenger/ChatActionPolicyValidator.php`

**Changes at line 106:**
- Remove `// TODO: Integrate with existing permission system when available` comment
- Remove placeholder example permission checks comments (lines 109-112)
- Keep the method returning `PolicyValidationResult::allowed()` (user permissions are not enforced per constraints)

**Changes at line 144:**
- Remove `// TODO: Implement actual ownership checks when Job/Run models are available` comment
- Remove placeholder simulation code (lines 146-164)
- Replace with actual ownership checks:
  ```php
  if ($jobId !== null) {
      $job = AgentJob::find($jobId);
      if (!$job || $job->user_id !== $user->id) {
          return PolicyValidationResult::denied('Permission denied');
      }
  }
  if ($runId !== null) {
      $run = AgentJobRun::with('job')->find($runId);
      if (!$run || !$run->job || $run->job->user_id !== $user->id) {
          return PolicyValidationResult::denied('Permission denied');
      }
  }
  ```

**Changes at line 189:**
- Remove `// TODO: Integrate with existing CommandPolicy, PathPolicy, EnvPolicy` comment
- Remove placeholder comment on line 190
- Keep basic safety checks (they are production-ready)

**Dependencies:** Section 1.3

---

## Section 3: Compliance Implementation

### 3.1 Implement Compliance Metrics Endpoint

**File:** `app/Http/Controllers/Api/V1/ComplianceController.php`

**Changes at `metrics()` method (line 24-34):**
1. Remove `// TODO: Implement actual metrics collection from events/database` comment
2. Inject `Illuminate\Http\Request $request` to access authenticated user
3. Get tenant ID from authenticated user: `$tenantId = $request->user()->tenant_id ?? $request->user()->id`
4. Query metrics:
   ```php
   $totalJobs = AgentJob::where('user_id', $tenantId)->count();
   $totalRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('user_id', $tenantId))->count();
   $successfulRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('user_id', $tenantId))
       ->where('status', AgentJobRun::STATUS_SUCCEEDED)->count();
   $failedRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('user_id', $tenantId))
       ->where('status', AgentJobRun::STATUS_FAILED)->count();
   $activeRuns = AgentJobRun::whereHas('job', fn($q) => $q->where('user_id', $tenantId))
       ->active()->count();
   ```
5. Calculate rates:
   ```php
   $successRate = $totalRuns > 0 ? round($successfulRuns / $totalRuns, 4) : null;
   $failureRate = $totalRuns > 0 ? round($failedRuns / $totalRuns, 4) : null;
   ```
6. Return response:
   ```php
   return response()->json([
       'total_jobs' => $totalJobs,
       'total_runs' => $totalRuns,
       'success_rate' => $successRate,
       'failure_rate' => $failureRate,
       'active_runs' => $activeRuns,
   ]);
   ```

**Dependencies:** None

### 3.2 Remove Tenant Override Capability

**File:** `app/Support/Compliance/ComplianceFlagResolver.php`

**Changes at `getTenantOverride()` method (lines 124-129):**
1. Remove `// TODO: Implement database lookup for tenant overrides` comment
2. Keep method returning `null` (this is the desired behavior per constraints)
3. Update docblock to document intentional behavior: "Tenant overrides are not supported. All tenants follow identical compliance rules."

**Dependencies:** None

---

## Section 4: Delegation Reconciler Auto-Retry

### 4.1 Implement Blocked Task Retry Logic

**File:** `app/Support/Delegation/DelegationReconciler.php`

**Changes at `retryBlockedTasks()` method (lines 83-97):**
1. Remove TODO comment and placeholder loop (lines 90-96)
2. Define retry constants:
   ```php
   private const MAX_RETRY_ATTEMPTS = 3;
   private const RETRY_DELAYS_MINUTES = [1, 5, 15];
   ```
3. Add retry tracking fields to query:
   ```php
   $blockedTasks = DelegationTask::query()
       ->where('status', DelegationTask::STATUS_BLOCKED)
       ->whereHas('graph', fn ($q) => $q->whereIn('status', DelegationGraph::ACTIVE_STATUSES))
       ->where(function ($q) {
           $q->whereNull('metadata_json->retry_count')
             ->orWhereRaw("COALESCE(JSON_EXTRACT(metadata_json, '$.retry_count'), 0) < ?", [self::MAX_RETRY_ATTEMPTS]);
       })
       ->get();
   ```
4. Implement retry loop:
   ```php
   foreach ($blockedTasks as $task) {
       $metadata = $task->metadata_json ?? [];
       $retryCount = $metadata['retry_count'] ?? 0;
       $lastRetryAt = isset($metadata['last_retry_at']) ? Carbon::parse($metadata['last_retry_at']) : null;
       
       $delayMinutes = self::RETRY_DELAYS_MINUTES[$retryCount] ?? 15;
       $nextRetryAt = $lastRetryAt ? $lastRetryAt->addMinutes($delayMinutes) : now();
       
       if (now()->lt($nextRetryAt)) {
           continue; // Not yet time to retry
       }
       
       if ($retryCount >= self::MAX_RETRY_ATTEMPTS) {
           $task->update([
               'status' => DelegationTask::STATUS_FAILED,
               'finished_at' => now(),
               'error_code' => 'MAX_RETRIES_EXCEEDED',
               'error_summary' => 'Task permanently failed after 3 retry attempts',
           ]);
           continue;
       }
       
       $task->update([
           'metadata_json' => array_merge($metadata, [
               'retry_count' => $retryCount + 1,
               'last_retry_at' => now()->toISOString(),
           ]),
       ]);
       
       // Re-queue for assignment attempt
       dispatch(new RetryBlockedTaskJob($task->id))->delay(now());
   }
   ```

**Dependencies:** None

### 4.2 Implement Stuck Graph Auto-Completion

**File:** `app/Support/Delegation/DelegationReconciler.php`

**Changes at `handleStuckGraphs()` method (lines 105-129):**
1. Remove TODO comment and placeholder loop (lines 121-128)
2. Implement completion detection:
   ```php
   foreach ($stuckGraphs as $graph) {
       $tasks = $graph->tasks;
       $allSucceeded = $tasks->every(fn ($t) => $t->status === DelegationTask::STATUS_SUCCEEDED);
       $anyFailed = $tasks->contains(fn ($t) => $t->status === DelegationTask::STATUS_FAILED);
       $anyCancelled = $tasks->contains(fn ($t) => $t->status === DelegationTask::STATUS_CANCELLED);
       
       $metadata = $graph->metadata_json ?? [];
       $retryCount = $metadata['stuck_retry_count'] ?? 0;
       
       if ($retryCount >= self::MAX_RETRY_ATTEMPTS) {
           $graph->update([
               'status' => DelegationGraph::STATUS_FAILED,
               'finished_at' => now(),
               'error_summary' => 'Graph stuck after 3 recovery attempts',
           ]);
           continue;
       }
       
       if ($allSucceeded) {
           $graph->update([
               'status' => DelegationGraph::STATUS_SUCCEEDED,
               'finished_at' => now(),
           ]);
       } elseif ($anyFailed) {
           $graph->update([
               'status' => DelegationGraph::STATUS_FAILED,
               'finished_at' => now(),
           ]);
       } else {
           $graph->update([
               'metadata_json' => array_merge($metadata, [
                   'stuck_retry_count' => $retryCount + 1,
                   'last_stuck_check_at' => now()->toISOString(),
               ]),
           ]);
       }
   }
   ```

**Dependencies:** Section 4.1

---

## Section 5: NL Schedule Parser Clarification Flow

### 5.1 Create ClarificationRequired Result Type

**New file:** `app/Support/NlSchedule/ClarificationRequired.php`

**Contents:**
```php
<?php

namespace App\Support\NlSchedule;

final readonly class ClarificationRequired
{
    public function __construct(
        public string $parseAttemptId,
        public string $interpretation,
        public array $alternatives,
        public float $confidence,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => 'clarification_required',
            'parse_attempt_id' => $this->parseAttemptId,
            'interpretation' => $this->interpretation,
            'alternatives' => $this->alternatives,
            'confidence' => $this->confidence,
        ];
    }
}
```

**Dependencies:** None

### 5.2 Implement Clarification Flow in Parser Service

**File:** `app/Support/NlSchedule/NlScheduleParserService.php`

**Changes at low-confidence branch (lines 107-129):**
1. Remove `// Note: Actual job dispatch will be added in later task` comment (line 122)
2. Change status from `'queued'` to `'clarification_required'` when creating attempt
3. Return structured clarification response:
   ```php
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
       'interpretation' => $result->humanReadable ?? $result->cronExpression,
       'alternatives' => $result->alternatives ?? [],
       'confidence' => $result->confidence,
       'message' => 'Please confirm or clarify your schedule. Did you mean: ' . ($result->humanReadable ?? $result->cronExpression) . '?',
   ];
   ```

**Dependencies:** Section 5.1

---

## Section 6: Trust Score Calculator

### 6.1 Replace Placeholder Metrics with Real Calculations

**File:** `app/Support/Delegation/TrustScoreCalculator.php`

**Changes at `aggregateMetrics()` method (lines 116-119):**
1. Replace hardcoded placeholder values with computed metrics
2. Current placeholders: `situationCorrectRate: 0.8`, `taskCorrectRate: 0.8`, `actionCorrectRate: 0.8`, `resultCorrectRate: 0.8`
3. Implementation per spec (success rate 40%, account age 30%, behavioral signals 30%):

**Add new method for behavioral signals:**
```php
private function getBehavioralScore(string $runnerType, ?int $jobId): float
{
    $query = AgentJobRun::query()
        ->join('agent_jobs', 'agent_job_runs.agent_job_id', '=', 'agent_jobs.id')
        ->where('agent_jobs.runner_type', $runnerType);
    
    if ($jobId !== null) {
        $query->where('agent_job_runs.agent_job_id', $jobId);
    }
    
    $total = $query->count();
    if ($total === 0) return 0.5; // Default for no data
    
    $errorCount = (clone $query)->where('agent_job_runs.status', AgentJobRun::STATUS_FAILED)->count();
    $errorRate = $errorCount / $total;
    
    return max(0, 1 - $errorRate);
}
```

**Update `aggregateMetrics()` to compute real values:**
```php
// Replace lines 116-119 with:
$situationCorrectRate = $starCompleted > 0 ? $starCompleted / $total : 0.5;
$taskCorrectRate = $successful / max(1, $total);
$actionCorrectRate = $taskCorrectRate; // Derived from task success
$resultCorrectRate = $taskCorrectRate; // Derived from task success

return new StarMetrics(
    starCompletionRate: $starCompleted / $total,
    situationCorrectRate: $situationCorrectRate,
    taskCorrectRate: $taskCorrectRate,
    actionCorrectRate: $actionCorrectRate,
    resultCorrectRate: $resultCorrectRate,
    firstPassSuccessRate: ($successful - $retrySuccessful) / max(1, $total - $retryAttempted),
    recoveryRate: $retryAttempted > 0 ? $retrySuccessful / $retryAttempted : 0,
    failureModeDistribution: array_map(fn ($c) => $c / max(1, $total), $failureModes),
    sampleSize: $total
);
```

**Dependencies:** None

---

## Section 7: AI Critic Output Retrieval

### 7.1 Implement Multi-Source Output Fallback

**File:** `app/Jobs/AiCriticCompletedJob.php`

**Changes at `getRunOutput()` method (lines 127-132):**
1. Remove `// In a real implementation, this would fetch the actual output` comment
2. Implement fallback chain:
   ```php
   private function getRunOutput(AgentJobRun $run): string
   {
       // 1. Try canonical stdout file
       $stdoutPath = storage_path("runs/{$run->id}/stdout.log");
       if (file_exists($stdoutPath)) {
           $content = file_get_contents($stdoutPath);
           if (!empty($content)) {
               return $content;
           }
       }
       
       // 2. Try metadata_json output
       $metadataOutput = $run->metadata_json['output'] ?? '';
       if (!empty($metadataOutput)) {
           return $metadataOutput;
       }
       
       // 3. Try artifacts
       if ($run->relationLoaded('artifacts') || $run->artifacts()->exists()) {
           $artifacts = $run->artifacts()->get();
           $combined = $artifacts
               ->filter(fn ($a) => str_starts_with($a->mime_type ?? '', 'text/'))
               ->map(fn ($a) => $a->content ?? '')
               ->implode("\n");
           if (!empty($combined)) {
               return $combined;
           }
       }
       
       return '';
   }
   ```

**Dependencies:** None

---

## Section 8: Inbound Attachment Processing

### 8.1 Implement Attachment Download and Storage

**File:** `app/Jobs/Messenger/ProcessInboundMessage.php`

**Changes at `extractAttachmentIds()` method (lines 259-271):**
1. Remove `// For now, just return the provider file IDs` comment (line 265)
2. Remove `// Attachment processing will be handled separately` comment (line 266)
3. Rename method to `processAttachments()` and change return type
4. Implement full attachment processing:

**New method:**
```php
/**
 * @param NormalizedMessage $message
 * @param ConnectorAccount $account
 * @return array<int>|null Array of MessageAttachment IDs
 */
private function processAttachments(NormalizedMessage $message, ConnectorAccount $account): ?array
{
    if (empty($message->attachments)) {
        return null;
    }

    $attachmentIds = [];
    $adapter = app(ConnectorManager::class)->resolve($this->provider);
    
    foreach ($message->attachments as $attachment) {
        try {
            // Validate file type and size
            $allowedTypes = config('messenger.attachments.allowed_types', ['image/*', 'application/pdf', 'text/*']);
            $maxSize = config('messenger.attachments.max_size_bytes', 10 * 1024 * 1024); // 10MB default
            
            if ($attachment->size > $maxSize) {
                Log::warning('ProcessInboundMessage: Attachment too large', [
                    'file_id' => $attachment->providerFileId,
                    'size' => $attachment->size,
                    'max' => $maxSize,
                ]);
                continue;
            }
            
            // Download file from provider
            $content = $adapter->downloadFile($account, $attachment->providerFileId);
            
            if ($content === null) {
                Log::warning('ProcessInboundMessage: Failed to download attachment', [
                    'file_id' => $attachment->providerFileId,
                ]);
                $this->createFailedAttachment($attachment, 'download_failed');
                continue;
            }
            
            // Store locally
            $filename = Str::uuid() . '_' . ($attachment->filename ?? 'attachment');
            $path = "attachments/{$account->id}/" . now()->format('Y/m/d') . "/{$filename}";
            
            Storage::disk(config('messenger.attachments.disk', 'local'))->put($path, $content);
            
            // Create MessageAttachment record
            $record = MessageAttachment::create([
                'connector_account_id' => $account->id,
                'provider_file_id' => $attachment->providerFileId,
                'local_path' => $path,
                'mime_type' => $attachment->mimeType ?? 'application/octet-stream',
                'size' => strlen($content),
                'filename' => $attachment->filename,
                'status' => 'processed',
            ]);
            
            $attachmentIds[] = $record->id;
            
        } catch (\Throwable $e) {
            Log::error('ProcessInboundMessage: Attachment processing failed', [
                'file_id' => $attachment->providerFileId,
                'error' => $e->getMessage(),
            ]);
            $this->createFailedAttachment($attachment, 'processing_error');
        }
    }
    
    return empty($attachmentIds) ? null : $attachmentIds;
}

private function createFailedAttachment($attachment, string $reason): void
{
    MessageAttachment::create([
        'provider_file_id' => $attachment->providerFileId,
        'status' => 'failed_to_download',
        'metadata_json' => ['failure_reason' => $reason],
    ]);
}
```

**Update `handle()` method:**
- Change line 122: `'attachment_ids' => $this->extractAttachmentIds($normalizedMessage),`
- To: `'attachment_ids' => $this->processAttachments($normalizedMessage, $account),`

**Dependencies:** None

---

## Section 9: Slack Socket Worker Graceful Drain

### 9.1 Implement Drain with Pending Operation Tracking

**File:** `app/Messenger/Gateway/Workers/SlackSocketWorker.php`

**Add new properties after line 57:**
```php
private int $pendingOperations = 0;
private int $drainTimeoutSeconds = 30;
```

**Changes at `drain()` method (lines 153-163):**
1. Remove `// In a real implementation, we would wait for in-flight messages` comment
2. Remove `// For now, we just set the flag to stop processing new events` comment
3. Implement graceful drain:
   ```php
   public function drain(): void
   {
       Log::info('SlackSocketWorker draining', [
           'account_id' => $this->account->id,
           'pending_operations' => $this->pendingOperations,
       ]);

       $this->draining = true;
       $this->drainTimeoutSeconds = (int) config('messenger.slack.drain_timeout_seconds', 30);
       
       // Stop accepting new messages by closing the WebSocket
       // but don't force-close yet - wait for pending operations
       
       $startTime = time();
       $checkInterval = 0.1; // 100ms
       
       $this->loop->addPeriodicTimer($checkInterval, function ($timer) use ($startTime) {
           if ($this->pendingOperations === 0) {
               $this->loop->cancelTimer($timer);
               $this->stop();
               return;
           }
           
           if ((time() - $startTime) >= $this->drainTimeoutSeconds) {
               Log::warning('SlackSocketWorker drain timeout exceeded', [
                   'account_id' => $this->account->id,
                   'pending_operations' => $this->pendingOperations,
                   'timeout' => $this->drainTimeoutSeconds,
               ]);
               $this->loop->cancelTimer($timer);
               $this->stop();
           }
       });
   }
   ```

**Update `dispatchEvent()` method to track operations:**
```php
private function dispatchEvent(array $payload): void
{
    $this->pendingOperations++;
    
    Log::debug('Dispatching event to ProcessInboundMessage', [
        'account_id' => $this->account->id,
        'event_type' => $payload['event']['type'] ?? 'unknown',
        'pending_operations' => $this->pendingOperations,
    ]);

    ProcessInboundMessage::dispatch(
        connectorAccountId: (string) $this->account->id,
        provider: $this->account->provider,
        payload: $payload
    )->then(function () {
        $this->pendingOperations--;
    })->catch(function () {
        $this->pendingOperations--;
    });
}
```

**Dependencies:** None

---

## Section 10: Final Cleanup

### 10.1 Audit and Remove Remaining Placeholders

**Files to audit for TODO/PLACEHOLDER removal:**
- `app/Services/Messenger/ChatActionPolicyValidator.php` — confirm all TODOs removed
- `app/Support/Compliance/ComplianceFlagResolver.php` — confirm TODO removed
- `app/Support/Delegation/DelegationReconciler.php` — confirm TODOs removed
- `app/Support/NlSchedule/NlScheduleParserService.php` — confirm Note comment removed
- `app/Support/Delegation/TrustScoreCalculator.php` — confirm placeholder comment removed (line 116)
- `app/Jobs/AiCriticCompletedJob.php` — confirm comment removed
- `app/Jobs/Messenger/ProcessInboundMessage.php` — confirm comments removed
- `app/Messenger/Gateway/Workers/SlackSocketWorker.php` — confirm comments removed
- `app/Http/Controllers/Api/V1/ComplianceController.php` — confirm TODO removed

**Dependencies:** All previous sections

---

## Verification Checklist

### Handler Consolidation
- [ ] ChatActionExecutor imports handlers from `App\Messenger\ChatAction\Handlers`
- [ ] ChatActionExecutor constructs ChatActionContext with all required fields
- [ ] All 9 handlers (JobsList, JobsCreate, JobsUpdate, JobsDelete, RunsListActive, RunsStop, RunsRetry, RunsRunNow, RunsSteer) execute real DB operations
- [ ] All stub handlers deleted from `App\Services\Messenger\ActionHandlers\`
- [ ] Empty directory removed

### Policy Enforcement
- [ ] ChatActionPolicyValidator.validateResourceOwnership() queries AgentJob and AgentJobRun
- [ ] Denies access when user_id does not match resource owner
- [ ] Permits access when user_id matches resource owner

### Compliance
- [ ] ComplianceController.metrics() returns total_jobs, total_runs, success_rate, failure_rate, active_runs
- [ ] ComplianceFlagResolver.getTenantOverride() returns null with documented rationale

### Delegation
- [ ] DelegationReconciler.retryBlockedTasks() implements 3-retry logic with 1/5/15 minute delays
- [ ] DelegationReconciler marks tasks as FAILED after max retries
- [ ] DelegationReconciler.handleStuckGraphs() transitions to appropriate terminal state

### NL Schedule Parser
- [ ] NlScheduleParserService returns clarification_required status for low-confidence parses
- [ ] Response includes interpretation and alternatives

### Trust Scoring
- [ ] TrustScoreCalculator computes situationCorrectRate, taskCorrectRate, actionCorrectRate, resultCorrectRate from actual data
- [ ] No hardcoded 0.8 values remain

### AI Critic
- [ ] AiCriticCompletedJob.getRunOutput() tries stdout file first
- [ ] Falls back to metadata_json then artifacts

### Attachments
- [ ] ProcessInboundMessage downloads files from provider
- [ ] Creates MessageAttachment records with local path, MIME type, size
- [ ] Handles download failures gracefully

### Socket Worker
- [ ] SlackSocketWorker.drain() tracks pendingOperations count
- [ ] Waits for operations to complete up to timeout
- [ ] Logs warning if timeout exceeded

### Cleanup
- [ ] No TODO comments remain in affected files
- [ ] No PLACEHOLDER comments remain in affected files
- [ ] No stub/fake data returns remain

## Sections

- Section 1: Messenger Handler Consolidation
- Section 2: Policy Enforcement Implementation
- Section 3: Compliance Implementation
- Section 4: Delegation Reconciler Auto-Retry
- Section 5: NL Schedule Parser Clarification Flow
- Section 6: Trust Score Calculator
- Section 7: AI Critic Output Retrieval
- Section 8: Inbound Attachment Processing
- Section 9: Slack Socket Worker Graceful Drain
- Section 10: Final Cleanup


## Risks

- Handler consolidation may break existing tests that expect ActionResult return type; mapping layer required
- ChatActionContext construction requires resolving AgentJob/AgentJobRun from database which adds latency to action execution
- Attachment download is synchronous within ProcessInboundMessage job; large files may cause job timeout
- DelegationReconciler retry intervals assume database timestamps are accurate; clock skew could cause premature retries
- SlackSocketWorker drain uses ReactPHP timer which requires event loop to be running; won't work if loop is stopped externally
- Trust score placeholder replacement changes scoring output; may affect downstream decisions until recalibrated
- ProcessInboundMessage attachment processing requires ConnectorAdapter to implement downloadFile method; verify all adapters support this


## Assumptions

- AgentJob model has user_id column for ownership verification
- AgentJobRun model has job relationship and active() scope defined
- MessageAttachment model exists or will be created with required columns
- Storage disk configuration for attachments exists or will be added
- ConnectorAdapter interface includes downloadFile method for all providers
- ReactPHP event loop remains running during drain operation
- AgentJobRun has artifacts relationship defined
- Stdout logs are stored at storage/runs/{run_id}/stdout.log path
- RetryBlockedTaskJob job class exists or will be created for retry dispatching
- DelegationTask and DelegationGraph have metadata_json column as JSON/array type

