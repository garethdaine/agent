<?php

namespace App\Events\Office;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentActivityChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $eventType,
        public readonly array $payload = []
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('office.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'activity.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'at' => now()->toIso8601String(),
        ];
    }
}
