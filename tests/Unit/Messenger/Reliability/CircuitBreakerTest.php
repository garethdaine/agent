<?php

namespace Tests\Unit\Messenger\Reliability;

use App\Messenger\Exceptions\CircuitOpenException;
use App\Messenger\Reliability\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    private int $connectorId = 123;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached circuit state
        Cache::forget("circuit_breaker:{$this->connectorId}");

        $this->circuitBreaker = new CircuitBreaker(
            connectorId: $this->connectorId,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );
    }

    protected function tearDown(): void
    {
        Cache::forget("circuit_breaker:{$this->connectorId}");
        parent::tearDown();
    }

    public function test_initial_state_is_closed(): void
    {
        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_can_request_returns_true_when_closed(): void
    {
        $this->assertTrue($this->circuitBreaker->canRequest());
    }

    public function test_transitions_to_open_after_5_consecutive_failures(): void
    {
        // Record 4 failures - should still be closed
        for ($i = 0; $i < 4; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertEquals('closed', $this->circuitBreaker->getState());
        $this->assertTrue($this->circuitBreaker->canRequest());

        // 5th failure should trip the circuit
        $this->circuitBreaker->recordFailure();

        $this->assertEquals('open', $this->circuitBreaker->getState());
    }

    public function test_open_state_rejects_requests_with_circuit_open_exception(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->expectException(CircuitOpenException::class);
        $this->expectExceptionMessage("Circuit breaker open for connector {$this->connectorId}");

        $this->circuitBreaker->canRequest();
    }

    public function test_transitions_to_half_open_after_60s_cooldown(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertEquals('open', $this->circuitBreaker->getState());

        // Travel forward 59 seconds - still open
        $this->travel(59)->seconds();

        $this->expectException(CircuitOpenException::class);
        $this->circuitBreaker->canRequest();
    }

    public function test_half_open_state_after_cooldown(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertEquals('open', $this->circuitBreaker->getState());

        // Travel forward 61 seconds - should transition to half-open
        $this->travel(61)->seconds();

        // Should be allowed now (transitions to half-open)
        $this->assertTrue($this->circuitBreaker->canRequest());
        $this->assertEquals('half_open', $this->circuitBreaker->getState());
    }

    public function test_half_open_allows_3_test_requests(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Wait for cooldown
        $this->travel(61)->seconds();

        // First 3 requests should be allowed
        $this->assertTrue($this->circuitBreaker->canRequest());
        $this->assertTrue($this->circuitBreaker->canRequest());
        $this->assertTrue($this->circuitBreaker->canRequest());

        // 4th request in half-open should be rejected (until we record successes)
        $this->assertFalse($this->circuitBreaker->canRequest());
    }

    public function test_successful_half_open_requests_transition_to_closed(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Wait for cooldown
        $this->travel(61)->seconds();

        // Make 3 test requests, all successful
        $this->circuitBreaker->canRequest();
        $this->circuitBreaker->recordSuccess();

        $this->circuitBreaker->canRequest();
        $this->circuitBreaker->recordSuccess();

        $this->circuitBreaker->canRequest();
        $this->circuitBreaker->recordSuccess();

        // Should now be closed
        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_any_half_open_failure_transitions_back_to_open(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Wait for cooldown
        $this->travel(61)->seconds();

        // Enter half-open state
        $this->circuitBreaker->canRequest();
        $this->assertEquals('half_open', $this->circuitBreaker->getState());

        // Record success for first request
        $this->circuitBreaker->recordSuccess();

        // Second request
        $this->circuitBreaker->canRequest();

        // But it fails!
        $this->circuitBreaker->recordFailure();

        // Should immediately go back to open
        $this->assertEquals('open', $this->circuitBreaker->getState());
    }

    public function test_state_persisted_in_cache_with_ttl(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Create a new CircuitBreaker instance for same connector
        $newInstance = new CircuitBreaker(
            connectorId: $this->connectorId,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );

        // Should read persisted state
        $this->assertEquals('open', $newInstance->getState());
    }

    public function test_per_connector_isolation_separate_circuits(): void
    {
        $connectorId1 = 100;
        $connectorId2 = 200;

        $circuit1 = new CircuitBreaker(
            connectorId: $connectorId1,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );

        $circuit2 = new CircuitBreaker(
            connectorId: $connectorId2,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );

        // Trip circuit 1
        for ($i = 0; $i < 5; $i++) {
            $circuit1->recordFailure();
        }

        // Circuit 1 should be open
        $this->assertEquals('open', $circuit1->getState());

        // Circuit 2 should still be closed
        $this->assertEquals('closed', $circuit2->getState());
        $this->assertTrue($circuit2->canRequest());

        // Cleanup
        Cache::forget("circuit_breaker:$connectorId1");
        Cache::forget("circuit_breaker:$connectorId2");
    }

    public function test_success_in_closed_state_resets_failure_count(): void
    {
        // Record 3 failures
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Record a success
        $this->circuitBreaker->recordSuccess();

        // Record 4 more failures - should still be closed (counter was reset)
        for ($i = 0; $i < 4; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_circuit_breaker_uses_config_defaults(): void
    {
        config([
            'messenger.circuit_breaker.failure_threshold' => 10,
            'messenger.circuit_breaker.cooldown_seconds' => 120,
            'messenger.circuit_breaker.half_open_requests' => 5,
        ]);

        $circuit = CircuitBreaker::forConnector($this->connectorId);

        // Record 9 failures - should still be closed with threshold of 10
        for ($i = 0; $i < 9; $i++) {
            $circuit->recordFailure();
        }

        $this->assertEquals('closed', $circuit->getState());

        // 10th failure trips it
        $circuit->recordFailure();
        $this->assertEquals('open', $circuit->getState());
    }

    public function test_get_failure_count_returns_current_count(): void
    {
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());

        $this->circuitBreaker->recordFailure();
        $this->assertEquals(1, $this->circuitBreaker->getFailureCount());

        $this->circuitBreaker->recordFailure();
        $this->assertEquals(2, $this->circuitBreaker->getFailureCount());
    }

    public function test_get_half_open_success_count(): void
    {
        // Trip the circuit and enter half-open
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }
        $this->travel(61)->seconds();
        $this->circuitBreaker->canRequest();

        $this->assertEquals(0, $this->circuitBreaker->getHalfOpenSuccessCount());

        $this->circuitBreaker->recordSuccess();
        $this->assertEquals(1, $this->circuitBreaker->getHalfOpenSuccessCount());

        $this->circuitBreaker->canRequest();
        $this->circuitBreaker->recordSuccess();
        $this->assertEquals(2, $this->circuitBreaker->getHalfOpenSuccessCount());
    }

    public function test_cache_ttl_is_ten_minutes(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        // Verify cache key exists
        $this->assertTrue(Cache::has("circuit_breaker:{$this->connectorId}"));

        // Travel 11 minutes - cache should expire
        $this->travel(11)->minutes();

        // Circuit should be effectively reset due to cache expiry
        $newInstance = new CircuitBreaker(
            connectorId: $this->connectorId,
            failureThreshold: 5,
            cooldownSeconds: 60,
            halfOpenRequests: 3
        );

        $this->assertEquals('closed', $newInstance->getState());
    }

    public function test_cooldown_expires_at_returns_correct_timestamp(): void
    {
        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $cooldownExpiresAt = $this->circuitBreaker->getCooldownExpiresAt();

        $this->assertNotNull($cooldownExpiresAt);
        $this->assertEqualsWithDelta(now()->addSeconds(60)->timestamp, $cooldownExpiresAt, 2);
    }

    public function test_cooldown_expires_at_returns_null_when_not_open(): void
    {
        $this->assertNull($this->circuitBreaker->getCooldownExpiresAt());
    }
}
