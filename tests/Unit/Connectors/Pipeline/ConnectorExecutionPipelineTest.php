<?php

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorInvocation;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use App\Support\Connectors\ConnectorVaultEncrypter;
use App\Support\Connectors\Pipeline\ConnectorExecutionPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ConnectorExecutionPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('connectors.enabled', true);
        config()->set('connectors.write_actions', true);
        config()->set('connectors.retry', [
            'max_attempts' => 1,
            'backoff_seconds' => [0],
        ]);
        config()->set('connectors.pii_redaction', ['enabled' => true, 'replacement' => '[REDACTED]']);
        config()->set('connectors.health.weights', [
            'credential_health' => 0.40,
            'error_rate' => 0.30,
            'latency' => 0.15,
            'rate_limit_headroom' => 0.15,
        ]);
        config()->set('connectors.health.thresholds', ['healthy' => 0.8, 'degraded' => 0.5]);
        config()->set('connectors.circuit_breaker', [
            'failure_threshold' => 5,
            'failure_window_seconds' => 60,
            'recovery_timeout_seconds' => 120,
        ]);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_full_pipeline_success(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => 'ok'], 200),
        ]);

        [$request, $team] = $this->createPipelineRequest();

        $pipeline = app(ConnectorExecutionPipeline::class);
        $response = $pipeline->execute($request);

        $this->assertInstanceOf(ActionResponse::class, $response);
        $this->assertEquals(200, $response->status);
    }

    public function test_pipeline_emits_telemetry_on_failure(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        [$request, $team] = $this->createPipelineRequest();

        $pipeline = app(ConnectorExecutionPipeline::class);
        $response = $pipeline->execute($request);

        $invocation = AgentConnectorInvocation::where('connector_id', $request->connector->id)->first();
        $this->assertNotNull($invocation);
        $this->assertEquals(AgentConnectorInvocation::OUTCOME_FAILED, $invocation->outcome);
    }

    public function test_pipeline_records_invocation(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['contacts' => []], 200),
        ]);

        [$request, $team] = $this->createPipelineRequest();

        $pipeline = app(ConnectorExecutionPipeline::class);
        $pipeline->execute($request);

        $this->assertDatabaseHas('agent_connector_invocations', [
            'connector_id' => $request->connector->id,
            'action_name' => 'list_contacts',
        ]);
    }

    public function test_pipeline_updates_health_score_after_invocation(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['contacts' => []], 200),
        ]);

        [$request, $team] = $this->createPipelineRequest();

        $pipeline = app(ConnectorExecutionPipeline::class);
        $pipeline->execute($request);

        $request->connection->refresh();
        $this->assertNotNull($request->connection->health_score);
    }

    private function createPipelineRequest(): array
    {
        $team = Team::factory()->create([
            'connector_vault_key' => str_repeat('a', 64),
        ]);

        $connector = AgentConnector::factory()->create([
            'base_url' => 'https://api.example.com',
            'auth_type' => 'api_key',
            'actions' => [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']],
            'rate_limits' => ['requests_per_minute' => 1000],
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 1.0,
        ]);

        $encrypter = new ConnectorVaultEncrypter;
        $encryptedData = $encrypter->encrypt($team, json_encode([
            'api_key' => 'test-key-123',
            'header_name' => 'X-API-Key',
        ]));

        AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'encrypted_data' => $encryptedData,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'token_expires_at' => now()->addYear(),
        ]);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: $team,
            trustScore: 1.0,
        );

        return [$request, $team];
    }
}
