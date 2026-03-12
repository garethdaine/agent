<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DelegationGraph;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Fired when a running delegation graph has active tasks that have not
 * progressed within the configured stall detection threshold.
 *
 * Distinct from stuck graphs (all tasks terminal but graph still running).
 * Stalled graphs have active tasks that are not making progress — a liveness failure.
 *
 * This event triggers the StalledGraphHandler to:
 * - Notify the graph owner
 * - Re-dispatch READY tasks that may have been missed
 * - Fail stale running attempts to trigger recovery
 */
class DelegationGraphStalled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  DelegationGraph  $graph  The stalled graph
     * @param  Collection  $stalledTasks  Active tasks with no recent state transitions
     * @param  int  $stalledMinutes  How many minutes since the last task state change
     */
    public function __construct(
        public readonly DelegationGraph $graph,
        public readonly Collection $stalledTasks,
        public readonly int $stalledMinutes,
    ) {}
}
