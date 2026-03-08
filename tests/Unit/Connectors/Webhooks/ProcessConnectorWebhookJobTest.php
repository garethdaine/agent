<?php

namespace Tests\Unit\Connectors\Webhooks;

use App\Jobs\Connectors\ProcessConnectorWebhookJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessConnectorWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_event_and_marks_routed(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $event = AgentConnectorWebhookEvent::create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'event_type' => 'invoice.created',
            'external_event_id' => 'ext_123',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
            'created_at' => now(),
        ]);

        $job = new ProcessConnectorWebhookJob($event);
        $job->handle();

        $event->refresh();
        $this->assertEquals(AgentConnectorWebhookEvent::STATUS_ROUTED, $event->processing_status);
    }

    public function test_moves_to_dead_letter_after_max_retries(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $event = AgentConnectorWebhookEvent::create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'event_type' => 'invoice.created',
            'external_event_id' => 'ext_dead',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
            'retry_count' => 3,
            'created_at' => now(),
        ]);

        $job = new ProcessConnectorWebhookJob($event);
        $job->failed(new \RuntimeException('Processing failed'));

        $event->refresh();
        $this->assertEquals(AgentConnectorWebhookEvent::STATUS_DEAD_LETTER, $event->processing_status);
        $this->assertNotNull($event->error_message);
    }

    public function test_records_processing_duration(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $event = AgentConnectorWebhookEvent::create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'event_type' => 'invoice.created',
            'external_event_id' => 'ext_duration',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
            'created_at' => now(),
        ]);

        $job = new ProcessConnectorWebhookJob($event);
        $job->handle();

        $event->refresh();
        $this->assertNotNull($event->processing_duration_ms);
        $this->assertIsInt($event->processing_duration_ms);
    }

    public function test_dispatched_on_connector_webhooks_queue(): void
    {
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
        ]);

        $event = AgentConnectorWebhookEvent::create([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'event_type' => 'test',
            'external_event_id' => 'ext_queue',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
            'created_at' => now(),
        ]);

        $job = new ProcessConnectorWebhookJob($event);

        $this->assertEquals('connector-webhooks', $job->queue);
    }
}
