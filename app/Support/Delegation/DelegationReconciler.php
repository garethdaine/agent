<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Events\DelegationTaskVerified;
use App\Models\AgentJobRun;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use Carbon\Carbon;

/**
 * Scheduled reconciliation service for the Delegation Engine.
 *
 * Handles:
 * - Expired human approval verification results
 * - Blocked tasks awaiting delegatee assignment
 * - Stuck running graphs
 * - Graceful cancellation timeout enforcement
 *
 * Runs every 2 minutes via delegation:reconcile command.
 */
class DelegationReconciler
{
    private const BACKOFF_DELAYS = [60, 300, 900]; // 1 min, 5 min, 15 min in seconds

    private const MAX_RETRIES = 3;

    /**
     * Run all reconciliation tasks.
     */
    public function reconcile(): void
    {
        $this->handleExpiredHumanApprovals();
        $this->retryBlockedTasks();
        $this->handleStuckGraphs();
        $this->enforceGracefulCancellationTimeout();
    }

    /**
     * Mark expired human approval verification results as failed.
     *
     * When a human approval step has an expires_at that is in the past
     * and the verdict is still pending, mark it as failed and fire
     * the DelegationTaskVerified event to resume the pipeline.
     */
    private function handleExpiredHumanApprovals(): void
    {
        $expired = DelegationVerificationResult::query()
            ->where('verdict', DelegationVerificationResult::VERDICT_PENDING)
            ->where('step_type', DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['task', 'attempt'])
            ->get();

        foreach ($expired as $result) {
            $result->update([
                'verdict' => DelegationVerificationResult::VERDICT_FAILED,
                'finished_at' => now(),
                'evidence_json' => array_merge($result->evidence_json ?? [], [
                    'failure_reason' => 'Human approval timed out',
                    'expired_at' => now()->toISOString(),
                ]),
            ]);

            // Fire event to notify listeners (e.g., DelegationCoordinator)
            // that verification has completed with a failure
            if ($result->task && $result->attempt) {
                event(new DelegationTaskVerified(
                    task: $result->task,
                    attempt: $result->attempt,
                    passed: false,
                    failedStepOrder: $result->step_order
                ));
            }
        }
    }

    /**
     * Retry assignment for blocked tasks with exponential backoff.
     *
     * Tasks become blocked when no matching delegatee profile is available.
     * This method attempts to re-assign them using backoff delays of 1/5/15 minutes.
     * After 3 failed retries, the task is marked as permanently failed.
     */
    private function retryBlockedTasks(): void
    {
        $blockedTasks = DelegationTask::query()
            ->where('status', DelegationTask::STATUS_BLOCKED)
            ->whereHas('graph', fn ($q) => $q->whereIn('status', DelegationGraph::ACTIVE_STATUSES))
            ->get();

        foreach ($blockedTasks as $task) {
            $metadata = $task->metadata_json ?? [];
            $retryCount = $metadata['retry_count'] ?? 0;
            $lastRetryAt = isset($metadata['last_retry_at'])
                ? Carbon::parse($metadata['last_retry_at'])
                : null;

            // Mark permanently failed after max retries
            if ($retryCount >= self::MAX_RETRIES) {
                $task->update([
                    'status' => DelegationTask::STATUS_FAILED,
                    'finished_at' => now(),
                    'error_code' => 'MAX_RETRIES_EXCEEDED',
                    'error_summary' => 'Failed after '.self::MAX_RETRIES.' retry attempts',
                ]);

                continue;
            }

            // Check if backoff period has elapsed
            $requiredDelay = self::BACKOFF_DELAYS[$retryCount] ?? 900;
            if ($lastRetryAt !== null && $lastRetryAt->addSeconds($requiredDelay)->isFuture()) {
                continue;
            }

            // Attempt re-assignment via DelegateeAssigner
            app(DelegateeAssigner::class)->assign($task);

            // Update retry metadata
            $task->update([
                'metadata_json' => array_merge($metadata, [
                    'retry_count' => $retryCount + 1,
                    'last_retry_at' => now()->toISOString(),
                ]),
            ]);
        }
    }

