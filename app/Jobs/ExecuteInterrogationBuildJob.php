<?php

namespace App\Jobs;

use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
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

    public function handle(BuildTaskRunFactory $runFactory): void
    {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $writer = new InterrogationEventWriter($session);
        $build = $this->buildMetadata($session);

        if (($build['status'] ?? null) !== 'running') {
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
                self::dispatch((int) $session->id)->delay(now()->addSeconds(2));

                return;
            }

            $finalized = $this->finalizeTaskFromRun($session, $activeTask, $run, $writer);

            if ($finalized) {
                self::dispatch((int) $session->id)->delay(now()->addSecond());
            }

            return;
        }

        $nextTask = $session->buildTasks()
            ->where('status', InterrogationBuildTask::STATUS_PENDING)
            ->orderBy('sequence')
            ->first();

        if ($nextTask !== null) {
            $run = $runFactory->create($session, $nextTask);

            $nextTask->status = InterrogationBuildTask::STATUS_IN_PROGRESS;
            $nextTask->attempt_count = (int) $nextTask->attempt_count + 1;
            $nextTask->agent_job_run_id = $run->id;
            $nextTask->last_error = null;
            $nextTask->started_at = $nextTask->started_at ?? CarbonImmutable::now('UTC');
            $nextTask->finished_at = null;
            $nextTask->save();

            $this->persistActivePointers($session, $nextTask, $run);

            $writer->appendSystem([
                'notice' => 'build_task_started',
                'task_id' => $nextTask->id,
                'sequence' => $nextTask->sequence,
                'title' => $nextTask->title,
                'run_id' => $run->id,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            self::dispatch((int) $session->id)->delay(now()->addSeconds(2));

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

            return true;
        }

        $runStatus = (string) $run->status;
        $runMetadata = is_array($run->metadata_json) ? $run->metadata_json : [];

        if ($runStatus === AgentJobRun::STATUS_SUCCEEDED) {
            $task->status = InterrogationBuildTask::STATUS_COMPLETED;
            $task->last_error = null;
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $this->saveBuildMetadata($session, $build);

            $writer->appendSystem([
                'notice' => 'build_task_completed',
                'task_id' => $task->id,
                'sequence' => $task->sequence,
                'title' => $task->title,
                'run_id' => $run->id,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            return true;
        }

        if (($runMetadata['rate_limit_detected'] ?? false) === true) {
            $task->status = InterrogationBuildTask::STATUS_BLOCKED;
            $task->last_error = trim((string) ($run->error_summary ?? 'Rate limit detected while executing build task.'));
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $build['status'] = 'paused';
            $build['pause_reason'] = 'rate_limit';
            $build['rate_limit_reset_at'] = $runMetadata['rate_limit_reset_at'] ?? $runMetadata['rate_limit_hold_until'] ?? null;
            $build['rate_limit_excerpt'] = $runMetadata['rate_limit_excerpt'] ?? null;
            $build['paused_at'] = CarbonImmutable::now('UTC')->toIso8601String();
            $this->saveBuildMetadata($session, $build);

            $writer->appendSystem([
                'notice' => 'build_paused_rate_limit',
                'task_id' => $task->id,
                'run_id' => $run->id,
                'reset_at' => $build['rate_limit_reset_at'] ?? null,
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            return false;
        }

        if (($build['status'] ?? null) === 'paused' && in_array($runStatus, [
            AgentJobRun::STATUS_KILLED,
            AgentJobRun::STATUS_STOPPING,
        ], true)) {
            $task->status = InterrogationBuildTask::STATUS_BLOCKED;
            $task->last_error = 'Execution paused before completion.';
            $task->finished_at = CarbonImmutable::now('UTC');
            $task->save();

            $this->saveBuildMetadata($session, $build);

            return false;
        }

        $task->status = InterrogationBuildTask::STATUS_FAILED;
        $task->last_error = trim((string) ($run->error_summary ?? 'Build task execution failed.'));
        $task->finished_at = CarbonImmutable::now('UTC');
        $task->save();

        $build['status'] = 'failed';
        $build['error'] = substr(trim((string) ($run->error_summary ?? 'Build task execution failed.')), 0, 1000);
        $build['failed_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $this->saveBuildMetadata($session, $build);

        $writer->appendError([
            'code' => 'BUILD_TASK_FAILED',
            'message' => $task->last_error,
            'task_id' => $task->id,
            'run_id' => $run->id,
            'run_status' => $runStatus,
        ]);

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
}
