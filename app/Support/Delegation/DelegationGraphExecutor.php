<?php

namespace App\Support\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationTask;

class DelegationGraphExecutor
{
    public function __construct(
        private readonly TrustScoreCalculator $trustCalculator
    ) {}

    /**
     * Determine if verification is required for a sub-task based on delegatee trust level.
     *
     * - Low trust (<0.4): Mandatory verification after every sub-task
     * - Medium trust (0.4-0.8): Standard monitoring (uses task's requires_verification flag)
     * - High trust (>0.8): Skip intermediate verification, verify only final task
     */
    public function shouldVerifySubTask(DelegationTask $task, DelegateeProfile $profile): bool
    {
        $trustScore = $this->trustCalculator->calculate($profile->runner_type);

        // Low trust: mandatory verification after every sub-task
        if ($trustScore->isLowTrust()) {
            return true;
        }

        // High trust: skip intermediate verification, only verify final tasks
        if ($trustScore->isHighTrust()) {
            return $this->isFinalTask($task);
        }

        // Medium trust: standard rules - use task's verification flag
        return $this->taskRequiresVerification($task);
    }

    /**
     * Check if a task is the final task in its delegation graph.
     */
    private function isFinalTask(DelegationTask $task): bool
    {
        // A task is final if no other tasks depend on it (no downstream tasks)
        return $task->dependents()->count() === 0;
    }

    /**
     * Check if task has verification requirement set.
     */
    private function taskRequiresVerification(DelegationTask $task): bool
    {
        return $task->metadata_json['requires_verification'] ?? false;
    }
}
