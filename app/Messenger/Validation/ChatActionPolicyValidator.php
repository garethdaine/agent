<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;

/**
 * Validates authorization for chat action operations.
 *
 * Implements creator-only ownership model where only the job/run owner
 * can perform operations. Returns generic denial messages to prevent
 * resource enumeration attacks.
 */
class ChatActionPolicyValidator
{
    private const GENERIC_DENIAL = 'Permission denied';

    /**
     * Authorize job access - checks creator-only ownership.
     * Returns generic denial to prevent resource enumeration.
     */
    public function authorizeJobAccess(User $user, AgentJob $job): AuthorizationResult
    {
        if ($job->user_id !== $user->id) {
            return AuthorizationResult::denied(self::GENERIC_DENIAL);
        }

        return AuthorizationResult::allowed();
    }

    /**
     * Authorize job by ID - returns same denial for non-existent and unauthorized.
     */
    public function authorizeJobById(User $user, int $jobId): AuthorizationResult
    {
        $job = AgentJob::find($jobId);

        // Same response for non-existent and unauthorized to prevent enumeration
        if (! $job || $job->user_id !== $user->id) {
            return AuthorizationResult::denied(self::GENERIC_DENIAL);
        }

        return AuthorizationResult::allowed();
    }

    /**
     * Authorize run access - checks job ownership.
     */
    public function authorizeRunAccess(User $user, AgentJobRun $run): AuthorizationResult
    {
        // Load job relationship if not loaded
        $job = $run->job;

        if (! $job || $job->user_id !== $user->id) {
            return AuthorizationResult::denied(self::GENERIC_DENIAL);
        }

        return AuthorizationResult::allowed();
    }

    /**
     * Authorize run by ID - returns same denial for non-existent and unauthorized.
     */
    public function authorizeRunById(User $user, int $runId): AuthorizationResult
    {
        $run = AgentJobRun::with('job')->find($runId);

        if (! $run || ! $run->job || $run->job->user_id !== $user->id) {
            return AuthorizationResult::denied(self::GENERIC_DENIAL);
        }

        return AuthorizationResult::allowed();
    }

    /**
     * Authorize steer operation - same as run access.
     */
    public function authorizeSteerAccess(User $user, AgentJobRun $run): AuthorizationResult
    {
        return $this->authorizeRunAccess($user, $run);
    }
}
