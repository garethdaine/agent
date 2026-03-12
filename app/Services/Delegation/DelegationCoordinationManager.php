<?php

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Models\DelegationCoordinationLock;
use App\Models\DelegationTask;
use Illuminate\Support\Facades\DB;

/**
 * Manages locks/semaphores for concurrent delegations to prevent resource conflicts.
 */
class DelegationCoordinationManager
{
    /**
     * Default lock duration in minutes.
     */
    private const DEFAULT_LOCK_MINUTES = 5;

    /**
     * Attempt to acquire an exclusive lock on a resource for a delegation task.
     *
     * Returns the lock if acquired, or null if the resource is already locked.
     */
    public function acquireExclusive(
        DelegationTask $task,
        string $resourceType,
        string $resourceId,
        int $ttlMinutes = self::DEFAULT_LOCK_MINUTES,
    ): ?DelegationCoordinationLock {
        return $this->acquire($task, $resourceType, $resourceId, DelegationCoordinationLock::LOCK_EXCLUSIVE, $ttlMinutes);
    }

    /**
     * Attempt to acquire a shared lock on a resource for a delegation task.
     *
     * Returns the lock if acquired, or null if an exclusive lock is already held.
     */
    public function acquireShared(
        DelegationTask $task,
        string $resourceType,
        string $resourceId,
        int $ttlMinutes = self::DEFAULT_LOCK_MINUTES,
    ): ?DelegationCoordinationLock {
        return $this->acquire($task, $resourceType, $resourceId, DelegationCoordinationLock::LOCK_SHARED, $ttlMinutes);
    }

    /**
     * Release a specific lock.
     */
    public function release(DelegationCoordinationLock $lock): bool
    {
        return (bool) $lock->delete();
    }

    /**
     * Release all locks held by a specific delegation task.
     */
    public function releaseAll(DelegationTask $task): int
    {
        return DelegationCoordinationLock::where('holder_delegation_task_id', $task->id)->delete();
    }

    /**
     * Release all expired locks and return the number removed.
     */
    public function releaseExpired(): int
    {
        return DelegationCoordinationLock::where('expires_at', '<=', now())->delete();
    }

    /**
     * Check if a resource has any active (non-expired) locks.
     */
    public function isLocked(string $resourceType, string $resourceId): bool
    {
        return DelegationCoordinationLock::forResource($resourceType, $resourceId)
            ->active()
            ->exists();
    }

    /**
     * Check if a resource has an active exclusive lock.
     */
    public function hasExclusiveLock(string $resourceType, string $resourceId): bool
    {
        return DelegationCoordinationLock::forResource($resourceType, $resourceId)
            ->active()
            ->where('lock_type', DelegationCoordinationLock::LOCK_EXCLUSIVE)
            ->exists();
    }

    /**
     * Get all active locks for a specific resource.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DelegationCoordinationLock>
     */
    public function getLocksForResource(string $resourceType, string $resourceId): \Illuminate\Database\Eloquent\Collection
    {
        return DelegationCoordinationLock::forResource($resourceType, $resourceId)
            ->active()
            ->get();
    }

    /**
     * Get all active locks held by a specific task.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DelegationCoordinationLock>
     */
    public function getLocksForTask(DelegationTask $task): \Illuminate\Database\Eloquent\Collection
    {
        return DelegationCoordinationLock::where('holder_delegation_task_id', $task->id)
            ->active()
            ->get();
    }

    /**
     * Acquire a lock within a transaction to prevent race conditions.
     */
    private function acquire(
        DelegationTask $task,
        string $resourceType,
        string $resourceId,
        string $lockType,
        int $ttlMinutes,
    ): ?DelegationCoordinationLock {
        return DB::transaction(function () use ($task, $resourceType, $resourceId, $lockType, $ttlMinutes) {
            // Clean up expired locks on this resource first
            DelegationCoordinationLock::forResource($resourceType, $resourceId)
                ->where('expires_at', '<=', now())
                ->delete();

            $existingLocks = DelegationCoordinationLock::forResource($resourceType, $resourceId)
                ->active()
                ->lockForUpdate()
                ->get();

            if ($lockType === DelegationCoordinationLock::LOCK_EXCLUSIVE && $existingLocks->isNotEmpty()) {
                return null;
            }

            if ($lockType === DelegationCoordinationLock::LOCK_SHARED) {
                $hasExclusive = $existingLocks->contains(fn (DelegationCoordinationLock $lock) => $lock->lock_type === DelegationCoordinationLock::LOCK_EXCLUSIVE);

                if ($hasExclusive) {
                    return null;
                }
            }

            return DelegationCoordinationLock::create([
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'holder_delegation_task_id' => $task->id,
                'acquired_at' => now(),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'lock_type' => $lockType,
            ]);
        });
    }
}
