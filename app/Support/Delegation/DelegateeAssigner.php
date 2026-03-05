<?php

namespace App\Support\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;

/**
 * Assigns delegatee profiles to delegation tasks based on capability matching
 * and performance metrics.
 *
 * Assignment Algorithm:
 * 1. Extract required_capability from task's contract_json
 * 2. Query active DelegateeProfile records for the graph owner with matching capability
 * 3. Rank by 24h success_rate (descending)
 * 4. Tiebreak by current load - count of running attempts (ascending)
 *
 * When no matching profile is found, returns null. The caller should transition
 * the task to STATUS_BLOCKED for later retry by the reconciler.
 */
class DelegateeAssigner
{
    /**
     * Find and assign the best matching delegatee profile for a task.
     *
     * @param  DelegationTask  $task  The task requiring assignment
     * @return AssignmentResult|null The assignment result or null if no matching profile found
     */
    public function assign(DelegationTask $task): ?AssignmentResult
    {
        // Honour pre-assigned delegatee from ritual/upstream systems
        $preAssignedId = $task->contract_json['pre_assigned_delegatee_profile_id'] ?? null;
        if ($preAssignedId !== null) {
            $profile = DelegateeProfile::query()
                ->active()
                ->where('user_id', $task->graph->user_id)
                ->find($preAssignedId);

            if ($profile !== null) {
                return new AssignmentResult(
                    profile: $profile,
                    reasoning: [
                        'source' => 'pre_assigned',
                        'delegatee_profile_id' => $preAssignedId,
                    ]
                );
            }
        }

        $requiredCapability = $task->contract_json['required_capability'] ?? null;
        if ($requiredCapability === null) {
            return null;
        }

        $graphUserId = $task->graph->user_id;

        $candidates = DelegateeProfile::query()
            ->active()
            ->where('user_id', $graphUserId)
            ->whereHas('capabilities', fn ($q) => $q->where('slug', $requiredCapability))
            ->with('metric')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $ranked = $candidates
            ->map(fn ($profile) => [
                'profile' => $profile,
                'success_rate' => $profile->metric?->window_24h_json['success_rate'] ?? 0,
                'current_load' => $this->currentLoad($profile),
            ])
            ->sortBy([
                fn ($a, $b) => $b['success_rate'] <=> $a['success_rate'],
                fn ($a, $b) => $a['current_load'] <=> $b['current_load'],
            ])
            ->first();

        return new AssignmentResult(
            profile: $ranked['profile'],
            reasoning: [
                'matched_capability' => $requiredCapability,
                'success_rate_24h' => $ranked['success_rate'],
                'current_load' => $ranked['current_load'],
            ]
        );
    }

    /**
     * Count currently running attempts for a delegatee profile.
     *
     * @param  DelegateeProfile  $profile  The profile to check
     * @return int Number of running attempts
     */
    private function currentLoad(DelegateeProfile $profile): int
    {
        return DelegationAttempt::query()
            ->where('delegatee_profile_id', $profile->id)
            ->where('status', DelegationAttempt::STATUS_RUNNING)
            ->count();
    }
}
