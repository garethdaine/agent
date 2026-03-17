<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowInterrogationSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $sessionId,
        public array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('workflow-interrogation.'.$this->sessionId);
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
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded !== false && strlen($encoded) > 8192) {
            $payload = $this->truncatePayload($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function truncatePayload(array $payload): array
    {
        if (isset($payload['session']) && is_array($payload['session'])) {
            $payload['session']['summary_json'] = ['_truncated' => true];
            $payload['session']['action_plan_json'] = ['_truncated' => true];

            if (is_array($payload['session']['active_batch'] ?? null)) {
                $payload['session']['active_batch'] = [
                    '_truncated' => true,
                    'round' => $payload['session']['active_batch']['round'] ?? null,
                    'question_count' => count((array) ($payload['session']['active_batch']['questions'] ?? [])),
                ];
            }

            if (is_array($payload['session']['metadata_json'] ?? null)) {
                $payload['session']['metadata_json'] = ['_truncated' => true];
            }
        }

        if (is_array($payload['payload'] ?? null)) {
            $payload['payload'] = [
                '_truncated' => true,
                'question_count' => count((array) ($payload['payload']['questions'] ?? [])),
                'keys' => array_values(array_filter(
                    array_keys($payload['payload']),
                    static fn (mixed $key): bool => is_string($key)
                )),
            ];
        }

        $payload['_truncated'] = true;

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) > 8192) {
            if (isset($payload['session']) && is_array($payload['session'])) {
                $payload['session'] = [
                    'id' => $payload['session']['id'] ?? $this->sessionId,
                    'status' => $payload['session']['status'] ?? null,
                    'phase' => $payload['session']['phase'] ?? null,
                    'current_round' => $payload['session']['current_round'] ?? null,
                    'processing' => $payload['session']['processing'] ?? null,
                    'error_code' => $payload['session']['error_code'] ?? null,
                    'error_summary' => $payload['session']['error_summary'] ?? null,
                    '_truncated' => true,
                ];
            }

            if (is_array($payload['payload'] ?? null)) {
                $payload['payload'] = ['_truncated' => true];
            }
        }

        return $payload;
    }
}
