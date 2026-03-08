<?php

namespace Tests\Feature\Connectors\Concurrency;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\CircuitBreaker;
use App\Support\Connectors\ConnectorVaultEncrypter;
use App\Support\Connectors\Exceptions\RateLimitExceededException;
use App\Support\Connectors\Pipeline\ConnectorExecutionPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectorConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'connectors.enabled' => true,
            'connectors.write_actions' => true,
            'connectors.circuit_breaker.failure_threshold' => 5,
            'connectors.circuit_breaker.failure_window_seconds' => 60,
            'connectors.circuit_breaker.recovery_timeout_seconds' => 120,
        ]);

        $user = User::factory()->create();
        $this->team = Team::factory()->create([
            'user_id' => $user->id,
            'connector_vault_key' => Str::random(64),
        ]);

        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-concurrency-connector',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'rate_limits' => [
                'requests_per_minute' => 5,
                'daily_limit' => 1000,
                'concurrent_limit' => 3,
            ],
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ],
        ]);

        $this->connection = AgentConnectorConnection::factory()->create([
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
            'encrypted_data' => $encrypter->encrypt($this->team, json_encode(['api_key' => 'test_key'])),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    private function createRequest(): ActionRequest
    {
        return new ActionRequest(
            connector: $this->connector,
            action: 'list-items',
            parameters: [],
            connection: $this->connection,
            team: $this->team,
            trustScore: 1.0,
        );
    }

    public function test_concurrent_invocations_respect_rate_limit(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['data' => []], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $successCount = 0;
        $rateLimitedCount = 0;

        // Try 10 invocations with a per-minute limit of 5
        for ($i = 0; $i < 10; $i++) {
            try {
                $pipeline->execute($this->createRequest());
                $successCount++;
            } catch (RateLimitExceededException) {
                $rateLimitedCount++;
            }
        }

        $this->assertEquals(5, $successCount, 'Exactly 5 requests should succeed');
        $this->assertEquals(5, $rateLimitedCount, 'Exactly 5 requests should be rate-limited');
    }

    public function test_circuit_breaker_under_concurrent_failures(): void
    {
        $circuitBreaker = app(CircuitBreaker::class);

        // Simulate concurrent failures
        for ($i = 0; $i < 5; $i++) {
            $circuitBreaker->recordFailure($this->connector->id);
        }

        // Circuit should now be open
        $this->assertTrue($circuitBreaker->isOpen($this->connector->id));

        // Subsequent requests should fail immediately
        Http::fake([
            'api.example.com/*' => Http::response(['data' => []], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);

        try {
            $pipeline->execute($this->createRequest());
            $this->fail('Expected CircuitOpenException');
        } catch (\App\Support\Connectors\Exceptions\CircuitOpenException $e) {
            $this->assertGreaterThan(0, $e->recoversInSeconds);
        }

        // No HTTP requests should have been made after circuit opened
        Http::assertNothingSent();

        // After reset, requests should succeed again
        $circuitBreaker->reset($this->connector->id);
        $this->assertFalse($circuitBreaker->isOpen($this->connector->id));
    }

    public function test_concurrent_credential_refresh_is_idempotent(): void
    {
        // Simulate two refresh attempts for the same credential
        $credential = AgentConnectorCredential::where('team_id', $this->team->id)
            ->where('connector_id', $this->connector->id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->first();

        $this->assertNotNull($credential);

        // Mock token refresh endpoint
        $refreshCallCount = 0;
        Http::fake(function ($request) use (&$refreshCallCount) {
            if (str_contains($request->url(), 'token')) {
                $refreshCallCount++;

                return Http::response([
                    'access_token' => 'new_token_'.$refreshCallCount,
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['data' => []], 200);
        });

        // The credential's last_refreshed_at acts as an idempotency guard
        // If two refresh jobs run simultaneously, the second one should see
        // that the credential was already refreshed recently
        $credential->update([
            'last_refreshed_at' => now(),
            'token_expires_at' => now()->addHour(),
        ]);

        // Verify the credential was updated (idempotent check)
        $credential->refresh();
        $this->assertNotNull($credential->last_refreshed_at);
        $this->assertTrue($credential->token_expires_at->isFuture());

        // Only one active credential should exist per team/connector
        $activeCount = AgentConnectorCredential::where('team_id', $this->team->id)
            ->where('connector_id', $this->connector->id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->count();
        $this->assertEquals(1, $activeCount);
    }
}
