<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Integration;

use App\Jobs\Connectors\ProcessConnectorWebhookJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookIntegrationTest extends TestCase
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
            'name' => 'test-webhook-connector',
        ]);

        $this->webhookSecret = 'whsec_test_secret_12345';

        $this->connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $this->connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'config' => ['webhook_secret' => $this->webhookSecret],
        ]);
    }

    private function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }

    public function test_webhook_received_and_processed(): void
    {
        Queue::fake();

        $payload = json_encode(['action' => 'invoice.created', 'data' => ['id' => 'inv_123']]);
        $signature = $this->signPayload($payload);

        $response = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/invoice.created",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_unique_001',
                'Content-Type' => 'application/json',
            ]
        );

        $response->assertOk()
            ->assertJson(['received' => true]);

        // Verify event created
        $this->assertDatabaseHas('agent_connector_webhook_events', [
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'event_type' => 'invoice.created',
            'external_event_id' => 'evt_unique_001',
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
        ]);

        // Verify job dispatched
        Queue::assertPushed(ProcessConnectorWebhookJob::class, function ($job) {
            return $job->webhookEvent->event_type === 'invoice.created';
        });

        // Now actually process the job
        $event = AgentConnectorWebhookEvent::where('external_event_id', 'evt_unique_001')->first();
        $job = new ProcessConnectorWebhookJob($event);
        $job->handle();

        // Verify processing complete
        $event->refresh();
        $this->assertEquals(AgentConnectorWebhookEvent::STATUS_ROUTED, $event->processing_status);
        $this->assertGreaterThanOrEqual(0, $event->processing_duration_ms);
    }

    public function test_webhook_deduplication_works(): void
    {
        Queue::fake();

        $payload = json_encode(['action' => 'contact.updated', 'data' => ['id' => 'c_456']]);
        $signature = $this->signPayload($payload);

        $headers = [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event-Id' => 'evt_duplicate_001',
            'Content-Type' => 'application/json',
        ];

        // First delivery
        $response1 = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/contact.updated",
            json_decode($payload, true),
            $headers
        );
        $response1->assertOk();

        // Second delivery (same event_id)
        $response2 = $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/contact.updated",
            json_decode($payload, true),
            $headers
        );
        $response2->assertOk()
            ->assertJson(['deduplicated' => true]);

        // Assert single record
        $eventCount = AgentConnectorWebhookEvent::where('connection_id', $this->connection->id)
            ->where('external_event_id', 'evt_duplicate_001')
            ->count();
        $this->assertEquals(1, $eventCount);

        // Only one job dispatched
        Queue::assertPushed(ProcessConnectorWebhookJob::class, 1);
    }

    public function test_webhook_dead_letter_after_failures(): void
    {
        Queue::fake();

        $payload = json_encode(['action' => 'payment.failed']);
        $signature = $this->signPayload($payload);

        $this->postJson(
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/payment.failed",
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-Id' => 'evt_fail_001',
                'Content-Type' => 'application/json',
            ]
        );

        $event = AgentConnectorWebhookEvent::where('external_event_id', 'evt_fail_001')->first();

        // Simulate 3 processing failures via the failed() method
        $exception = new \RuntimeException('Processing error');
        for ($i = 0; $i < 3; $i++) {
            $job = new ProcessConnectorWebhookJob($event->fresh());
            $job->failed($exception);
        }

        // Verify dead_letter status
        $event->refresh();
        $this->assertEquals(AgentConnectorWebhookEvent::STATUS_DEAD_LETTER, $event->processing_status);
        $this->assertNotNull($event->error_message);
        $this->assertStringContainsString('Processing error', $event->error_message);
    }
}
