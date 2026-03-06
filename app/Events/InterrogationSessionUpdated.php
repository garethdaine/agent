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
        $payload = $this->payload;

        // Truncate payload to stay under Pusher/Reverb's ~10KB broadcast limit.
        // If the serialized payload exceeds 8KB, strip large fields and set a
        // truncated flag so the client knows to fetch the full data via API.
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) > 8192) {
            $payload = array_map(function ($value) {
                if (is_string($value) && strlen($value) > 500) {
                    return mb_substr($value, 0, 500).'… [truncated]';
                }
                if (is_array($value)) {
                    $nested = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($nested !== false && strlen($nested) > 1000) {
                        return ['_truncated' => true, '_original_size' => strlen($nested)];
                    }
                }

                return $value;
            }, $payload);
            $payload['_truncated'] = true;
        }

        return [
            'session_id' => $this->sessionId,
            'event_type' => $this->eventType,
            'payload' => $payload,
            'sequence' => $this->sequence,
        ];
    }
}
