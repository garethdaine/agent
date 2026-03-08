<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgentJobRunFinished;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Models\InterrogationSession;
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

        // Check whether the build is still actively running rather than
        // checking task status.  The self-sustaining loop inside
        // ExecuteInterrogationBuildJob may have already finalized the task
        // (marking it completed) before this queued listener processes.  If
        // the loop's follow-up dispatch failed silently, this safety net is
        // the only mechanism that can restart the loop.  Checking task status
        // would cause us to bail out, leaving the build permanently stalled.
        // The build tick itself is idempotent — an extra dispatch is harmless.
        $session = InterrogationSession::query()->find($sessionId);

        if ($session === null) {
            return;
        }

        $sessionMeta = is_array($session->metadata_json) ? $session->metadata_json : [];
        $buildStatus = is_array($sessionMeta['build'] ?? null)
            ? ($sessionMeta['build']['status'] ?? null)
            : null;

        if ($buildStatus !== 'running') {
            return;
        }

        // Dispatch a build tick with a short delay to allow the self-sustaining
        // loop a chance to process first.  If the loop already dispatched its
        // own follow-up, the tick will harmlessly handle whatever state it finds.
        ExecuteInterrogationBuildJob::dispatch($sessionId)
            ->delay(now()->addSeconds(3));
    }
}
