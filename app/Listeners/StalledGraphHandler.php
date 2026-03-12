<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DelegationGraphStalled;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Notifications\DelegationStallNotification;
use App\Support\Delegation\DelegateeAssigner;
use App\Support\Delegation\DelegationEventWriter;
use Illuminate\Support\Facades\Log;

/**
 * Handles stalled delegation graph events.
 *
 * Recovery actions:
 * 1. Log the stall event for audit trail
 * 2. Notify the graph owner
 * 3. Re-assign READY tasks that may have been missed due to a race
 * 4. Fail stale RUNNING task attempts to trigger the recovery handler
 * 5. Increment stall count in graph metadata
 */
class StalledGraphHandler
{
    public function __construct(
        private readonly DelegateeAssigner $assigner,
        private readonly DelegationEventWriter $eventWriter,
    ) {}

    public function handle(DelegationGraphStalled $event): void
    {
        $graph = $event->graph;
        $stalledTasks = $event->stalledTasks;

        Log::warning('Delegation graph stalled', [
            'graph_id' => $graph->id,
            'stalled_minutes' => $event->stalledMinutes,
            'stalled_task_count' => $stalledTasks->count(),
            'stalled_task_ids' => $stalledTasks->pluck('id')->all(),
        ]);

        // 1. Log audit event
        $this->eventWriter->write($graph, 'graph_stalled', [
            'stalled_minutes' => $event->stalledMinutes,
            'stalled_task_count' => $stalledTasks->count(),
            'stalled_task_ids' => $stalledTasks->pluck('id')->all(),
        ]);

        // 2. Notify graph owner (only on first stall or every 3rd stall)
        $metadata = $graph->metadata_json ?? [];
        $stallCount = ($metadata['stall_count'] ?? 0) + 1;
        $graph->update([
            'metadata_json' => array_merge($metadata, [
                'stall_count' => $stallCount,
                'last_stall_at' => now()->toIso8601String(),
            ]),
        ]);

        if ($stallCount === 1 || $stallCount % 3 === 0) {
            $graph->user?->notify(
                new DelegationStallNotification($graph, $event->stalledMinutes, $stalledTasks->count())
            );
        }

        // 3. Re-assign READY tasks that may have been missed
        $readyTasks = $stalledTasks->where('status', DelegationTask::STATUS_READY);
        foreach ($readyTasks as $task) {
            $this->assigner->assign($task);
        }

        // 4. Fail stale RUNNING attempts to trigger recovery handler
        $runningTasks = $stalledTasks->where('status', DelegationTask::STATUS_RUNNING);
        foreach ($runningTasks as $task) {
            $staleAttempt = $task->attempts()
                ->where('status', DelegationAttempt::STATUS_RUNNING)
                ->where('created_at', '<', now()->subMinutes($event->stalledMinutes))
                ->first();

            if ($staleAttempt !== null) {
                $staleAttempt->update([
                    'status' => DelegationAttempt::STATUS_FAILED,
                    'finished_at' => now(),
                    'error_code' => 'STALL_DETECTED',
                    'error_summary' => "Attempt stalled for {$event->stalledMinutes} minutes with no progress",
                ]);

                // The DelegationRecoveryHandler listens for DelegationAttemptCompleted
                // and will handle retry/re-delegation from here
                event(new \App\Events\DelegationAttemptCompleted($staleAttempt->fresh()));
            }
        }
    }
}
