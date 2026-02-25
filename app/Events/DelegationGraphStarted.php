<?php

namespace App\Events;

use App\Models\DelegationGraph;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delegation graph transitions to the running state.
 *
 * This event triggers the DelegationCoordinator to:
 * - Find all root tasks (ready tasks with no dependencies)
 * - Assign delegatees and spawn execution attempts
 * - Respect max_parallel_tasks limit
 */
class DelegationGraphStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DelegationGraph  $graph  The graph that was started
     */
    public function __construct(
        public readonly DelegationGraph $graph
    ) {}
}
