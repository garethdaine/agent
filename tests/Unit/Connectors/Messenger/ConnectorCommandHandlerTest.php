<?php

namespace Tests\Unit\Connectors\Messenger;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Services\Connectors\ConnectionLifecycleService;
use App\Support\Connectors\Messenger\ConnectorCommandHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConnectorCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorCommandHandler $handler;

    private ConnectionLifecycleService $lifecycleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lifecycleService = Mockery::mock(ConnectionLifecycleService::class);
        $this->handler = new ConnectorCommandHandler($this->lifecycleService);
    }

    public function test_list_connections_command_returns_connected_services(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('a', 64)]);
        $user->current_team_id = $team->id;
        $user->save();

        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'display_name' => 'Xero',
            'auth_type' => 'oauth2_authorization_code',
        ]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 0.95,
        ]);

        $result = $this->handler->handleCommand('list', ['connections'], $user);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Xero', $result->message);
        $this->assertStringContainsString('connected', $result->message);
    }

    public function test_list_connections_command_handles_empty(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->current_team_id = $team->id;
        $user->save();

        $result = $this->handler->handleCommand('list', ['connections'], $user);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No services connected', $result->message);
    }

    public function test_test_command_runs_health_check(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('b', 64)]);
        $user->current_team_id = $team->id;
        $user->save();

        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'display_name' => 'Xero',
            'auth_type' => 'api_key',
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $testResult = new \App\DTOs\Connectors\ConnectionTestResult(
            connected: true,
            latencyMs: 42.5,
            error: null,
        );

        $this->lifecycleService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn($testResult);

        $result = $this->handler->handleCommand('test', ['xero'], $user);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Xero', $result->message);
        $this->assertStringContainsString('healthy', $result->message);
    }

    public function test_test_command_handles_unknown_service(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->current_team_id = $team->id;
        $user->save();

        $result = $this->handler->handleCommand('test', ['nonexistent'], $user);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }

    public function test_disconnect_command_requests_confirmation(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('c', 64)]);
        $user->current_team_id = $team->id;
        $user->save();

        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'display_name' => 'Xero',
            'auth_type' => 'api_key',
        ]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);

        $result = $this->handler->handleCommand('disconnect', ['xero'], $user);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('confirm', strtolower($result->message));
        $this->assertStringContainsString('Xero', $result->message);
    }

    public function test_connect_command_returns_instructions(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->current_team_id = $team->id;
        $user->save();

        $connector = AgentConnector::factory()->create([
            'name' => 'xero',
            'display_name' => 'Xero',
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-secret',
                'authorize_url' => 'https://login.xero.com/authorize',
                'token_url' => 'https://login.xero.com/token',
                'scopes' => ['openid', 'accounting.transactions'],
                'pkce' => true,
            ],
        ]);

        $result = $this->handler->handleCommand('connect', ['xero'], $user);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Xero', $result->message);
        // OAuth connectors should mention authorization
        $this->assertStringContainsString('OAuth', $result->message);
    }
}
