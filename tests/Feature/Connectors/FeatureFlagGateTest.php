<?php

namespace Tests\Feature\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ConnectorVaultEncrypter;
use App\Support\Connectors\Pipeline\ConnectorExecutionPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeatureFlagGateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private AgentConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'user_id' => $this->user->id,
            'connector_vault_key' => Str::random(64),
        ]);
        $this->user->switchTeam($this->team);
        $this->team->users()->attach($this->user, ['role' => 'admin']);

        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-flag-connector',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
                ['name' => 'create-item', 'method' => 'POST', 'path' => '/items'],
            ],
            'rate_limits' => ['requests_per_minute' => 60],
        ]);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_all_api_routes_404_when_connectors_disabled(): void
    {
        // Connectors default to disabled — config value is false, no DB record
        config(['connectors.enabled' => false]);

        $routes = [
            ['GET', '/agent/api/v1/connectors'],
            ['GET', "/agent/api/v1/connectors/{$this->connector->id}"],
            ['GET', "/agent/api/v1/connectors/{$this->connector->id}/actions"],
            ['POST', "/agent/api/v1/connectors/{$this->connector->id}/connect"],
            ['DELETE', "/agent/api/v1/connectors/{$this->connector->id}/disconnect"],
            ['POST', "/agent/api/v1/connectors/{$this->connector->id}/test"],
            ['GET', "/agent/api/v1/connectors/{$this->connector->id}/health"],
            ['GET', "/agent/api/v1/connectors/{$this->connector->id}/telemetry"],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->json($method, $url);

            $this->assertTrue(
                in_array($response->getStatusCode(), [404, 403]),
                "Route {$method} {$url} should be blocked when connectors disabled, got {$response->getStatusCode()}"
            );
        }
    }

    public function test_ui_routes_404_when_ui_flag_disabled(): void
    {
        // Enable connectors master flag but leave UI disabled
        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
        config(['connectors.enabled' => true, 'connectors.ui_enabled' => false]);

        $uiRoutes = [
            '/tools/connectors',
            '/tools/connectors/library',
            "/tools/connectors/{$this->connector->id}",
        ];

        foreach ($uiRoutes as $url) {
            $response = $this->actingAs($this->user)->get($url);

            $this->assertTrue(
                in_array($response->getStatusCode(), [404, 403, 302]),
                "UI route {$url} should be blocked when UI flag disabled, got {$response->getStatusCode()}"
            );
        }
    }

    public function test_webhook_route_404_when_webhooks_disabled(): void
    {
        config(['connectors.webhooks_enabled' => false]);

        $payload = json_encode(['event' => 'test']);

        $response = $this->call(
            'POST',
            "/agent/api/v1/connectors/{$this->connector->name}/webhooks/test.event",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => 'test',
            ],
            $payload
        );

        $response->assertStatus(404);
    }

    public function test_write_actions_blocked_when_write_flag_disabled(): void
    {
        config()->set([
            'connectors.enabled' => true,
            'connectors.write_actions' => false,
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 1.00,
        ]);

        $encrypter = app(ConnectorVaultEncrypter::class);
        AgentConnectorCredential::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encrypter->encrypt($this->team, json_encode(['api_key' => 'key'])),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.example.com/*' => Http::response(['data' => []], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = new ActionRequest(
            connector: $this->connector,
            action: 'create-item',
            parameters: ['name' => 'Test'],
            connection: $connection,
            team: $this->team,
            trustScore: 1.0,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Write actions are disabled.');
        $pipeline->execute($request);
    }

    public function test_credential_refresh_skipped_when_flag_disabled(): void
    {
        config(['connectors.credential_refresh' => false]);

        // Create a credential with soon-to-expire token
        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
        ]);

        $encrypter = app(ConnectorVaultEncrypter::class);
        $credential = AgentConnectorCredential::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->connector->id,
            'auth_type' => 'oauth2_authorization_code',
            'encrypted_data' => $encrypter->encrypt($this->team, json_encode([
                'access_token' => 'old_token',
                'refresh_token' => 'refresh_token',
            ])),
            'token_expires_at' => now()->addMinutes(3), // Within 5-minute buffer
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        // Run the refresh job — it should check the flag
        $job = new \App\Jobs\Connectors\RefreshConnectorCredentialsJob;

        Http::fake();
        $job->handle(app(ConnectorVaultEncrypter::class));

        // Token should NOT have been refreshed (no HTTP calls made to token endpoint)
        // The credential's last_refreshed_at should still be null
        $credential->refresh();
        $this->assertNull($credential->last_refreshed_at);
    }
}
