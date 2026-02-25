<?php

namespace App\Support\Delegation;

use App\Models\DelegationTask;
use Carbon\CarbonImmutable;

class TaskStateTransitionService
{
    /**
     * Atomically transition a task from one of the allowed statuses to a new status.
     *
     * Uses a single UPDATE query with WHERE conditions to ensure atomic state transitions.
     * Only succeeds if the task exists and its current status matches one of the allowed
     * from statuses.
     *
     * @param  int  $taskId  The ID of the task to transition
     * @param  array<int, string>  $fromStatuses  Allowed current statuses (must be non-empty)
     * @param  string  $toStatus  Target status to transition to
     * @param  array<string, mixed>  $attributes  Additional attributes to update
     * @return bool True if exactly one row was updated (transition succeeded)
     */
    public function transition(int $taskId, array $fromStatuses, string $toStatus, array $attributes = []): bool
    {
        if ($fromStatuses === []) {
            return false;
        }

        $payload = array_merge($attributes, [
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        $updated = DelegationTask::query()
            ->whereKey($taskId)
            ->whereIn('status', $fromStatuses)
            ->update($payload);

        return $updated === 1;
    }
}
