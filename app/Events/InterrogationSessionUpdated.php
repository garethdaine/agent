<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InterrogationSessionUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $sessionId,
        public string $eventType,
        public array $payload,
        public int $sequence,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('interrogation.'.$this->sessionId);
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'sequence' => $this->sequence,
        ];
    }
}
