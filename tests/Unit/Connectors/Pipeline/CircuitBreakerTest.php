<?php

namespace Tests\Unit\Connectors\Pipeline;

use App\Support\Connectors\CircuitBreaker;
use App\Support\Connectors\Exceptions\CircuitOpenException;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = new CircuitBreaker;

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

    public function test_closed_circuit_allows_requests(): void
    {
        $this->breaker->check('test-connector-1');

        // No exception means it passed
        $this->assertFalse($this->breaker->isOpen('test-connector-1'));
    }

    public function test_opens_after_failure_threshold(): void
    {
        $connectorId = 'test-connector-2';

        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($connectorId);
        }

        $this->assertTrue($this->breaker->isOpen($connectorId));
    }

    public function test_open_circuit_rejects_with_structured_error(): void
    {
        $connectorId = 'test-connector-3';

        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($connectorId);
        }

        $this->expectException(CircuitOpenException::class);

        $this->breaker->check($connectorId);
    }

    public function test_circuit_recovers_after_timeout(): void
    {
        $connectorId = 'test-connector-4';

        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($connectorId);
        }

        $this->assertTrue($this->breaker->isOpen($connectorId));

        // Manually manipulate Redis to simulate time passing
        $key = "connector_circuit:{$connectorId}";
        $state = json_decode(Redis::get($key), true);
        $state['open_until'] = time() - 1;
        Redis::setex($key, 300, json_encode($state));

        // Circuit should now be half-open (not open)
        $this->assertFalse($this->breaker->isOpen($connectorId));
        $this->assertTrue($this->breaker->isHalfOpen($connectorId));

        // Should allow a request through
        $this->breaker->check($connectorId);
    }

    public function test_successful_request_resets_failure_count(): void
    {
        $connectorId = 'test-connector-5';

        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure($connectorId);
        }

        $this->assertFalse($this->breaker->isOpen($connectorId));

        $this->breaker->recordSuccess($connectorId);

        // Even after adding another failure, circuit should stay closed
        $this->breaker->recordFailure($connectorId);
        $this->assertFalse($this->breaker->isOpen($connectorId));
    }
}
