<?php

namespace App\Events;

use App\Models\DelegationAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delegation attempt completes (succeeded or failed).
 *
 * This event is consumed by:
 * - DelegationCoordinator: handles successful attempts, triggers verification
 * - DelegationRecoveryHandler: handles failed attempts, implements retry/re-delegate/escalate chain
 */
class DelegationAttemptCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DelegationAttempt  $attempt  The attempt that completed
     */
    public function __construct(
        public readonly DelegationAttempt $attempt
    ) {}
}
