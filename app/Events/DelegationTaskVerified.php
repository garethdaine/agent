<?php

namespace App\Events;

use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delegation task's verification process completes.
 *
 * This event is fired in two scenarios:
 * 1. All verification steps pass → passed = true
 * 2. Any verification step fails → passed = false
 *
 * Listeners can use this event to:
 * - Update task status (SUCCESS or FAILED)
 * - Trigger the next ready tasks in the graph
 * - Resume the verification pipeline for async steps
 */
class DelegationTaskVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DelegationTask  $task  The task that was verified
     * @param  DelegationAttempt  $attempt  The attempt that was verified
     * @param  bool  $passed  Whether verification passed
     * @param  int|null  $failedStepOrder  The step order that failed (if applicable)
     */
    public function __construct(
        public readonly DelegationTask $task,
        public readonly DelegationAttempt $attempt,
        public readonly bool $passed,
        public readonly ?int $failedStepOrder = null
    ) {}
}
