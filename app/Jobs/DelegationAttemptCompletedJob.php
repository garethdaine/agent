<?php

namespace App\Jobs;

use App\Events\DelegationAttemptCompleted;
use App\Models\DelegationAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Handles the completion of a delegation attempt after the ExecuteAgentRunJob
 * has finished executing.
 *
 * This job is dispatched as part of a Bus::chain after ExecuteAgentRunJob,
 * ensuring it only runs after the agent run completes.
 *
 * Responsibilities:
 * - Update the attempt status based on the agent run result
 * - Calculate and record duration
 * - Fire DelegationAttemptCompleted event for the coordinator to handle
 */
class DelegationAttemptCompletedJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $backoff = 0;

    public int $timeout = 60;

    public function __construct(public int $attemptId)
    {
        $this->onConnection('redis');
        $this->onQueue('delegation');
    }

    public function handle(): void
    {
        $attempt = DelegationAttempt::with(['agentJobRun', 'task'])->find($this->attemptId);

        if ($attempt === null) {
            return;
        }

        $run = $attempt->agentJobRun;
        if ($run === null) {
            return;
        }

        // Map AgentJobRun status to DelegationAttempt status
        $status = $this->mapRunStatusToAttemptStatus($run->status);
        $errorCode = null;
        $errorSummary = null;

        if ($status === DelegationAttempt::STATUS_FAILED) {
            $errorCode = $run->error_code ?? 'EXECUTION_ERROR';
            $errorSummary = $run->error_summary ?? 'Agent run failed';
        }

        $attempt->update([
            'status' => $status,
            'finished_at' => now(),
            'duration_ms' => $run->duration_ms ?? ($attempt->started_at ? now()->diffInMilliseconds($attempt->started_at) : null),
            'error_code' => $errorCode,
            'error_summary' => $errorSummary,
        ]);

        // Fire event for coordinator and recovery handler to process
        event(new DelegationAttemptCompleted($attempt->fresh(['task', 'task.graph', 'profile'])));
    }

    /**
     * Map AgentJobRun status to DelegationAttempt status.
     *
     * @param  string  $runStatus  The AgentJobRun status
     * @return string The corresponding DelegationAttempt status
     */
    private function mapRunStatusToAttemptStatus(string $runStatus): string
    {
        return match ($runStatus) {
            'succeeded' => DelegationAttempt::STATUS_SUCCEEDED,
            'failed', 'killed', 'timed_out', 'skipped' => DelegationAttempt::STATUS_FAILED,
            default => DelegationAttempt::STATUS_FAILED,
        };
    }
}
