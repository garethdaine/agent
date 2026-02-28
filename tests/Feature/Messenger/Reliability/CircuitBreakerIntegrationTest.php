<?php

namespace Tests\Feature\Messenger\Reliability;

use App\Messenger\Exceptions\CircuitOpenException;
use App\Messenger\Reliability\CircuitBreaker;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MessengerHttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CircuitBreakerIntegrationTest extends TestCase
{
    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = ConnectorAccount::factory()->create([
            'provider' => 'slack',
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        // Clear circuit state
        Cache::forget("circuit_breaker:{$this->account->id}");
    }

    protected function tearDown(): void
    {
        Cache::forget("circuit_breaker:{$this->account->id}");
        parent::tearDown();
    }

    public function test_circuit_trips_after_consecutive_send_failures(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $client = new MessengerHttpClient($this->account);

        // Make 5 failing requests (which will retry internally, but that's ok)
        for ($i = 0; $i < 5; $i++) {
            $result = $client->post('https://api.example.com/test', ['data' => 'test']);
            $this->assertFalse($result['success']);
        }

        // Verify circuit is now open
        $circuit = CircuitBreaker::forConnector($this->account->id);
        $this->assertEquals('open', $circuit->getState());
    }

    public function test_circuit_recovers_after_cooldown(): void
    {
        $circuit = CircuitBreaker::forConnector($this->account->id);

        // Trip the circuit manually
        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }

        $this->assertEquals('open', $circuit->getState());

        // Travel past cooldown
        $this->travel(61)->seconds();

        // Circuit should transition to half-open and allow requests
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $client = new MessengerHttpClient($this->account);
        $result = $client->post('https://api.example.com/test', ['data' => 'test']);

        // Request should succeed
        $this->assertTrue($result['success']);

        // After enough successful half-open requests, circuit closes
        for ($i = 0; $i < 2; $i++) {
            $client->post('https://api.example.com/test', ['data' => 'test']);
            $circuit->recordSuccess();
        }

        // Verify circuit is closed
        $this->assertEquals('closed', CircuitBreaker::forConnector($this->account->id)->getState());
    }

    public function test_outbound_messages_rejected_when_circuit_open(): void
    {
        $circuit = CircuitBreaker::forConnector($this->account->id);

        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }

        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $client = new MessengerHttpClient($this->account);
        $result = $client->post('https://api.example.com/test', ['data' => 'test']);

        // Request should be rejected without making HTTP call
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Circuit breaker open', $result['error']);

        // Verify no HTTP requests were made
        Http::assertNothingSent();
    }

    public function test_metrics_emitted_on_state_transitions(): void
    {
        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Circuit breaker transition') &&
                       isset($context['connector_id']) &&
                       isset($context['from']) &&
                       isset($context['to']);
            })
            ->once();

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $circuit = new CircuitBreaker(
            connectorId: $this->account->id,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );

        // Trip the circuit (should log transition)
        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }
    }

    public function test_circuit_breaker_per_connector_isolation(): void
    {
        $account2 = ConnectorAccount::factory()->create([
            'provider' => 'telegram',
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        try {
            // Trip circuit for first account (manually, without HTTP)
            $circuit1 = CircuitBreaker::forConnector($this->account->id);
            for ($i = 0; $i < 5; $i++) {
                $circuit1->recordFailure();
            }

            // Verify first circuit is open
            $this->assertEquals('open', $circuit1->getState());

            // Second account should still work - fake HTTP for success
            Http::fake([
                '*' => Http::response(['ok' => true], 200),
            ]);

            $client2 = new MessengerHttpClient($account2);
            $result = $client2->post('https://api.example.com/test', ['data' => 'test']);

            $this->assertTrue($result['success']);

            // Verify second circuit is still closed
            $circuit2 = CircuitBreaker::forConnector($account2->id);
            $this->assertEquals('closed', $circuit2->getState());
        } finally {
            Cache::forget("circuit_breaker:{$account2->id}");
        }
    }

    public function test_circuit_half_open_failure_reopens_circuit(): void
    {
        $circuit = CircuitBreaker::forConnector($this->account->id);

        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }

        // Wait for cooldown
        $this->travel(61)->seconds();

        // Enter half-open (first request)
        $circuit->canRequest();
        $this->assertEquals('half_open', $circuit->getState());

        // Simulate failure in half-open
        $circuit->recordFailure();

        // Should go back to open
        $this->assertEquals('open', $circuit->getState());
    }

    public function test_circuit_state_survives_process_restart(): void
    {
        $circuit = CircuitBreaker::forConnector($this->account->id);

        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }

        $this->assertEquals('open', $circuit->getState());

        // Simulate "process restart" by creating new instance
        $newCircuit = CircuitBreaker::forConnector($this->account->id);

        // State should be preserved
        $this->assertEquals('open', $newCircuit->getState());
    }

    public function test_rate_limits_read_from_config(): void
    {
        // Verify rate limits are present in config
        $slackRateLimit = config('messenger.providers.slack.rate_limit');

        $this->assertNotNull($slackRateLimit);
        $this->assertArrayHasKey('requests_per_second', $slackRateLimit);
        $this->assertArrayHasKey('burst_limit', $slackRateLimit);
        $this->assertEquals(1, $slackRateLimit['requests_per_second']);
        $this->assertEquals(5, $slackRateLimit['burst_limit']);
    }

    public function test_circuit_breaker_config_has_required_settings(): void
    {
        $config = config('messenger.circuit_breaker');

        $this->assertNotNull($config);
        $this->assertArrayHasKey('failure_threshold', $config);
        $this->assertArrayHasKey('cooldown_seconds', $config);
        $this->assertArrayHasKey('half_open_requests', $config);

        $this->assertEquals(5, $config['failure_threshold']);
        $this->assertEquals(60, $config['cooldown_seconds']);
        $this->assertEquals(3, $config['half_open_requests']);
    }
}
