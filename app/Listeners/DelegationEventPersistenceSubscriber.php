<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Delegation\DelegationEventWriter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists delegation domain events to the delegation_events table for audit
 * and replay purposes.
 *
 * Mirrors the broadcast subscriber pattern but writes to database instead
 * of broadcasting to WebSocket channels.
 */
class DelegationEventPersistenceSubscriber implements ShouldQueue
{
    public string $queue = 'delegation';

    public function __construct(
        private readonly FeatureFlagManager $featureFlags,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DelegationGraphStarted::class, [self::class, 'handleGraphStarted']);
        $events->listen(DelegationAttemptCompleted::class, [self::class, 'handleAttemptCompleted']);
        $events->listen(DelegationTaskVerified::class, [self::class, 'handleTaskVerified']);
        $events->listen(DelegationGraphCompleted::class, [self::class, 'handleGraphCompleted']);
    }

    public function handleGraphStarted(DelegationGraphStarted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $graph = $event->graph->fresh();
        if ($graph === null) {
            return;
        }

        $writer = new DelegationEventWriter($graph);
        $writer->write('graph.started', [
            'status' => $graph->status,
            'max_parallel_tasks' => $graph->max_parallel_tasks,
        ]);
    }

    public function handleAttemptCompleted(DelegationAttemptCompleted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $attempt = $event->attempt->fresh(['task', 'task.graph']);
        if ($attempt === null || $attempt->task === null || $attempt->task->graph === null) {
            return;
        }

        $graph = $attempt->task->graph;
        $writer = new DelegationEventWriter($graph);
        $writer->write('attempt.completed', [
            'task_id' => $attempt->task->id,
            'task_name' => $attempt->task->name,
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status,
            'duration_ms' => $attempt->duration_ms,
        ], $attempt->task);
    }

    public function handleTaskVerified(DelegationTaskVerified $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $task = $event->task->fresh(['graph']);
        if ($task === null || $task->graph === null) {
            return;
        }

        $payload = [
            'task_id' => $task->id,
            'task_name' => $task->name,
            'passed' => $event->passed,
        ];

        if (! $event->passed && $event->failedStepOrder !== null) {
            $payload['failed_step_order'] = $event->failedStepOrder;
        }

        $writer = new DelegationEventWriter($task->graph);
        $writer->write('task.verified', $payload, $task);
    }

    public function handleGraphCompleted(DelegationGraphCompleted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $graph = $event->graph->fresh();
        if ($graph === null) {
            return;
        }

        $writer = new DelegationEventWriter($graph);
        $writer->write('graph.completed', [
            'final_status' => $event->finalStatus,
        ]);
    }
}
