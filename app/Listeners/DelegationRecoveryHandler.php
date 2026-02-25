<?php

namespace App\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Notifications\DelegationEscalationNotification;
use App\Support\Delegation\AttemptSpawner;
use App\Support\Delegation\DelegateeAssigner;
use App\Support\Delegation\TaskStateTransitionService;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Handles failure recovery for delegation attempts.
 *
 * Recovery chain (as specified):
 * 1. Retry on same delegatee up to limit (config: retry_same_delegatee_limit, default 2)
 * 2. Re-delegate to different delegatee up to limit (config: redelegate_limit, default 1)
 * 3. Escalate to graph owner (mark task failed, send notification)
 *
 * Error classification:
 * - Transient errors (TIMEOUT, RATE_LIMIT, CONNECTION_*): eligible for retry
 * - Non-transient errors (INVALID_OUTPUT, PERMISSION_DENIED, SKIPPED): skip to re-delegation
 */
class DelegationRecoveryHandler
{
    public function __construct(
        private readonly DelegateeAssigner $assigner,
        private readonly AttemptSpawner $spawner,
        private readonly TaskStateTransitionService $taskTransition
    ) {}

    /**
     * Subscribe to delegation events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DelegationAttemptCompleted::class, [self::class, 'handle']);
    }

    /**
     * Handle failed attempt - implement recovery chain.
     */
    public function handle(DelegationAttemptCompleted $event): void
    {
        $attempt = $event->attempt->fresh(['task', 'task.graph', 'task.graph.user', 'profile']);
        if ($attempt === null) {
            return;
        }

        // Only handle failed attempts
        if ($attempt->status !== DelegationAttempt::STATUS_FAILED) {
            return;
        }

        $task = $attempt->task;
        if ($task === null || $task->graph === null) {
            return;
        }

        // Don't retry if task is already in a terminal state
        if (in_array($task->status, [
            DelegationTask::STATUS_SUCCEEDED,
            DelegationTask::STATUS_FAILED,
            DelegationTask::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $this->executeRecoveryChain($task, $attempt);
    }

    /**
     * Execute the recovery chain: retry → re-delegate → escalate.
     */
    private function executeRecoveryChain(DelegationTask $task, DelegationAttempt $attempt): void
    {
        $errorType = $this->classifyError($attempt);
        $currentProfileId = $attempt->delegatee_profile_id;

        // Count attempts on the same profile
        $sameProfileAttempts = $task->attempts()
            ->where('delegatee_profile_id', $currentProfileId)
            ->count();

        // Count total re-delegations (attempts on different profiles)
        $distinctProfiles = $task->attempts()
            ->distinct('delegatee_profile_id')
            ->count('delegatee_profile_id');
        $redelegationCount = $distinctProfiles - 1; // First profile doesn't count as re-delegation

        $retryLimit = config('delegation.retry_same_delegatee_limit', 2);
        $redelegateLimit = config('delegation.redelegate_limit', 1);

        // Decision tree:
        // 1. If transient error and under retry limit: retry on same delegatee
        // 2. If retry limit exceeded or non-transient: try re-delegation
        // 3. If re-delegation limit exceeded: escalate

        if ($errorType === 'transient' && $sameProfileAttempts < $retryLimit) {
            // Retry on same delegatee
            $this->retryOnSameDelegatee($task, $attempt->profile);

            return;
        }

        if ($redelegationCount < $redelegateLimit) {
            // Try re-delegation to different delegatee
            $redelegated = $this->redelegateToNewProfile($task, $currentProfileId);
            if ($redelegated) {
                return;
            }
            // Fall through to escalation if no other delegatee available
        }

        // Escalate - all recovery options exhausted
        $this->escalate($task);
    }

    /**
     * Classify error as transient or non-transient.
     *
     * Transient errors are eligible for retry on the same delegatee.
     * Non-transient errors skip directly to re-delegation.
     */
    private function classifyError(DelegationAttempt $attempt): string
    {
        $errorCode = $attempt->error_code ?? '';

        // Check for SKIPPED - explicitly non-transient
        if ($errorCode === 'SKIPPED') {
            return 'non_transient';
        }

        // Check transient error codes
        $transientCodes = config('delegation.transient_error_codes', ['TIMEOUT', 'RATE_LIMIT', 'CONNECTION_*']);
        foreach ($transientCodes as $pattern) {
            if ($this->matchesPattern($errorCode, $pattern)) {
                return 'transient';
            }
        }

        // Check explicit non-transient error codes
        $nonTransientCodes = config('delegation.non_transient_error_codes', ['INVALID_OUTPUT', 'PERMISSION_DENIED']);
        foreach ($nonTransientCodes as $pattern) {
            if ($this->matchesPattern($errorCode, $pattern)) {
                return 'non_transient';
            }
        }

        // Default: treat unknown errors as transient to give a chance for retry
        return 'transient';
    }

    /**
     * Match error code against pattern (supports * wildcard).
     */
    private function matchesPattern(string $errorCode, string $pattern): bool
    {
        if (! str_contains($pattern, '*')) {
            return $errorCode === $pattern;
        }

        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/i';

        return preg_match($regex, $errorCode) === 1;
    }

    /**
     * Retry the task on the same delegatee.
     */
    private function retryOnSameDelegatee(DelegationTask $task, $profile): void
    {
        // Task should already be in running state, spawn new attempt
        $this->spawner->spawn($task, $profile);
    }

    /**
     * Re-delegate to a different delegatee profile.
     *
     * @return bool True if re-delegation succeeded, false if no alternative available
     */
    private function redelegateToNewProfile(DelegationTask $task, int $excludeProfileId): bool
    {
        // Get a new assignment, but the assigner doesn't have exclusion logic built in.
        // We need to temporarily work around this by attempting assignment and checking.
        $result = $this->assigner->assign($task);

        if ($result === null) {
            // No profile available at all
            return false;
        }

        // If the same profile was assigned, we need to try to find another
        if ($result->profile->id === $excludeProfileId) {
            // The assigner returned the same profile - we could enhance the assigner
            // to support exclusions, but for now, check if we have alternatives
            return false;
        }

        // Update task with new assignment
        $task->update([
            'assigned_delegatee_profile_id' => $result->profile->id,
            'assignment_reason_json' => array_merge($result->reasoning, [
                're_delegation' => true,
                'previous_profile_id' => $excludeProfileId,
            ]),
        ]);

        // Spawn attempt on new profile
        $this->spawner->spawn($task, $result->profile);

        return true;
    }

    /**
     * Escalate the task to the graph owner.
     */
    private function escalate(DelegationTask $task): void
    {
        // Transition task to failed
        $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_RUNNING],
            DelegationTask::STATUS_FAILED,
            [
                'finished_at' => now(),
                'metadata_json' => array_merge($task->metadata_json ?? [], [
                    'escalation_notified_at' => now()->toIso8601String(),
                    'escalation_reason' => 'Recovery chain exhausted',
                ]),
            ]
        );

        // Notify graph owner
        $graphOwner = $task->graph->user;
        if ($graphOwner !== null) {
            $graphOwner->notify(new DelegationEscalationNotification($task));
        }
    }
}
