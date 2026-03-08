<?php

namespace Tests\Feature\Connectors\Commands;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_command_shows_all_connections(): void
    {
        $team = Team::factory()->create();
        $connector1 = AgentConnector::factory()->create(['display_name' => 'Xero']);
        $connector2 = AgentConnector::factory()->create(['display_name' => 'HubSpot']);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector1->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 0.95,
        ]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector2->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 0.85,
        ]);

        $this->artisan('connector:health', ['--team' => $team->id])
            ->expectsOutputToContain('Xero')
            ->expectsOutputToContain('HubSpot')
            ->assertSuccessful();
    }

    public function test_health_command_highlights_degraded(): void
    {
        $team = Team::factory()->create();
        $connector = AgentConnector::factory()->create(['display_name' => 'Degraded Service']);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_DEGRADED,
            'health_score' => 0.45,
        ]);

        \Illuminate\Support\Facades\Artisan::call('connector:health', ['--team' => $team->id]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Degraded Service', $output);
        $this->assertStringContainsString('unhealthy', $output);
    }
}
