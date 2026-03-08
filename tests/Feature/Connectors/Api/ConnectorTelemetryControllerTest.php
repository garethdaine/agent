<?php

namespace Tests\Feature\Connectors\Api;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorTelemetryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->switchTeam($this->team);
        $this->team->users()->attach($this->user, ['role' => 'admin']);

        $this->connector = AgentConnector::factory()->create();
        $this->connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
        ]);

        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
    }

    public function test_telemetry_returns_invocation_history(): void
    {
        AgentConnectorInvocation::factory()->count(3)->create([
            'connection_id' => $this->connection->id,
            'connector_id' => $this->connector->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/agent/api/v1/connectors/{$this->connector->id}/telemetry");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action_name', 'http_method', 'http_status', 'duration_ms', 'outcome', 'created_at'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_telemetry_requires_admin_or_operator(): void
    {
        $regularUser = User::factory()->create();
        $this->team->users()->attach($regularUser, ['role' => 'editor']);
        $regularUser->switchTeam($this->team);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->getJson("/agent/api/v1/connectors/{$this->connector->id}/telemetry");

        $response->assertForbidden();
    }
}
