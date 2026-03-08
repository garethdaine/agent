<?php

namespace Tests\Feature\Connectors\Commands;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectorRotateKeysCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotates_vault_key_and_re_encrypts_credentials(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => Str::random(64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $vault = app(ConnectorVaultEncrypter::class);
        $originalData = json_encode(['access_token' => 'test_token_123']);
        $encrypted = $vault->encrypt($team, $originalData);

        $credential = AgentConnectorCredential::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypted,
            'encryption_key_id' => 'v1',
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'rotation_count' => 0,
        ]);

        $oldVaultKey = $team->connector_vault_key;

        $this->artisan('connector:rotate-keys', ['--team' => $team->id, '--force' => true])
            ->assertSuccessful();

        $team->refresh();
        $credential->refresh();

        $this->assertNotEquals($oldVaultKey, $team->connector_vault_key);

        // Credentials should still be decryptable with new key
        $encryptedData = $credential->encrypted_data;
        if (is_resource($encryptedData)) {
            $encryptedData = stream_get_contents($encryptedData);
        }
        $decrypted = $vault->decrypt($team, $encryptedData);
        $this->assertEquals($originalData, $decrypted);
    }

    public function test_increments_rotation_count(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => Str::random(64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $vault = app(ConnectorVaultEncrypter::class);

        $credential = AgentConnectorCredential::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $vault->encrypt($team, 'test'),
            'encryption_key_id' => 'v1',
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'rotation_count' => 2,
        ]);

        $this->artisan('connector:rotate-keys', ['--team' => $team->id, '--force' => true])
            ->assertSuccessful();

        $credential->refresh();
        $this->assertEquals(3, $credential->rotation_count);
    }

    public function test_logs_credential_rotation_event(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => Str::random(64)]);
        $connector = AgentConnector::factory()->create();
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $vault = app(ConnectorVaultEncrypter::class);

        AgentConnectorCredential::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $vault->encrypt($team, 'test'),
            'encryption_key_id' => 'v1',
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'rotation_count' => 0,
        ]);

        $this->artisan('connector:rotate-keys', ['--team' => $team->id, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('agent_connector_credential_events', [
            'event_type' => 'rotated',
        ]);
    }
}