    /**
     * Handle stuck running graphs.
     *
     * Detects graphs that are in RUNNING status but have no active tasks
     * and no incomplete work. Transitions them to appropriate terminal states:
     * - SUCCEEDED: all tasks succeeded
     * - FAILED: any task failed
     * - PARTIAL: mix of succeeded and cancelled (no failures)
     */
    private function handleStuckGraphs(): void
    {
        // Find graphs that are running but have no running tasks
        // and all tasks are in terminal states
        $stuckGraphs = DelegationGraph::query()
            ->where('status', DelegationGraph::STATUS_RUNNING)
            ->whereDoesntHave('tasks', function ($query) {
                $query->whereNotIn('status', [
                    DelegationTask::STATUS_SUCCEEDED,
                    DelegationTask::STATUS_FAILED,
                    DelegationTask::STATUS_CANCELLED,
                ]);
            })
            ->where('updated_at', '<', now()->subMinutes(5))
            ->with('tasks')
            ->get();

        foreach ($stuckGraphs as $graph) {
            $tasks = $graph->tasks;

            // Skip if no tasks (edge case)
            if ($tasks->isEmpty()) {
                continue;
            }

            // Check for any incomplete work (tasks not in terminal states)
            $hasIncomplete = $tasks->whereNotIn('status', [
                DelegationTask::STATUS_SUCCEEDED,
                DelegationTask::STATUS_FAILED,
                DelegationTask::STATUS_CANCELLED,
            ])->isNotEmpty();

            if ($hasIncomplete) {
                continue; // Not actually stuck, has pending work
            }

            // Determine terminal state based on task outcomes
            $allSucceeded = $tasks->every(fn ($t) => $t->status === DelegationTask::STATUS_SUCCEEDED);
            $anyFailed = $tasks->contains(fn ($t) => $t->status === DelegationTask::STATUS_FAILED);

            if ($allSucceeded) {
                $graph->update([
                    'status' => DelegationGraph::STATUS_SUCCEEDED,
                    'finished_at' => now(),
                ]);
            } elseif ($anyFailed) {
                $graph->update([
                    'status' => DelegationGraph::STATUS_FAILED,
                    'finished_at' => now(),
                ]);
            } else {
                // Mix of succeeded and cancelled (no failures)
                $graph->update([
                    'status' => DelegationGraph::STATUS_PARTIAL,
                    'finished_at' => now(),
                ]);
            }
        }
    }

    /**
     * Force-kill running tasks after graceful cancellation timeout.
     *
     * When a graph is cancelled, we allow running tasks a grace period
     * (default 15 minutes) to complete naturally. After this timeout,
     * we force-kill any remaining running tasks.
     */
    private function enforceGracefulCancellationTimeout(): void
    {
        $timeoutMinutes = config('delegation.graceful_cancellation_timeout_minutes', 15);

        $cancelledGraphsWithRunningTasks = DelegationGraph::query()
            ->where('status', DelegationGraph::STATUS_CANCELLED)
            ->where('updated_at', '<', now()->subMinutes($timeoutMinutes))
            ->whereHas('tasks', fn ($q) => $q->where('status', DelegationTask::STATUS_RUNNING))
            ->with(['tasks' => fn ($q) => $q->where('status', DelegationTask::STATUS_RUNNING)])
            ->get();

        foreach ($cancelledGraphsWithRunningTasks as $graph) {
            $this->forceKillRunningTasks($graph);
        }
    }

    /**
     * Force-kill all running tasks and their attempts for a graph.
     *
     * Uses a hybrid approach:
     * 1. Update AgentJobRun status to 'killed' (database status)
     * 2. Mark attempts as failed
     * 3. Mark tasks as cancelled
     */
    private function forceKillRunningTasks(DelegationGraph $graph): void
    {
        $runningTasks = $graph->tasks()
            ->where('status', DelegationTask::STATUS_RUNNING)
            ->get();

        foreach ($runningTasks as $task) {
            // Find running attempts for this task
            $runningAttempts = $task->attempts() // @phpstan-ignore method.notFound
                ->where('status', DelegationAttempt::STATUS_RUNNING)
                ->with('agentJobRun')
                ->get();

            foreach ($runningAttempts as $attempt) {
                // Update the linked AgentJobRun if it exists
                if ($attempt->agentJobRun !== null) {
                    $attempt->agentJobRun->update([
                        'status' => AgentJobRun::STATUS_KILLED,
                        'finished_at' => now(),
                        'error_code' => 'FORCE_KILLED',
                        'error_summary' => 'Force killed by reconciler due to cancellation timeout',
                    ]);
                }

                // Mark attempt as failed
                $attempt->update([
                    'status' => DelegationAttempt::STATUS_FAILED,
                    'finished_at' => now(),
                    'error_code' => 'CANCELLATION_TIMEOUT',
                    'error_summary' => 'Force killed after graceful cancellation timeout',
                ]);
            }

            // Mark task as cancelled
            $task->update([
                'status' => DelegationTask::STATUS_CANCELLED,
                'finished_at' => now(),
                'error_code' => 'CANCELLATION_TIMEOUT',
                'error_summary' => 'Cancelled after graceful cancellation timeout',
            ]);
        }
    }
}
