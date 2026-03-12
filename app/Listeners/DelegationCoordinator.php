<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Events\Office\AgentActivityChanged;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Services\Delegation\PermissionAttenuationService;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Delegation\AttemptSpawner;
use App\Support\Delegation\DelegateeAssigner;
use App\Support\Delegation\GraphStateTransitionService;
use App\Support\Delegation\TaskStateTransitionService;
use App\Support\Delegation\VerificationPipeline;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Coordinates the happy-path flow for delegation graph execution.
 *
 * This listener handles:
 * - Graph started: assign and spawn root tasks
 * - Attempt completed (success): trigger verification pipeline
 * - Task verified (passed): mark succeeded, fire ready dependents, check graph completion
 * - Task verified (failed): mark failed (recovery handled by RecoveryHandler)
 *
 * Flow:
 * GraphStarted → root tasks ready → assign → spawn →
 * AttemptCompleted(success) → verify → TaskVerified →
 * complete → check graph completion → fire next ready tasks
 */
class DelegationCoordinator
{
    public function __construct(
        private readonly FeatureFlagManager $featureFlags,
        private readonly DelegateeAssigner $assigner,
        private readonly AttemptSpawner $spawner,
        private readonly VerificationPipeline $pipeline,
        private readonly TaskStateTransitionService $taskTransition,
        private readonly GraphStateTransitionService $graphTransition,
        private readonly PermissionAttenuationService $permissionAttenuation,
    ) {}

    /**
     * Subscribe to delegation events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DelegationGraphStarted::class, [self::class, 'handleGraphStarted']);
        $events->listen(DelegationAttemptCompleted::class, [self::class, 'handleAttemptCompleted']);
        $events->listen(DelegationTaskVerified::class, [self::class, 'handleTaskVerified']);
    }

    /**
     * Handle graph started event.
     *
     * Find ready root tasks and spawn them respecting max_parallel_tasks.
     */
    public function handleGraphStarted(DelegationGraphStarted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $graph = $event->graph->fresh();
        if ($graph === null || $graph->status !== DelegationGraph::STATUS_RUNNING) {
            return;
        }

        $this->spawnReadyTasks($graph);
    }

