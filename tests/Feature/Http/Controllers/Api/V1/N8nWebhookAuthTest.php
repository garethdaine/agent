<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class N8nWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookUrl = '/agent/api/v1/n8n/webhook';

    public function test_unauthenticated_post_returns_401(): void
    {
        config(['n8n.enabled' => true, 'n8n.webhook_secret' => 'test-secret']);

        $response = $this->postJson($this->webhookUrl, ['event' => 'test']);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'message' => 'Missing webhook signature.']);
    }

    public function test_invalid_signature_returns_401(): void
    {
        config(['n8n.enabled' => true, 'n8n.webhook_secret' => 'test-secret']);

        $response = $this->postJson($this->webhookUrl, ['event' => 'test'], [
            'X-N8N-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'message' => 'Invalid webhook signature.']);
    }

    public function test_missing_webhook_secret_config_returns_403(): void
    {
        config(['n8n.enabled' => true, 'n8n.webhook_secret' => null]);

        $response = $this->postJson($this->webhookUrl, ['event' => 'test']);

        $response->assertStatus(403);
        $response->assertJson(['ok' => false, 'message' => 'Webhook authentication is not configured.']);
    }

    public function test_valid_signature_passes_through(): void
    {
        $secret = 'test-secret';
        config(['n8n.enabled' => true, 'n8n.webhook_secret' => $secret]);

        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = $this->call('POST', $this->webhookUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_N8N_SIGNATURE' => $signature,
        ], $payload);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }
}
