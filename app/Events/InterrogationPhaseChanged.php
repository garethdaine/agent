<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InterrogationPhaseChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $fromPhase,
        public int $toPhase,
        public string $status,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('interrogation.'.$this->sessionId);
    }

    public function broadcastAs(): string
    {
        return 'phase.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'from_phase' => $this->fromPhase,
            'to_phase' => $this->toPhase,
            'status' => $this->status,
        ];
    }
}
