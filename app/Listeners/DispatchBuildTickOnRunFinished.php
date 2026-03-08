<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgentJobRunFinished;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Models\InterrogationBuildTask;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchBuildTickOnRunFinished implements ShouldQueue
{
    public string $connection = 'redis';

    public string $queue = 'default';

    public function handle(AgentJobRunFinished $event): void
    {
        $run = $event->run;
        $metadata = is_array($run->metadata_json) ? $run->metadata_json : [];

        // Only act on runs associated with interrogation build tasks.
        $buildTaskId = (int) ($metadata['interrogation_build_task_id'] ?? 0);
        $sessionId = (int) ($metadata['interrogation_session_id'] ?? 0);

        if ($buildTaskId <= 0 || $sessionId <= 0) {
            return;
        }

        // Only dispatch if the build task is still in-progress and linked to
        // this run — avoids duplicate ticks when the self-sustaining loop is
        // already handling the transition.
        $task = InterrogationBuildTask::query()
            ->where('id', $buildTaskId)
            ->where('agent_job_run_id', $run->id)
            ->where('status', InterrogationBuildTask::STATUS_IN_PROGRESS)
            ->first();

        if ($task === null) {
            return;
        }

        // Dispatch a build tick with a short delay to allow the self-sustaining
        // loop a chance to process first. This acts as a safety net — if the
        // loop already finalized the task, the tick will no-op.
        ExecuteInterrogationBuildJob::dispatch($sessionId)
            ->delay(now()->addSeconds(3));
    }
}
