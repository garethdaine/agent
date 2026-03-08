<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Api;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectorConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private AgentConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->switchTeam($this->team);
        $this->team->users()->attach($this->user, ['role' => 'admin']);

        $this->connector = AgentConnector::factory()->create([
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/invoices'],
            ],
        ]);

        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
    }

    public function test_connect_with_api_key_creates_connection(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/agent/api/v1/connectors/{$this->connector->id}/connect", [
                'api_key' => 'test_key_abc123',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'connector', 'status'],
            ]);

        $this->assertDatabaseHas('agent_connector_connections', [
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);
    }

    public function test_connect_oauth_returns_authorization_url(): void
    {
        $oauthConnector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test_client',
                'client_secret' => 'test_secret',
                'authorize_url' => 'https://provider.example.com/authorize',
                'token_url' => 'https://provider.example.com/token',
                'scopes' => ['read', 'write'],
                'pkce' => true,
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/agent/api/v1/connectors/{$oauthConnector->id}/connect");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['authorization_url'],
            ]);

        $this->assertStringContainsString(
            'https://provider.example.com/authorize',
            $response->json('data.authorization_url')
        );
    }

    public function test_disconnect_revokes_and_returns_204(): void
    {
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'connected_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/agent/api/v1/connectors/{$this->connector->id}/disconnect");

        $response->assertNoContent();

        $this->assertDatabaseHas('agent_connector_connections', [
            'id' => $connection->id,
            'status' => AgentConnectorConnection::STATUS_DISCONNECTED,
        ]);
    }

    public function test_disconnect_requires_admin(): void
    {
        $regularUser = User::factory()->create();
        $this->team->users()->attach($regularUser, ['role' => 'editor']);
        $regularUser->switchTeam($this->team);

        AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->deleteJson("/agent/api/v1/connectors/{$this->connector->id}/disconnect");

        $response->assertForbidden();
    }

    public function test_test_returns_health_result(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'connected_by' => $this->user->id,
        ]);

        $encrypter = app(\App\Support\Connectors\ConnectorVaultEncrypter::class);
        $this->team->update(['connector_vault_key' => \Illuminate\Support\Str::random(64)]);

        AgentConnectorCredential::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'encrypted_data' => $encrypter->encrypt($this->team->fresh(), json_encode(['api_key' => 'test_key'])),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/agent/api/v1/connectors/{$this->connector->id}/test");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['connected', 'latency_ms'],
            ]);
    }

    public function test_health_returns_connection_health(): void
    {
        AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'health_score' => 0.95,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/agent/api/v1/connectors/{$this->connector->id}/health");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['health_score', 'status'],
            ]);
    }
}
