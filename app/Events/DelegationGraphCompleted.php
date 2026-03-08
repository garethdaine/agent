<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DelegationGraph;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delegation graph reaches a terminal state.
 *
 * Terminal states: succeeded, failed, partial, cancelled
 *
 * This event can be used for:
 * - Broadcasting completion to the frontend
 * - Triggering cleanup or follow-up actions
 * - Recording audit events
 */
class DelegationGraphCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DelegationGraph  $graph  The graph that completed
     * @param  string  $finalStatus  The terminal status (succeeded, failed, partial, cancelled)
     */
    public function __construct(
        public readonly DelegationGraph $graph,
        public readonly string $finalStatus
    ) {}
}
