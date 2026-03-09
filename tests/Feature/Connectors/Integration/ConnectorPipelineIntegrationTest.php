<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Integration;

use App\Models\AgentConnector;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorInvocation;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\CircuitBreaker;
use App\Support\Connectors\ConnectorVaultEncrypter;
use App\Support\Connectors\Exceptions\ApprovalRequiredException;
use App\Support\Connectors\Exceptions\CircuitOpenException;
use App\Support\Connectors\Exceptions\RateLimitExceededException;
use App\Support\Connectors\Pipeline\ConnectorExecutionPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectorPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $user;

    private AgentConnector $connector;

    private AgentConnectorConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();

        config()->set([
            'connectors.enabled' => true,
            'connectors.write_actions' => true,
            'connectors.pii_redaction.enabled' => true,
            'connectors.pii_redaction.replacement' => '[REDACTED]',
            'connectors.circuit_breaker.failure_threshold' => 5,
            'connectors.circuit_breaker.failure_window_seconds' => 60,
            'connectors.circuit_breaker.recovery_timeout_seconds' => 120,
        ]);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'user_id' => $this->user->id,
            'connector_vault_key' => Str::random(64),
        ]);

        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-pipeline-connector',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'daily_limit' => 1000,
                'concurrent_limit' => 10,
            ],
            'actions' => [
                ['name' => 'list-contacts', 'method' => 'GET', 'path' => '/contacts'],
                [
                    'name' => 'create-contact',
                    'method' => 'POST',
                    'path' => '/contacts',
                    'requires_approval' => true,
                ],
                [
                    'name' => 'list-users',
                    'method' => 'GET',
                    'path' => '/users',
                    'pii_fields' => ['email', 'phone', 'address.postcode'],
                ],
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
            'encrypted_data' => $encrypter->encrypt($this->team, json_encode(['api_key' => 'test_key_123'])),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    private function createRequest(string $action, array $params = [], ?float $trustScore = 1.0): ActionRequest
    {
        return new ActionRequest(
            connector: $this->connector,
            action: $action,
            parameters: $params,
            connection: $this->connection,
            team: $this->team,
            trustScore: $trustScore,
        );
    }

    public function test_pipeline_with_all_stages(): void
    {
        Http::fake([
            'api.example.com/contacts' => Http::response([
                'data' => [['id' => 1, 'name' => 'John']],
            ], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('list-contacts');

        $response = $pipeline->execute($request);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->status);

        // Verify invocation record created with all fields populated
        $invocation = AgentConnectorInvocation::where('connection_id', $this->connection->id)
            ->where('action_name', 'list-contacts')
            ->first();

        $this->assertNotNull($invocation);
        $this->assertEquals(AgentConnectorInvocation::OUTCOME_SUCCESS, $invocation->outcome);
        $this->assertEquals(200, $invocation->http_status);
        $this->assertGreaterThanOrEqual(0, $invocation->duration_ms);
        $this->assertEquals(0, $invocation->retry_count);
    }

    public function test_pipeline_with_retry_on_500(): void
    {
        // Override retry config to eliminate sleep delays in tests
        config()->set('connectors.retry.backoff_seconds', [0, 0, 0]);

        Http::fake([
            'api.example.com/contacts' => Http::sequence()
                ->push(['error' => 'Internal Server Error'], 500)
                ->push(['data' => [['id' => 1]]], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('list-contacts');

        $response = $pipeline->execute($request);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->status);

        // Verify retry_count recorded
        $invocation = AgentConnectorInvocation::where('connection_id', $this->connection->id)
            ->where('action_name', 'list-contacts')
            ->first();

        $this->assertNotNull($invocation);
        $this->assertEquals(AgentConnectorInvocation::OUTCOME_SUCCESS, $invocation->outcome);
        $this->assertEquals(1, $invocation->retry_count);
    }

    public function test_pipeline_with_circuit_breaker_open(): void
    {
        // Open the circuit breaker manually
        $circuitBreaker = app(CircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $circuitBreaker->recordFailure($this->connector->id);
        }
        $this->assertTrue($circuitBreaker->isOpen($this->connector->id));

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('list-contacts');

        $this->expectException(CircuitOpenException::class);
        $pipeline->execute($request);

        // Verify no HTTP call was made
        Http::assertNothingSent();
    }

    public function test_pipeline_with_rate_limit_exceeded(): void
    {
        // Exhaust per-minute rate limit
        $minuteKey = "connector_rate:{$this->connection->id}:minute";
        Redis::set($minuteKey, 60);
        Redis::expire($minuteKey, 60);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('list-contacts');

        $this->expectException(RateLimitExceededException::class);
        $pipeline->execute($request);
    }

    public function test_pipeline_with_approval_required(): void
    {
        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('create-contact', ['name' => 'Test'], trustScore: 1.0);

        try {
            $pipeline->execute($request);
            $this->fail('Expected ApprovalRequiredException to be thrown');
        } catch (ApprovalRequiredException $e) {
            $approval = $e->approval;
            $this->assertInstanceOf(AgentConnectorApproval::class, $approval);
            $this->assertEquals(AgentConnectorApproval::STATUS_PENDING, $approval->status);
            $this->assertEquals('create-contact', $approval->action_name);
            $this->assertEquals(AgentConnectorApproval::TYPE_APPROVAL, $approval->type);

            // Verify approval record persisted
            $this->assertDatabaseHas('agent_connector_approvals', [
                'id' => $approval->id,
                'connection_id' => $this->connection->id,
                'status' => AgentConnectorApproval::STATUS_PENDING,
            ]);
        }
    }

    public function test_pipeline_blocks_write_when_flag_disabled(): void
    {
        config()->set('connectors.write_actions', false);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('create-contact', ['name' => 'Test'], trustScore: 1.0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Write actions are disabled.');
        $pipeline->execute($request);
    }

    public function test_pipeline_with_pii_redaction(): void
    {
        // Response with PII fields at top level (matching redactor's field path resolution)
        Http::fake([
            'api.example.com/users' => Http::response([
                'id' => 1,
                'name' => 'John Smith',
                'email' => 'john@example.com',
                'phone' => '+447123456789',
                'address' => [
                    'line1' => '123 High Street',
                    'postcode' => 'SW1A 1AA',
                ],
            ], 200),
        ]);

        $pipeline = app(ConnectorExecutionPipeline::class);
        $request = $this->createRequest('list-users');

        $response = $pipeline->execute($request);

        $this->assertTrue($response->isSuccess());

        $data = $response->data;

        // PII fields should be redacted at top level
        $this->assertEquals('[REDACTED]', $data['email']);
        $this->assertEquals('[REDACTED]', $data['phone']);
        $this->assertEquals('[REDACTED]', $data['address']['postcode']);
        // Non-PII fields preserved
        $this->assertEquals('John Smith', $data['name']);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('123 High Street', $data['address']['line1']);
    }
}