    /**
     * Handle attempt completed event.
     *
     * For successful attempts, trigger the verification pipeline.
     * Failed attempts are handled by DelegationRecoveryHandler.
     */
    public function handleAttemptCompleted(DelegationAttemptCompleted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $attempt = $event->attempt->fresh(['task', 'task.graph']);
        if ($attempt === null) {
            return;
        }

        // Only handle successful attempts; failures go to RecoveryHandler
        if ($attempt->status !== DelegationAttempt::STATUS_SUCCEEDED) {
            return;
        }

        $task = $attempt->task;
        if ($task === null) {
            return;
        }

        // Transition task to verifying
        $transitioned = $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_RUNNING],
            DelegationTask::STATUS_VERIFYING
        );

        if (! $transitioned) {
            return; // Task state race, another process won
        }

        // Execute verification pipeline
        $this->pipeline->execute($task->fresh(), $attempt);
    }

    /**
     * Handle task verified event.
     *
     * For passed verification: mark succeeded, fire ready dependents, check graph completion.
     * For failed verification: mark failed, check graph completion.
     */
    public function handleTaskVerified(DelegationTaskVerified $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $task = $event->task->fresh(['graph', 'dependents']);
        if ($task === null) {
            return;
        }

        if ($event->passed) {
            $this->handleVerificationPassed($task);
        } else {
            $this->handleVerificationFailed($task, $event->failedStepOrder);
        }
    }

    /**
     * Handle successful verification.
     */
    private function handleVerificationPassed(DelegationTask $task): void
    {
        $transitioned = $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_VERIFYING],
            DelegationTask::STATUS_SUCCEEDED,
            ['finished_at' => now()]
        );

        if (! $transitioned) {
            return;
        }

        $this->broadcastTaskCompleted($task);

        $this->makeReadyDependents($task);
        $this->checkGraphCompletion($task->graph);
        $this->spawnReadyTasks($task->graph->fresh());
    }

    /**
     * Handle failed verification.
     */
    private function handleVerificationFailed(DelegationTask $task, ?int $failedStepOrder): void
    {
        // Transition task to failed
        $attributes = ['finished_at' => now()];
        if ($failedStepOrder !== null) {
            $attributes['metadata_json'] = array_merge($task->metadata_json ?? [], [
                'verification_failed_at_step' => $failedStepOrder,
            ]);
        }

        $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_VERIFYING],
            DelegationTask::STATUS_FAILED,
            $attributes
        );

        // Check graph completion (may result in partial or failed status)
        $this->checkGraphCompletion($task->graph->fresh());
    }

    /**
     * Make dependent tasks ready if all their dependencies are satisfied.
     *
     * Respects max_fan_out_per_node to limit how many immediate dependents
     * of a single task are transitioned to READY simultaneously. This mitigates
     * group size degradation identified by Berdoz et al. (2026).
     *
     * Remaining dependents stay PENDING and are picked up by subsequent
     * spawnReadyTasks() calls or the PENDING sweep in spawnReadyTasks().
     */
    private function makeReadyDependents(DelegationTask $task): void
    {
        $dependents = $task->dependents()->with('dependencies')->get();
        $maxFanOut = $task->graph->metadata_json['max_fan_out_per_node']
            ?? config('delegation.max_fan_out_per_node', 3);

        $readied = 0;

        foreach ($dependents as $dependent) {
            if ($readied >= $maxFanOut) {
                break; // Remaining stay PENDING, picked up by next cycle
            }

            // Check if all dependencies of this dependent are succeeded
            $allDependenciesSatisfied = $dependent->dependencies // @phpstan-ignore property.notFound
                ->every(fn ($dep) => $dep->status === DelegationTask::STATUS_SUCCEEDED);

            if (! $allDependenciesSatisfied) {
                continue;
            }

            // Transition to ready if currently pending
            $transitioned = $this->taskTransition->transition(
                $dependent->id, // @phpstan-ignore property.notFound
                [DelegationTask::STATUS_PENDING],
                DelegationTask::STATUS_READY
            );

            if ($transitioned) {
                $readied++;
            }
        }
    }

    /**
     * Spawn ready tasks respecting max_parallel_tasks.
     *
     * Also sweeps for PENDING tasks whose dependencies are all satisfied
     * but were not transitioned to READY due to fan-out capping. This ensures
     * no tasks are permanently stuck in PENDING after a fan-out limit.
     */
    private function spawnReadyTasks(?DelegationGraph $graph): void
    {
        if ($graph === null || $graph->status !== DelegationGraph::STATUS_RUNNING) {
            return;
        }

        $maxParallel = $graph->max_parallel_tasks ?? config('delegation.default_max_parallel_tasks', 5);

        // Count currently running tasks
        $runningCount = $graph->tasks()
            ->where('status', DelegationTask::STATUS_RUNNING)
            ->count();

        $slotsAvailable = $maxParallel - $runningCount;
        if ($slotsAvailable <= 0) {
            return;
        }

        // Get ready tasks ordered by sequence
        $readyTasks = $graph->tasks()
            ->where('status', DelegationTask::STATUS_READY)
            ->orderBy('sequence_order')
            ->limit($slotsAvailable)
            ->get();

        foreach ($readyTasks as $task) {
            $this->assignAndSpawn($task); // @phpstan-ignore argument.type
            $slotsAvailable--;
        }

        // Sweep: promote PENDING tasks whose deps are all satisfied (fan-out overflow)
        if ($slotsAvailable > 0) {
            $pendingTasks = $graph->tasks()
                ->where('status', DelegationTask::STATUS_PENDING)
                ->with('dependencies')
                ->orderBy('sequence_order')
                ->get();

            foreach ($pendingTasks as $pending) {
                if ($slotsAvailable <= 0) {
                    break;
                }

                $allSatisfied = $pending->dependencies
                    ->every(fn ($dep) => $dep->status === DelegationTask::STATUS_SUCCEEDED);

                if (! $allSatisfied) {
                    continue;
                }

                $transitioned = $this->taskTransition->transition(
                    $pending->id,
                    [DelegationTask::STATUS_PENDING],
                    DelegationTask::STATUS_READY
                );

                if ($transitioned) {
                    $this->assignAndSpawn($pending->fresh());
                    $slotsAvailable--;
                }
            }
        }
    }

    /**
     * Assign a delegatee and spawn an attempt for a task.
     */
    private function assignAndSpawn(DelegationTask $task): void
    {
        // First transition to assigned
        $transitionedToAssigned = $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_READY],
            DelegationTask::STATUS_ASSIGNED
        );

        if (! $transitionedToAssigned) {
            return; // Another process won the race
        }

        $task = $task->fresh();
        $result = $this->assigner->assign($task);

        if ($result === null) {
            // No matching delegatee available, transition to blocked
            $this->taskTransition->transition(
                $task->id,
                [DelegationTask::STATUS_ASSIGNED],
                DelegationTask::STATUS_BLOCKED
            );

            return;
        }

        // Record assignment
        $task->update([
            'assigned_delegatee_profile_id' => $result->profile->id,
            'assignment_reason_json' => $result->reasoning,
        ]);

        // Transition to running
        $transitionedToRunning = $this->taskTransition->transition(
            $task->id,
            [DelegationTask::STATUS_ASSIGNED],
            DelegationTask::STATUS_RUNNING,
            ['started_at' => now()]
        );

        if (! $transitionedToRunning) {
            return; // Unexpected state change
        }

        // Spawn the attempt
        $this->spawner->spawn($task->fresh(), $result->profile);
    }

    /**
     * Check if graph has completed and update its status accordingly.
     */
    private function checkGraphCompletion(DelegationGraph $graph): void
    {
        $graph = $graph->fresh();
        if ($graph === null || ! in_array($graph->status, DelegationGraph::ACTIVE_STATUSES, true)) {
            return;
        }

        $tasks = $graph->tasks()->get();

        // Check for any still active tasks
        $hasActiveTasks = $tasks->contains(function ($task) {
            return in_array($task->status, [ // @phpstan-ignore property.notFound
                DelegationTask::STATUS_PENDING,
                DelegationTask::STATUS_BLOCKED,
                DelegationTask::STATUS_READY,
                DelegationTask::STATUS_ASSIGNED,
                DelegationTask::STATUS_RUNNING,
                DelegationTask::STATUS_VERIFYING,
            ], true);
        });

        if ($hasActiveTasks) {
            return; // Graph not complete yet
        }

        // Count terminal states
        $succeededCount = $tasks->where('status', DelegationTask::STATUS_SUCCEEDED)->count();
        $failedCount = $tasks->where('status', DelegationTask::STATUS_FAILED)->count();
        $cancelledCount = $tasks->where('status', DelegationTask::STATUS_CANCELLED)->count();
        $totalCount = $tasks->count();

        // Determine final status
        $finalStatus = match (true) {
            $succeededCount === $totalCount => DelegationGraph::STATUS_SUCCEEDED,
            $failedCount === $totalCount => DelegationGraph::STATUS_FAILED,
            $cancelledCount === $totalCount => DelegationGraph::STATUS_CANCELLED,
            $failedCount > 0 || $cancelledCount > 0 => DelegationGraph::STATUS_PARTIAL,
            default => DelegationGraph::STATUS_FAILED, // Unexpected state
        };

        // Transition graph to terminal status
        $transitioned = $this->graphTransition->transition(
            $graph->id,
            DelegationGraph::ACTIVE_STATUSES,
            $finalStatus,
            ['finished_at' => now()]
        );

        if ($transitioned) {
            event(new DelegationGraphCompleted($graph->fresh(), $finalStatus));
        }
    }

    /**
     * Create a sub-delegation task under a parent task.
     *
     * Applies permission attenuation at the delegation boundary
     * to ensure the child never exceeds the parent's permissions.
     *
     * @param  array<string, mixed>  $childContract
     * @return DelegationTask|null The created child task, or null if max depth exceeded or validation failed
     */
    public function createSubDelegation(
        DelegationTask $parentTask,
        array $childContract,
        string $childName,
        int $maxDepth = 3
    ): ?DelegationTask {
        // Check delegation depth limit
        $currentDepth = $parentTask->getDelegationDepth();
        if ($currentDepth >= $maxDepth) {
            return null;
        }

        // Validate permissions don't exceed parent
        $parentContract = $parentTask->contract_json ?? [];
        $violations = $this->permissionAttenuation->validate($parentContract, $childContract);
        if ($violations !== []) {
            // Auto-attenuate instead of rejecting
            $childContract = $this->permissionAttenuation->attenuate($parentContract, $childContract);
        }

        // Create the child task
        return DelegationTask::create([
            'delegation_graph_id' => $parentTask->delegation_graph_id,
            'parent_delegation_task_id' => $parentTask->id,
            'name' => $childName,
            'status' => DelegationTask::STATUS_READY,
            'sequence_order' => $parentTask->sequence_order,
            'contract_json' => $childContract,
        ]);
    }

    private function broadcastTaskCompleted(DelegationTask $task): void
    {
        try {
            $graph = $task->graph;
            $userId = $graph?->user_id;
            if (! $userId) {
                return;
            }

            $dependentNames = $task->dependents()
                ->whereIn('status', [DelegationTask::STATUS_PENDING, DelegationTask::STATUS_BLOCKED])
                ->pluck('name')
                ->all();

            AgentActivityChanged::dispatch($userId, 'delegation.task_completed', [
                'task_name' => $task->name,
                'task_id' => $task->id,
                'delegatee_profile_id' => $task->assigned_delegatee_profile_id,
                'next_tasks' => $dependentNames,
            ]);
        } catch (\Throwable) {
            // Best-effort
        }
    }
}
