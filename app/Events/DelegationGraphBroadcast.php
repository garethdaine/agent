<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for delegation graph updates.
 *
 * Sent to per-graph private channel for real-time UI updates.
 * This event is queued via ShouldBroadcast to avoid blocking
 * the main delegation workflow.
 */
class DelegationGraphBroadcast implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $graphId  The delegation graph ID
     * @param  string  $eventType  The type of event (e.g., 'graph.started', 'task.completed')
     * @param  array<string, mixed>  $payload  Enriched payload with graph status, task counts, etc.
     */
    public function __construct(
        public readonly int $graphId,
        public readonly string $eventType,
        public readonly array $payload
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('delegation.graph.'.$this->graphId);
    }

    public function broadcastAs(): string
    {
        return 'delegation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'graph_id' => $this->graphId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
        ];
    }
}
