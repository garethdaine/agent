<?php

namespace App\Jobs;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Interrogation\BuildExecutionBackupService;
use App\Support\Interrogation\BuildTaskRunFactory;
use App\Support\Interrogation\InterrogationEventWriter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteInterrogationBuildJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $sessionId)
    {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(
        BuildTaskRunFactory $runFactory,
        BuildExecutionBackupService $backupService,
        OrchestrationPolicyServiceContract $policyService,
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $writer = new InterrogationEventWriter($session);
        $build = $this->buildMetadata($session);

        if (($build['status'] ?? null) !== 'running') {
            return;
        }

        if (! $this->ensureBackupBeforeBuildStart($session, $writer, $backupService)) {
            return;
        }

        $activeTask = $session->buildTasks()
            ->with('run')
            ->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)
            ->orderBy('sequence')
            ->first();

        if ($activeTask !== null) {
            $run = $activeTask->run;

            if ($run !== null && in_array((string) $run->status, AgentJobRun::ACTIVE_STATUSES, true)) {
                $this->persistActivePointers($session, $activeTask, $run);
                $this->dispatchFollowUpBuildTick((int) $session->id, 2, $writer);

                return;
            }

            $finalized = $this->finalizeTaskFromRun($session, $activeTask, $run, $writer, $policyService);

            if ($finalized) {
                $this->dispatchFollowUpBuildTick((int) $session->id, 1, $writer);
            }

            return;
        }

        $hasTerminalBlockingTask = $session->buildTasks()
            ->whereIn('status', [
                InterrogationBuildTask::STATUS_FAILED,
                InterrogationBuildTask::STATUS_BLOCKED,
            ])
            ->exists();

        if ($hasTerminalBlockingTask) {
            $this->finalizeBuildLifecycle($session, $writer);

            return;
        }

        $nextTask = $session->buildTasks()
            ->where('status', InterrogationBuildTask::STATUS_PENDING)
            ->orderBy('sequence')
            ->first();

        if ($nextTask !== null) {
            $nextAttempt = (int) $nextTask->attempt_count + 1;

            if (! $this->ensureBackupBeforeTaskStart($session, $nextTask, $nextAttempt, $writer, $backupService)) {
                return;
            }

            $run = $runFactory->create($session, $nextTask);

            $nextTask->status = InterrogationBuildTask::STATUS_IN_PROGRESS;
            $nextTask->attempt_count = $nextAttempt;
            $nextTask->agent_job_run_id = $run->id;
            $nextTask->last_error = null;
            $nextTask->started_at = $nextTask->started_at ?? CarbonImmutable::now('UTC');
            $nextTask->finished_at = null;
            $nextTask->save();
            $this->queueTaskProviderStatusSync($session, $nextTask);

            $this->persistActivePointers($session, $nextTask, $run);

            $writer->appendSystem([
                'notice' => 'build_task_started',
                'task_id' => $nextTask->id,
                'sequence' => $nextTask->sequence,
                'title' => $nextTask->title,
                'run_id' => $run->id,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            $this->dispatchFollowUpBuildTick((int) $session->id, 2, $writer);

            return;
        }

        $this->finalizeBuildLifecycle($session, $writer);
    }

    private function finalizeBuildLifecycle(InterrogationSession $session, InterrogationEventWriter $writer): void
    {
        $tasks = $session->buildTasks()->get();

        $hasFailed = $tasks->contains(fn (InterrogationBuildTask $task): bool => $task->status === InterrogationBuildTask::STATUS_FAILED);
        $hasBlocked = $tasks->contains(fn (InterrogationBuildTask $task): bool => $task->status === InterrogationBuildTask::STATUS_BLOCKED);

        $build = $this->buildMetadata($session);
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;

        if ($hasFailed) {
            $build['status'] = 'failed';
            $build['finished_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $build['completion_summary'] = $this->buildCompletionSummary($tasks, false);
            $this->saveBuildMetadata($session, $build);

            $session->status = InterrogationSession::STATUS_FAILED;
            $session->error_code = 'BUILD_EXECUTION_FAILED';
            $session->error_summary = 'One or more build tasks failed.';
            $session->finished_at = CarbonImmutable::now('UTC');
            $session->save();

            $writer->appendSystem([
                'notice' => 'build_failed',
                'summary' => $build['completion_summary'],
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            return;
        }

        if ($hasBlocked) {
            $build['status'] = 'paused';
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $build['pause_reason'] = (string) ($build['pause_reason'] ?? 'blocked');
            $this->saveBuildMetadata($session, $build);

            if ($session->status !== InterrogationSession::STATUS_BUILD_EXECUTING) {
                $session->status = InterrogationSession::STATUS_BUILD_EXECUTING;
                $session->save();
            }

            $writer->appendSystem([
                'notice' => 'build_paused',
                'reason' => $build['pause_reason'],
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            return;
        }

        $build['status'] = 'completed';
        $build['finished_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['completion_summary'] = $this->buildCompletionSummary($tasks, true);
        $this->saveBuildMetadata($session, $build);

        $session->status = InterrogationSession::STATUS_COMPLETED;
        $session->phase = InterrogationSession::PHASE_BUILD_EXECUTION;
        $session->finished_at = CarbonImmutable::now('UTC');
        $session->error_code = null;
        $session->error_summary = null;
        $session->save();

        $writer->appendSystem([
            'notice' => 'build_completed',
            'summary' => $build['completion_summary'],
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);
    }

    private function finalizeTaskFromRun(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?AgentJobRun $run,
        InterrogationEventWriter $writer,
        OrchestrationPolicyServiceContract $policyService,
    ): bool {
        $build = $this->buildMetadata($session);
        $build['active_task_id'] = null;
        $build['active_run_id'] = null;

        if ($run === null) {
            $task->status = InterrogationBuildTask::STATUS_FAILED;
            $task->last_error = 'Run record not found for active build task.';
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $this->saveBuildMetadata($session, $build);

            $writer->appendError([
                'code' => 'BUILD_TASK_RUN_NOT_FOUND',
                'message' => 'Active build task run record is missing.',
                'task_id' => $task->id,
            ]);

            $this->queueTaskProviderStatusSync($session, $task, $writer);

            return true;
        }

        $runStatus = (string) $run->status;
        $runMetadata = is_array($run->metadata_json) ? $run->metadata_json : [];
        $permissionBlockerDetected = ($runMetadata['permission_blocker_detected'] ?? false) === true;
        $clarificationRequired = ($runMetadata['clarification_required'] ?? false) === true;

        if ($runStatus !== AgentJobRun::STATUS_SUCCEEDED && ($runMetadata['rate_limit_detected'] ?? false) === true) {
            $task->status = InterrogationBuildTask::STATUS_BLOCKED;
            $task->last_error = trim((string) ($run->error_summary ?? 'Rate limit detected while executing build task.'));
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $build['status'] = 'paused';
            $build['pause_reason'] = 'rate_limit';
            $build['rate_limit_reset_at'] = $runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? null;
            $build['rate_limit_excerpt'] = $runMetadata['rate_limit_excerpt'] ?? null;
            $build['permission_required'] = false;
            $build['permission_excerpt'] = null;
            $build['clarification_required'] = false;
            $build['clarification_excerpt'] = null;
            $build['active_task_id'] = (int) $task->id;
            $build['active_run_id'] = (int) $run->id;
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $this->saveBuildMetadata($session, $build);

            $writer->appendSystem([
                'notice' => 'build_paused_rate_limit',
                'task_id' => $task->id,
                'run_id' => $run->id,
                'reset_at' => $build['rate_limit_reset_at'] ?? null,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            $this->queueTaskProviderStatusSync($session, $task, $writer);

            return false;
        }

        if ($clarificationRequired) {
            $task->status = InterrogationBuildTask::STATUS_BLOCKED;
            $task->last_error = trim((string) ($run->error_summary ?? 'Clarification required before continuing this task.'));
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $build['status'] = 'paused';
            $build['pause_reason'] = 'clarification';
            $build['permission_required'] = false;
            $build['permission_excerpt'] = null;
            $build['clarification_required'] = true;
            $build['clarification_excerpt'] = is_string($runMetadata['clarification_excerpt'] ?? null)
                ? $runMetadata['clarification_excerpt']
                : null;
            $build['rate_limit_detected'] = false;
            $build['rate_limit_reset_at'] = null;
            $build['rate_limit_excerpt'] = null;
            $build['active_task_id'] = (int) $task->id;
            $build['active_run_id'] = (int) $run->id;
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $this->saveBuildMetadata($session, $build);

            $writer->appendSystem([
                'notice' => 'build_paused_clarification',
                'task_id' => $task->id,
                'run_id' => $run->id,
                'message' => $build['clarification_excerpt'] ?? null,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            $this->queueTaskProviderStatusSync($session, $task, $writer);

            return false;
        }

        if ($runStatus === AgentJobRun::STATUS_SUCCEEDED) {
            // Check compliance gates before allowing completion
            if ($policyService->isEnabled()) {
                $completionResult = $policyService->evaluateCompletion($task);

                if (! $completionResult->canComplete) {
                    $task->status = InterrogationBuildTask::STATUS_BLOCKED;
                    $task->setComplianceMetadata([
                        'compliance_block_reason' => $completionResult->blockReason,
                        'compliance_remediation' => $completionResult->remediation,
                        'compliance_gates' => array_map(fn ($g) => [
                            'gate' => $g->gate,
                            'status' => $g->status,
                            'reason' => $g->reasonCode,
                        ], $completionResult->gates),
                    ]);
                    $task->finished_at = CarbonImmutable::now('UTC');
                    $task->save();

                    // Pause the build for compliance
                    $this->pauseBuildForCompliance($session, $task, $writer);

                    $this->queueTaskProviderStatusSync($session, $task, $writer);

                    return false;
                }
            }

            $task->status = InterrogationBuildTask::STATUS_COMPLETED;
            $task->last_error = null;
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $build['permission_required'] = false;
            $build['permission_excerpt'] = null;
            $build['clarification_required'] = false;
            $build['clarification_excerpt'] = null;
            $build['rate_limit_detected'] = false;
            $build['rate_limit_reset_at'] = null;
            $build['rate_limit_excerpt'] = null;
            $this->saveBuildMetadata($session, $build);

            $writer->appendSystem([
                'notice' => 'build_task_completed',
                'task_id' => $task->id,
                'sequence' => $task->sequence,
                'title' => $task->title,
                'run_id' => $run->id,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            $this->queueTaskProviderStatusSync($session, $task, $writer);

            return true;
        }

        if (($build['status'] ?? null) === 'paused' && in_array($runStatus, [
            AgentJobRun::STATUS_KILLED,
            AgentJobRun::STATUS_STOPPING,
        ], true)) {
            $task->status = InterrogationBuildTask::STATUS_BLOCKED;
            $task->last_error = 'Execution paused before completion.';
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $build['active_task_id'] = (int) $task->id;
            $build['active_run_id'] = (int) $run->id;
            $this->saveBuildMetadata($session, $build);

            $this->queueTaskProviderStatusSync($session, $task, $writer);

            return false;
        }

        $task->status = InterrogationBuildTask::STATUS_FAILED;
        $task->last_error = trim((string) ($run->error_summary ?? 'Build task execution failed.'));
        $task->finished_at = CarbonImmutable::now('UTC');
        $task->save();

        $build['status'] = 'failed';
        $build['error'] = substr(trim((string) ($run->error_summary ?? 'Build task execution failed.')), 0, 1000);
        $build['permission_required'] = $permissionBlockerDetected;
        $build['permission_excerpt'] = $permissionBlockerDetected
            ? (is_string($runMetadata['permission_blocker_excerpt'] ?? null) ? $runMetadata['permission_blocker_excerpt'] : null)
            : null;
        $build['clarification_required'] = $clarificationRequired;
        $build['clarification_excerpt'] = $clarificationRequired
            ? (is_string($runMetadata['clarification_excerpt'] ?? null) ? $runMetadata['clarification_excerpt'] : null)
            : null;
        $build['active_task_id'] = (int) $task->id;
        $build['active_run_id'] = (int) $run->id;
        $build['failed_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $this->saveBuildMetadata($session, $build);

        $writer->appendError([
            'code' => 'BUILD_TASK_FAILED',
            'message' => $task->last_error,
            'task_id' => $task->id,
            'run_id' => $run->id,
            'run_status' => $runStatus,
        ]);

        $this->queueTaskProviderStatusSync($session, $task, $writer);

        return false;
    }

    private function persistActivePointers(InterrogationSession $session, InterrogationBuildTask $task, AgentJobRun $run): void
    {
        $build = $this->buildMetadata($session);
        $build['status'] = 'running';
        $build['active_task_id'] = $task->id;
        $build['active_run_id'] = $run->id;
        $build['started_at'] = $build['started_at'] ?? CarbonImmutable::now('UTC')->toIso8601String();

        $runMetadata = is_array($run->metadata_json) ? $run->metadata_json : [];
        $build['approval_required'] = (bool) ($runMetadata['approval_required'] ?? false);
        $build['permission_required'] = (bool) ($runMetadata['permission_blocker_detected'] ?? false);
        $build['permission_excerpt'] = is_string($runMetadata['permission_blocker_excerpt'] ?? null)
            ? $runMetadata['permission_blocker_excerpt']
            : null;
        $build['clarification_required'] = (bool) ($runMetadata['clarification_required'] ?? false);
        $build['clarification_excerpt'] = is_string($runMetadata['clarification_excerpt'] ?? null)
            ? $runMetadata['clarification_excerpt']
            : null;
        $build['rate_limit_detected'] = (bool) ($runMetadata['rate_limit_detected'] ?? false);
        $build['rate_limit_reset_at'] = $runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? null;

        $this->saveBuildMetadata($session, $build);

        if ($session->status !== InterrogationSession::STATUS_BUILD_EXECUTING || (int) $session->phase !== InterrogationSession::PHASE_BUILD_EXECUTION) {
            $session->status = InterrogationSession::STATUS_BUILD_EXECUTING;
            $session->phase = InterrogationSession::PHASE_BUILD_EXECUTION;
            $session->save();
        }
    }

    private function buildCompletionSummary($tasks, bool $successful): string
    {
        $total = $tasks->count();
        $completed = $tasks->where('status', InterrogationBuildTask::STATUS_COMPLETED)->count();
        $failed = $tasks->where('status', InterrogationBuildTask::STATUS_FAILED)->count();
        $blocked = $tasks->where('status', InterrogationBuildTask::STATUS_BLOCKED)->count();
        $skipped = $tasks->where('status', InterrogationBuildTask::STATUS_SKIPPED)->count();

        if ($successful) {
            return sprintf(
                'Build execution completed successfully. %d/%d tasks completed (%d failed, %d blocked, %d skipped).',
                $completed,
                $total,
                $failed,
                $blocked,
                $skipped,
            );
        }

        return sprintf(
            'Build execution ended with failures. %d/%d tasks completed (%d failed, %d blocked, %d skipped).',
            $completed,
            $total,
            $failed,
            $blocked,
            $skipped,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        return is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function saveBuildMetadata(InterrogationSession $session, array $build): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();
    }

    private function pauseBuildForCompliance(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        InterrogationEventWriter $writer
    ): void {
        $build = $this->buildMetadata($session);
        $build['status'] = 'paused';
        $build['pause_reason'] = 'compliance_blocked';
        $build['blocked_task_id'] = (int) $task->id;
        $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['active_task_id'] = (int) $task->id;
        $build['active_run_id'] = $task->agent_job_run_id;

        $this->saveBuildMetadata($session, $build);

        $taskMetadata = is_array($task->metadata_json) ? $task->metadata_json : [];

        $writer->appendSystem([
            'notice' => 'build_paused_compliance',
            'task_id' => $task->id,
            'sequence' => $task->sequence,
            'block_reason' => $taskMetadata['compliance_block_reason'] ?? 'verification_incomplete',
            'remediation' => $taskMetadata['compliance_remediation'] ?? null,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);
    }

    private function queueTaskProviderStatusSync(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?InterrogationEventWriter $writer = null
    ): void {
        try {
            SyncInterrogationTaskStatusToTaskProviderJob::dispatch((int) $session->id, (int) $task->id);
        } catch (\Throwable $throwable) {
            report($throwable);

            if ($writer !== null) {
                $writer->appendSystem([
                    'notice' => 'build_task_provider_sync_queue_failed',
                    'task_id' => (int) $task->id,
                    'sequence' => (int) $task->sequence,
                    'error' => mb_substr(trim((string) $throwable->getMessage()), 0, 300),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]);
            }
        }
    }

    private function dispatchFollowUpBuildTick(
        int $sessionId,
        int $delaySeconds,
        ?InterrogationEventWriter $writer = null
    ): void {
        try {
            self::dispatch($sessionId)->delay(now()->addSeconds(max(0, $delaySeconds)));
        } catch (\Throwable $throwable) {
            report($throwable);

            if ($writer !== null) {
                $writer->appendSystem([
                    'notice' => 'build_follow_up_dispatch_failed',
                    'delay_seconds' => max(0, $delaySeconds),
                    'error' => mb_substr(trim((string) $throwable->getMessage()), 0, 300),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]);
            }
        }
    }

    private function ensureBackupBeforeBuildStart(
        InterrogationSession $session,
        InterrogationEventWriter $writer,
        BuildExecutionBackupService $backupService,
    ): bool {
        $build = $this->buildMetadata($session);
        $executionBackup = is_array($build['execution_backup'] ?? null) ? $build['execution_backup'] : [];
        $buildStartBackup = is_array($executionBackup['build_start'] ?? null) ? $executionBackup['build_start'] : [];
        $alreadyBackedUp = is_string($buildStartBackup['completed_at'] ?? null)
            && trim((string) $buildStartBackup['completed_at']) !== '';

        if ($alreadyBackedUp) {
            return true;
        }

        $result = $backupService->backupBeforeBuildStart($session);
        $attemptedAt = (string) ($result['attempted_at'] ?? CarbonImmutable::now('UTC')->toIso8601String());
        $message = trim((string) ($result['message'] ?? ''));
        $nowIso = CarbonImmutable::now('UTC')->toIso8601String();

        $executionBackup['build_start'] = [
            'status' => ($result['ok'] ?? false) ? 'succeeded' : 'failed',
            'attempted_at' => $attemptedAt,
            'completed_at' => ($result['ok'] ?? false) ? $attemptedAt : null,
            'message' => $message !== '' ? $message : null,
            'output' => is_string($result['output'] ?? null) && trim((string) $result['output']) !== ''
                ? trim((string) $result['output'])
                : null,
            'skipped' => ($result['skipped'] ?? false) === true,
        ];
        $build['execution_backup'] = $executionBackup;
        $build['updated_at'] = $nowIso;

        if (($result['ok'] ?? false) !== true) {
            $build['status'] = 'paused';
            $build['pause_reason'] = 'backup_failed';
            $build['error'] = mb_substr(
                'Database backup failed before build execution started. '.($message !== '' ? $message : 'Run again after resolving backup issues.'),
                0,
                1000
            );
            $build['paused_at'] = $nowIso;
            $build['active_task_id'] = null;
            $build['active_run_id'] = null;
            $this->saveBuildMetadata($session, $build);

            $writer->appendError([
                'code' => 'BUILD_BACKUP_FAILED',
                'message' => $build['error'],
                'scope' => 'build_start',
                'at' => $nowIso,
            ]);

            return false;
        }

        $this->saveBuildMetadata($session, $build);

        $writer->appendSystem([
            'notice' => 'build_backup_completed',
            'scope' => 'build_start',
            'message' => $message !== '' ? $message : 'Database backup completed before build start.',
            'skipped' => ($result['skipped'] ?? false) === true,
            'at' => $nowIso,
        ]);

        return true;
    }

    private function ensureBackupBeforeTaskStart(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        int $attempt,
        InterrogationEventWriter $writer,
        BuildExecutionBackupService $backupService,
    ): bool {
        $result = $backupService->backupBeforeTaskStart($session, $task, $attempt);
        $attemptedAt = (string) ($result['attempted_at'] ?? CarbonImmutable::now('UTC')->toIso8601String());
        $message = trim((string) ($result['message'] ?? ''));
        $nowIso = CarbonImmutable::now('UTC')->toIso8601String();

        $build = $this->buildMetadata($session);
        $executionBackup = is_array($build['execution_backup'] ?? null) ? $build['execution_backup'] : [];
        $taskBackups = is_array($executionBackup['task_starts'] ?? null) ? array_values($executionBackup['task_starts']) : [];
        $taskBackups[] = [
            'task_id' => (int) $task->id,
            'sequence' => (int) $task->sequence,
            'attempt' => $attempt,
            'status' => ($result['ok'] ?? false) ? 'succeeded' : 'failed',
            'attempted_at' => $attemptedAt,
            'message' => $message !== '' ? $message : null,
            'output' => is_string($result['output'] ?? null) && trim((string) $result['output']) !== ''
                ? trim((string) $result['output'])
                : null,
            'skipped' => ($result['skipped'] ?? false) === true,
        ];

        if (count($taskBackups) > 200) {
            $taskBackups = array_slice($taskBackups, -200);
        }

        $executionBackup['task_starts'] = $taskBackups;
        $build['execution_backup'] = $executionBackup;
        $build['updated_at'] = $nowIso;

        if (($result['ok'] ?? false) !== true) {
            $build['status'] = 'paused';
            $build['pause_reason'] = 'backup_failed';
            $build['error'] = mb_substr(
                sprintf(
                    'Database backup failed before starting task %d. %s',
                    (int) $task->sequence,
                    $message !== '' ? $message : 'Resume build after backup health is restored.'
                ),
                0,
                1000
            );
            $build['paused_at'] = $nowIso;
            $build['active_task_id'] = null;
            $build['active_run_id'] = null;
            $this->saveBuildMetadata($session, $build);

            $writer->appendError([
                'code' => 'BUILD_TASK_BACKUP_FAILED',
                'message' => $build['error'],
                'task_id' => $task->id,
                'sequence' => $task->sequence,
                'attempt' => $attempt,
                'at' => $nowIso,
            ]);

            return false;
        }

        $this->saveBuildMetadata($session, $build);

        $writer->appendSystem([
            'notice' => 'build_task_backup_completed',
            'task_id' => $task->id,
            'sequence' => $task->sequence,
            'attempt' => $attempt,
            'message' => $message !== '' ? $message : 'Database backup completed before task start.',
            'skipped' => ($result['skipped'] ?? false) === true,
            'at' => $nowIso,
        ]);

        return true;
    }
}
