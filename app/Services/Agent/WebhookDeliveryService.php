<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDeliveryService
{
    public function deliver(string $url, array $payload, ?string $secret = null): bool
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AgentOps-Webhook/1.0',
            'X-AgentOps-Event' => $payload['event'] ?? 'unknown',
            'X-AgentOps-Delivery' => (string) \Illuminate\Support\Str::uuid(),
        ];

        if ($secret) {
            $headers['X-AgentOps-Signature'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->retry(2, 1000)
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->failed()) {
                Log::warning('WebhookDelivery: Non-2xx response', [
                    'url' => $url,
                    'status' => $response->status(),
                    'event' => $payload['event'] ?? null,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WebhookDelivery: Failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'event' => $payload['event'] ?? null,
            ]);

            return false;
        }
    }

    public static function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
