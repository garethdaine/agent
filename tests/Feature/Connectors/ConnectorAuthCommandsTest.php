<?php

namespace Tests\Feature\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectorAuthCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_connect_with_api_key_flag(): void
    {
        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
        ]);
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'admin']);

        $this->artisan('connector:connect', [
            'name' => 'xero',
            '--api-key' => 'test_key_123',
            '--team' => $team->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('agent_connector_connections', [
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $credential = AgentConnectorCredential::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->first();
        $this->assertNotNull($credential);
    }

    public function test_connector_disconnect_revokes_and_removes(): void
    {
        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'auth_type' => 'api_key',
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('q', 64)]);
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'admin']);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        $this->artisan('connector:disconnect', [
            'name' => 'xero',
            '--team' => $team->id,
            '--force' => true,
        ])->assertSuccessful();

        $connection->refresh();
        $this->assertEquals(AgentConnectorConnection::STATUS_DISCONNECTED, $connection->status);
    }

    public function test_connector_test_reports_health(): void
    {
        $encrypter = new ConnectorVaultEncrypter;
        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.xero.com',
            'actions' => [
                ['name' => 'list-invoices', 'method' => 'GET', 'path' => '/invoices'],
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('r', 64)]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $encrypted = $encrypter->encrypt($team, json_encode(['api_key' => 'test-key', 'api_secret' => null]));
        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypted,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.xero.com/invoices' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('connector:test', [
            'name' => 'xero',
            '--team' => $team->id,
        ])
            ->expectsOutputToContain('Connected')
            ->assertSuccessful();
    }

    public function test_connector_connect_unknown_connector_fails(): void
    {
        $team = Team::factory()->create();

        $this->artisan('connector:connect', [
            'name' => 'nonexistent',
            '--api-key' => 'test-key',
            '--team' => $team->id,
        ])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }
}
