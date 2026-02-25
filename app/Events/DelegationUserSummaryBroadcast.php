<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for user-level delegation summary updates.
 *
 * Sent to per-user private channel for dashboard and notification updates.
 * This event is queued via ShouldBroadcast to avoid blocking
 * the main delegation workflow.
 */
class DelegationUserSummaryBroadcast implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $userId  The user ID
     * @param  int  $graphId  The delegation graph ID that triggered this update
     * @param  string  $eventType  The type of event (e.g., 'graph.started', 'graph.completed')
     * @param  array<string, mixed>  $payload  Summary payload with aggregated status info
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $graphId,
        public readonly string $eventType,
        public readonly array $payload
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('delegation.user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'delegation.summary';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'graph_id' => $this->graphId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
        ];
    }
}
