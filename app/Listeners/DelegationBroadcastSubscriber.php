<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphBroadcast;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Events\DelegationUserSummaryBroadcast;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Subscribes to all delegation domain events and broadcasts to WebSocket channels.
 *
 * This listener implements ShouldQueue to ensure broadcasting does not block
 * the main delegation workflow. It enriches events with graph status and task
 * counts before broadcasting to per-graph and per-user private channels.
 *
 * Channels:
 * - delegation.graph.{graphId}: Per-graph updates for real-time UI
 * - delegation.user.{userId}: User-level summary for dashboard/notifications
 */
class DelegationBroadcastSubscriber implements ShouldQueue
{
    private readonly FeatureFlagManager $featureFlags;

    public function __construct(?FeatureFlagManager $featureFlags = null)
    {
        $this->featureFlags = $featureFlags ?? app(FeatureFlagManager::class);
    }

    /**
     * The queue this listener should be dispatched on.
     */
    public string $queue = 'delegation';

    /**
     * Subscribe to delegation events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DelegationGraphStarted::class, [self::class, 'handleGraphStarted']);
        $events->listen(DelegationAttemptCompleted::class, [self::class, 'handleAttemptCompleted']);
        $events->listen(DelegationTaskVerified::class, [self::class, 'handleTaskVerified']);
        $events->listen(DelegationGraphCompleted::class, [self::class, 'handleGraphCompleted']);
    }

    /**
     * Handle graph started event.
     */
    public function handleGraphStarted(DelegationGraphStarted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $graph = $event->graph->fresh();
        if ($graph === null) {
            return;
        }

        $payload = $this->buildGraphPayload($graph);
        $payload['event'] = 'started';

        $this->broadcastGraphEvent($graph, 'graph.started', $payload);
        $this->broadcastUserSummary($graph, 'graph.started', $payload);
    }

    /**
     * Handle graph completed event.
     */
    public function handleGraphCompleted(DelegationGraphCompleted $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $graph = $event->graph->fresh();
        if ($graph === null) {
            return;
        }

        $payload = $this->buildGraphPayload($graph);
        $payload['final_status'] = $event->finalStatus;
        $payload['event'] = 'completed';

        $this->broadcastGraphEvent($graph, 'graph.completed', $payload);
        $this->broadcastUserSummary($graph, 'graph.completed', $payload);
    }

    /**
     * Handle attempt completed event.
     */
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
        $payload = $this->buildGraphPayload($graph);
        $payload['task_id'] = $attempt->task->id;
        $payload['task_name'] = $attempt->task->name;
        $payload['attempt_id'] = $attempt->id;
        $payload['attempt_number'] = $attempt->attempt_number;
        $payload['status'] = $attempt->status;
        $payload['event'] = 'attempt_completed';

        $this->broadcastGraphEvent($graph, 'attempt.completed', $payload);
        $this->broadcastUserSummary($graph, 'attempt.completed', $payload);
    }

    /**
     * Handle task verified event.
     */
    public function handleTaskVerified(DelegationTaskVerified $event): void
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return;
        }

        $task = $event->task->fresh(['graph']);
        if ($task === null || $task->graph === null) {
            return;
        }

        $graph = $task->graph;
        $payload = $this->buildGraphPayload($graph);
        $payload['task_id'] = $task->id;
        $payload['task_name'] = $task->name;
        $payload['attempt_id'] = $event->attempt->id;
        $payload['passed'] = $event->passed;
        $payload['event'] = 'task_verified';

        if (! $event->passed && $event->failedStepOrder !== null) {
            $payload['failed_step_order'] = $event->failedStepOrder;
        }

        $this->broadcastGraphEvent($graph, 'task.verified', $payload);
        $this->broadcastUserSummary($graph, 'task.verified', $payload);
    }

    /**
     * Build the base payload with graph status and task counts.
     *
     * @return array<string, mixed>
     */
    private function buildGraphPayload(DelegationGraph $graph): array
    {
        $taskCounts = $this->getTaskCounts($graph);

        return [
            'graph_id' => $graph->id,
            'graph_name' => $graph->name,
            'graph_status' => $graph->status,
            'task_counts' => $taskCounts,
        ];
    }

    /**
     * Get task counts by status for the graph.
     *
     * @return array<string, int>
     */
    private function getTaskCounts(DelegationGraph $graph): array
    {
        $tasks = $graph->tasks()->get();

        return [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', DelegationTask::STATUS_PENDING)->count(),
            'blocked' => $tasks->where('status', DelegationTask::STATUS_BLOCKED)->count(),
            'ready' => $tasks->where('status', DelegationTask::STATUS_READY)->count(),
            'assigned' => $tasks->where('status', DelegationTask::STATUS_ASSIGNED)->count(),
            'running' => $tasks->where('status', DelegationTask::STATUS_RUNNING)->count(),
            'verifying' => $tasks->where('status', DelegationTask::STATUS_VERIFYING)->count(),
            'succeeded' => $tasks->where('status', DelegationTask::STATUS_SUCCEEDED)->count(),
            'failed' => $tasks->where('status', DelegationTask::STATUS_FAILED)->count(),
            'cancelled' => $tasks->where('status', DelegationTask::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Broadcast to the per-graph channel.
     *
     * @param  array<string, mixed>  $payload
     */
    private function broadcastGraphEvent(DelegationGraph $graph, string $eventType, array $payload): void
    {
        event(new DelegationGraphBroadcast(
            $graph->id,
            $eventType,
            $payload
        ));
    }

    /**
     * Broadcast to the per-user summary channel.
     *
     * @param  array<string, mixed>  $payload
     */
    private function broadcastUserSummary(DelegationGraph $graph, string $eventType, array $payload): void
    {
        if ($graph->user_id === null) {
            return;
        }

        event(new DelegationUserSummaryBroadcast(
            $graph->user_id,
            $graph->id,
            $eventType,
            $payload
        ));
    }
}
