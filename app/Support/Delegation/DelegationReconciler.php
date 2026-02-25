<?php

namespace App\Support\Delegation;

use App\Events\DelegationTaskVerified;
use App\Models\AgentJobRun;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;

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
     * Retry assignment for blocked tasks.
     *
     * Tasks become blocked when no matching delegatee profile is available.
     * This method attempts to re-assign them in case new profiles have become
     * available since the initial assignment attempt.
     */
    private function retryBlockedTasks(): void
    {
        $blockedTasks = DelegationTask::query()
            ->where('status', DelegationTask::STATUS_BLOCKED)
            ->whereHas('graph', fn ($q) => $q->whereIn('status', DelegationGraph::ACTIVE_STATUSES))
            ->get();

        // TODO: Integrate with DelegateeAssigner when available
        // For now, this serves as a placeholder for the reconciliation loop
        foreach ($blockedTasks as $task) {
            // The actual assignment would be handled by DelegateeAssigner
            // which is called from DelegationCoordinator
            // This method just identifies tasks that need retry
        }
    }

    /**
     * Handle stuck running graphs.
     *
     * Detects graphs that are in RUNNING status but have no active tasks
     * and no incomplete work. These may indicate missed completion events.
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
            ->get();

        // These graphs may have missed completion events
        // In a full implementation, we would fire the completion check
        // For now, log for manual intervention
        foreach ($stuckGraphs as $graph) {
            // TODO: Implement automatic completion detection
            // This would analyze task states and determine if graph should
            // transition to succeeded, failed, or partial
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
            $runningAttempts = $task->attempts()
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
