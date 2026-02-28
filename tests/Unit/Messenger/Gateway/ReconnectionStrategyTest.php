<?php

namespace Tests\Unit\Messenger\Gateway;

use App\Messenger\Gateway\ReconnectionStrategy;
use Tests\TestCase;

class ReconnectionStrategyTest extends TestCase
{
    private ReconnectionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'messenger.gateway.reconnect.initial_delay' => 1,
            'messenger.gateway.reconnect.max_delay' => 300,
            'messenger.gateway.reconnect.jitter_percent' => 20,
        ]);

        $this->strategy = new ReconnectionStrategy;
    }

    public function test_initial_delay_is_one_second(): void
    {
        // First attempt (attempt 0) should use initial delay
        $delay = $this->strategy->getNextDelay(0);

        // With ±20% jitter, delay should be between 0.8 and 1.2
        $this->assertGreaterThanOrEqual(0.8, $delay);
        $this->assertLessThanOrEqual(1.2, $delay);
    }

    public function test_exponential_increase_doubles_each_attempt(): void
    {
        // Disable jitter for predictable testing
        $strategy = new ReconnectionStrategy(
            initialDelay: 1,
            maxDelay: 300,
            jitterPercent: 0
        );

        $this->assertEquals(1, $strategy->getNextDelay(0));
        $this->assertEquals(2, $strategy->getNextDelay(1));
        $this->assertEquals(4, $strategy->getNextDelay(2));
        $this->assertEquals(8, $strategy->getNextDelay(3));
        $this->assertEquals(16, $strategy->getNextDelay(4));
        $this->assertEquals(32, $strategy->getNextDelay(5));
        $this->assertEquals(64, $strategy->getNextDelay(6));
        $this->assertEquals(128, $strategy->getNextDelay(7));
        $this->assertEquals(256, $strategy->getNextDelay(8));
    }

    public function test_max_delay_caps_at_300_seconds(): void
    {
        // Disable jitter for predictable testing
        $strategy = new ReconnectionStrategy(
            initialDelay: 1,
            maxDelay: 300,
            jitterPercent: 0
        );

        // Attempt 9 would be 512 without cap, should be 300
        $this->assertEquals(300, $strategy->getNextDelay(9));
        $this->assertEquals(300, $strategy->getNextDelay(10));
        $this->assertEquals(300, $strategy->getNextDelay(20));
    }

    public function test_jitter_applies_plus_minus_20_percent(): void
    {
        $strategy = new ReconnectionStrategy(
            initialDelay: 100,
            maxDelay: 300,
            jitterPercent: 20
        );

        // Collect multiple samples to verify jitter range
        $delays = [];
        for ($i = 0; $i < 100; $i++) {
            $delays[] = $strategy->getNextDelay(0);
        }

        // All delays should be within ±20% of 100 (80-120)
        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(80, $delay);
            $this->assertLessThanOrEqual(120, $delay);
        }

        // With 100 samples, we should see some variation (not all same)
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should produce varied delays');
    }

    public function test_reset_clears_attempt_counter(): void
    {
        // This tests the instance tracking of attempts
        $this->strategy->recordAttempt();
        $this->strategy->recordAttempt();
        $this->strategy->recordAttempt();

        $this->assertEquals(3, $this->strategy->getAttemptCount());

        $this->strategy->resetAttempts();

        $this->assertEquals(0, $this->strategy->getAttemptCount());
    }

    public function test_successful_connection_over_60_seconds_triggers_reset(): void
    {
        // Record some attempts
        $this->strategy->recordAttempt();
        $this->strategy->recordAttempt();
        $this->assertEquals(2, $this->strategy->getAttemptCount());

        // Simulate successful connection
        $this->strategy->recordConnectionStart();

        // Simulate 61 seconds passing (use Carbon for time manipulation)
        $this->travel(61)->seconds();

        // Check if connection is stable enough to reset
        $this->assertTrue($this->strategy->shouldResetAttempts());
        $this->strategy->resetIfStable();

        $this->assertEquals(0, $this->strategy->getAttemptCount());
    }

    public function test_successful_connection_under_60_seconds_does_not_reset(): void
    {
        $this->strategy->recordAttempt();
        $this->strategy->recordAttempt();
        $this->assertEquals(2, $this->strategy->getAttemptCount());

        $this->strategy->recordConnectionStart();

        // Only 30 seconds elapsed
        $this->travel(30)->seconds();

        $this->assertFalse($this->strategy->shouldResetAttempts());
        $this->strategy->resetIfStable();

        // Attempts should NOT be reset
        $this->assertEquals(2, $this->strategy->getAttemptCount());
    }

    public function test_max_delay_never_exceeds_configured_value(): void
    {
        $strategy = new ReconnectionStrategy(
            initialDelay: 1,
            maxDelay: 300,
            jitterPercent: 20
        );

        // Even with high attempt counts, should never exceed max
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $delay = $strategy->getNextDelay($attempt);
            $this->assertLessThanOrEqual(360, $delay, "Delay at attempt $attempt exceeded max with jitter");
            // Max with +20% jitter would be 300 * 1.2 = 360
        }
    }

    public function test_constructor_uses_config_defaults(): void
    {
        config([
            'messenger.gateway.reconnect.initial_delay' => 5,
            'messenger.gateway.reconnect.max_delay' => 600,
            'messenger.gateway.reconnect.jitter_percent' => 10,
        ]);

        $strategy = new ReconnectionStrategy;

        // With 0% jitter override, base delay should be 5
        $strategyNoJitter = new ReconnectionStrategy(jitterPercent: 0);
        $this->assertEquals(5, $strategyNoJitter->getNextDelay(0));
    }
}
