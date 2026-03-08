<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Commands;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Models\AgentConnectorWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorPruneTelemetryCommandTest extends TestCase
{
    use RefreshDatabase;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = AgentConnector::factory()->create();
        $this->connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $this->connector->id,
        ]);
    }

    public function test_prunes_invocations_older_than_retention(): void
    {
        AgentConnectorInvocation::create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'action_name' => 'old-action',
            'http_method' => 'GET',
            'outcome' => 'success',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('connector:prune-telemetry', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('agent_connector_invocations', [
            'action_name' => 'old-action',
        ]);
    }

    public function test_preserves_recent_invocations(): void
    {
        AgentConnectorInvocation::create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'action_name' => 'recent-action',
            'http_method' => 'GET',
            'outcome' => 'success',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('connector:prune-telemetry', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseHas('agent_connector_invocations', [
            'action_name' => 'recent-action',
        ]);
    }

    public function test_prunes_webhook_events_older_than_retention(): void
    {
        AgentConnectorWebhookEvent::create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'event_type' => 'old.event',
            'external_event_id' => 'ext_old',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => 'routed',
            'created_at' => now()->subDays(100),
        ]);

        AgentConnectorWebhookEvent::create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
            'event_type' => 'recent.event',
            'external_event_id' => 'ext_recent',
            'payload_hash' => hash('sha256', '{}'),
            'processing_status' => 'routed',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('connector:prune-telemetry', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('agent_connector_webhook_events', [
            'event_type' => 'old.event',
        ]);
        $this->assertDatabaseHas('agent_connector_webhook_events', [
            'event_type' => 'recent.event',
        ]);
    }
}
