<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RunEventsAvailable implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $runId,
        public readonly int $latestSequence,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('run.'.$this->runId);
    }

    public function broadcastAs(): string
    {
        return 'events.available';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'latest_sequence' => $this->latestSequence,
        ];
    }
}
