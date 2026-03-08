<?php

namespace Tests\Feature\Connectors\Security;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\Webhooks\WebhookSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        config(['connectors.webhooks_enabled' => true]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-security-webhook',
        ]);

        $this->webhookSecret = 'whsec_security_test_secret';

        $this->connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $this->connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'config' => ['webhook_secret' => $this->webhookSecret],
        ]);
    }

    public function test_webhook_rejects_replay_attack(): void
    {
        $originalPayload = json_encode(['event' => 'payment.completed', 'amount' => 100]);
        $validSignature = hash_hmac('sha256', $originalPayload, $this->webhookSecret);

        // Modified payload with same signature (replay/tampering attack)
        $tamperedPayload = json_encode(['event' => 'payment.completed', 'amount' => 99999]);

        $response = $this->call(
            'POST',
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/payment.completed",
            [],
            [],
            [],
            [
                'HTTP_X_WEBHOOK_SIGNATURE' => $validSignature,
                'HTTP_X_WEBHOOK_EVENT_ID' => 'evt_replay_001',
                'CONTENT_TYPE' => 'application/json',
            ],
            $tamperedPayload
        );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_rejects_empty_signature(): void
    {
        $payload = json_encode(['event' => 'test']);

        $response = $this->call(
            'POST',
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/test.event",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_EVENT_ID' => 'evt_no_sig_001',
            ],
            $payload
        );

        // No signature header means empty string → verification fails → 401
        $response->assertStatus(401);
    }

    public function test_webhook_timing_safe_comparison(): void
    {
        $verifier = new WebhookSignatureVerifier;

        // Use reflection to verify hash_equals is used in the verify method
        $reflector = new \ReflectionClass($verifier);
        $method = $reflector->getMethod('verify');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Assert that hash_equals is used (constant-time comparison)
        $this->assertStringContainsString('hash_equals', $methodSource,
            'WebhookSignatureVerifier must use hash_equals for timing-safe comparison');

        // Also verify it doesn't use direct comparison operators
        $this->assertStringNotContainsString(' == $', $methodSource,
            'Must not use == for signature comparison');
        $this->assertStringNotContainsString(' === $', $methodSource,
            'Must not use === for signature comparison');
    }
}
