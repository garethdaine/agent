<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Connectors\ConnectionLifecycleService;
use App\Support\Connectors\Auth\ApiKeyAuthManager;
use App\Support\Connectors\Auth\OAuthFlowManager;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionLifecycleService $service;

    private ConnectorVaultEncrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encrypter = new ConnectorVaultEncrypter;
        $oauthManager = new OAuthFlowManager($this->encrypter);
        $apiKeyManager = new ApiKeyAuthManager($this->encrypter);
        $this->service = new ConnectionLifecycleService($oauthManager, $apiKeyManager, $this->encrypter);
    }

    public function test_connect_creates_connection_record(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('h', 64)]);
        $user = User::factory()->create();

        $result = $this->service->connect($connector, $team, $user, ['api_key' => 'test-key']);

        $this->assertTrue($result->connected);
        $this->assertNotNull($result->connection);
        $this->assertEquals(AgentConnectorConnection::STATUS_CONNECTED, $result->connection->status);
    }

    public function test_connect_creates_pending_connection_for_oauth(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'authorize_url' => 'https://login.example.com/authorize',
                'token_url' => 'https://login.example.com/token',
                'scopes' => ['read'],
                'pkce' => true,
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('i', 64)]);
        $user = User::factory()->create();

        $result = $this->service->connect($connector, $team, $user);

        $this->assertFalse($result->connected);
        $this->assertNotNull($result->authorizationUrl);
        $this->assertNotNull($result->connection);
        $this->assertEquals(AgentConnectorConnection::STATUS_PENDING, $result->connection->status);
    }

    public function test_connect_generates_vault_key_if_missing(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => null]);
        $user = User::factory()->create();

        $this->assertNull($team->connector_vault_key);

        $this->service->connect($connector, $team, $user, ['api_key' => 'test-key']);

        $team->refresh();
        $this->assertNotNull($team->connector_vault_key);
        $this->assertEquals(64, strlen($team->connector_vault_key));
    }

    public function test_connect_rejects_duplicate_connection(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('j', 64)]);
        $user = User::factory()->create();

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already has an active connection');

        $this->service->connect($connector, $team, $user, ['api_key' => 'test-key']);
    }

    public function test_connect_respects_max_connections_per_team(): void
    {
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('k', 64)]);
        $user = User::factory()->create();

        config()->set('connectors.max_connections_per_team', 2);

        for ($i = 0; $i < 2; $i++) {
            $c = AgentConnector::factory()->create(['auth_type' => 'api_key']);
            AgentConnectorConnection::factory()->create([
                'team_id' => $team->id,
                'connector_id' => $c->id,
                'status' => AgentConnectorConnection::STATUS_CONNECTED,
            ]);
        }

        $newConnector = AgentConnector::factory()->create(['auth_type' => 'api_key']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum number of connections');

        $this->service->connect($newConnector, $team, $user, ['api_key' => 'test-key']);
    }

    public function test_disconnect_revokes_credentials(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('l', 64)]);
        $user = User::factory()->create();

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $credential = AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        $this->service->disconnect($connection, $user);

        $connection->refresh();
        $credential->refresh();

        $this->assertEquals(AgentConnectorConnection::STATUS_DISCONNECTED, $connection->status);
        $this->assertEquals(AgentConnectorCredential::STATUS_REVOKED, $credential->status);
        $this->assertNotNull($connection->disconnected_at);
        $this->assertEquals($user->id, $connection->disconnected_by);
    }

    public function test_disconnect_logs_credential_event(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('m', 64)]);
        $user = User::factory()->create();

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $credential = AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        $this->service->disconnect($connection, $user);

        $event = AgentConnectorCredentialEvent::where('credential_id', $credential->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('revoked', $event->event_type);
        $this->assertEquals($user->id, $event->actor_id);
    }

    public function test_test_connection_returns_health_result(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('n', 64)]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $encrypted = $this->encrypter->encrypt($team, json_encode(['api_key' => 'test-key', 'api_secret' => null]));
        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypted,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.example.com/items' => Http::response(['ok' => true], 200),
        ]);

        $result = $this->service->testConnection($connection);

        $this->assertTrue($result->connected);
        $this->assertGreaterThanOrEqual(0, $result->latencyMs);
        $this->assertNull($result->error);
    }

    public function test_test_connection_handles_auth_failure(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('o', 64)]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $encrypted = $this->encrypter->encrypt($team, json_encode(['api_key' => 'bad-key', 'api_secret' => null]));
        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypted,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.example.com/items' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->service->testConnection($connection);

        $this->assertFalse($result->connected);
        $this->assertNotNull($result->error);
    }

    public function test_test_connection_handles_timeout(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('p', 64)]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $encrypted = $this->encrypter->encrypt($team, json_encode(['api_key' => 'test-key', 'api_secret' => null]));
        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypted,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.example.com/items' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->testConnection($connection);

        $this->assertFalse($result->connected);
        $this->assertNotNull($result->error);
    }
}
