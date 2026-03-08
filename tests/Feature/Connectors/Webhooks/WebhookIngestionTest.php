<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Webhooks;

use App\Jobs\Connectors\ProcessConnectorWebhookJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorWebhookEvent;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    private string $webhookSecret = 'whsec_test_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();

        config(['connectors.webhooks_enabled' => true]);

        $this->team = Team::factory()->create();
        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-connector',
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);
        $this->connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'config' => ['webhook_secret' => $this->webhookSecret],
        ]);
    }

    public function test_webhook_endpoint_accepts_valid_signed_request(): void
    {
        $payload = json_encode(['event' => 'invoice.created', 'data' => ['id' => '123']]);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $response = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/invoice.created",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_001',
            ]
        );

        $response->assertOk();
    }

    public function test_webhook_endpoint_rejects_invalid_signature(): void
    {
        $payload = json_encode(['event' => 'invoice.created']);

        $response = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/invoice.created",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => 'invalid_signature',
                'X-Webhook-Event-Id' => 'evt_002',
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_webhook_endpoint_rejects_when_webhooks_disabled(): void
    {
        config(['connectors.webhooks_enabled' => false]);

        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $response = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/test",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_003',
            ]
        );

        $response->assertNotFound();
    }

    public function test_webhook_creates_event_record(): void
    {
        Queue::fake();

        $payload = json_encode(['event' => 'contact.updated', 'data' => ['name' => 'Test']]);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/contact.updated",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_004',
            ]
        );

        $this->assertDatabaseHas('agent_connector_webhook_events', [
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'event_type' => 'contact.updated',
            'external_event_id' => 'evt_004',
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
        ]);
    }

    public function test_webhook_deduplicates_by_external_event_id(): void
    {
        Queue::fake();

        $payload = json_encode(['event' => 'invoice.created']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);
        $headers = [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event-Id' => 'evt_duplicate',
        ];

        $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/invoice.created",
            json_decode($payload, true),
            $headers
        );

        $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/invoice.created",
            json_decode($payload, true),
            $headers
        );

        $count = AgentConnectorWebhookEvent::where('external_event_id', 'evt_duplicate')
            ->where('connection_id', $this->connection->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_webhook_dispatches_processing_job(): void
    {
        Queue::fake();

        $payload = json_encode(['event' => 'payment.received']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/payment.received",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_dispatch',
            ]
        );

        Queue::assertPushedOn('connector-webhooks', ProcessConnectorWebhookJob::class);
    }

    public function test_webhook_returns_200_even_for_unknown_connector(): void
    {
        $payload = json_encode(['event' => 'test']);

        $response = $this->postJson(
            '/agent/api/v1/connectors/nonexistent-connector/webhooks/test',
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => 'any',
                'X-Webhook-Event-Id' => 'evt_unknown',
            ]
        );

        $response->assertOk();
    }
}
