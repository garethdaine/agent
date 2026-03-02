<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Support\Memory\RateLimiter\ProviderRateLimiter;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ProviderRateLimiter token-bucket rate limiting.
 *
 * Verifies:
 * - Allows requests within rate limit
 * - Blocks requests exceeding rate limit
 * - Handles 429 responses with jitter backoff
 * - Token bucket refills over time
 */
class ProviderRateLimiterTest extends TestCase
{
    private ProviderRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        // Use memory redis connection
        config(['memory.enabled' => true]);

        $this->rateLimiter = new ProviderRateLimiter;

        // Clean up any existing test keys before each test
        $this->cleanupRedisKeys();
    }

    protected function tearDown(): void
    {
        // Clean up after test
        $this->cleanupRedisKeys();

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Clean up all rate limiter Redis keys.
     */
    private function cleanupRedisKeys(): void
    {
        $redis = Redis::connection('memory');
        // Search for keys with any prefix pattern
        $keys = $redis->keys('*ratelimit*');
        foreach ($keys as $key) {
            // Remove the database prefix if present (e.g., "agent-database-")
            $cleanKey = preg_replace('/^[^:]+:/', '', $key);
            $redis->del($cleanKey);
        }
    }

    /**
     * Test that requests within rate limit are allowed.
     */
    public function test_allows_requests_within_rate_limit(): void
    {
        $provider = 'openai-test-1';
        $model = 'gpt-4o-mini';

        config(['memory.rate_limits.openai-test-1' => [
            'requests_per_minute' => 500,
            'tokens_per_minute' => 200000,
            'requests_per_day' => 10000,
        ]]);

        $this->rateLimiter->resetBucket($provider, $model);

        // First request should always be allowed
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertTrue($result->allowed);
        $this->assertGreaterThan(0, $result->remaining);
        $this->assertNull($result->retryAfter);
    }

    /**
     * Test that multiple requests within limit are allowed.
     */
    public function test_allows_multiple_requests_within_limit(): void
    {
        $provider = 'openai-test-2';
        $model = 'gpt-4o-mini';

        config(['memory.rate_limits.openai-test-2' => [
            'requests_per_minute' => 500,
            'tokens_per_minute' => 200000,
            'requests_per_day' => 10000,
        ]]);

        $this->rateLimiter->resetBucket($provider, $model);

        // Config allows 500 RPM
        for ($i = 0; $i < 5; $i++) {
            $result = $this->rateLimiter->attempt($provider, $model);
            $this->assertTrue($result->allowed, "Request $i should be allowed");
        }
    }

    /**
     * Test that requests exceeding rate limit are blocked.
     */
    public function test_blocks_requests_exceeding_rate_limit(): void
    {
        $provider = 'test-blocking-provider';
        $model = 'test-model';

        // Override config for test with low limit
        config(['memory.rate_limits.test-blocking-provider' => [
            'requests_per_minute' => 3,
            'tokens_per_minute' => 100000,
            'requests_per_day' => 100,
        ]]);

        $this->rateLimiter->resetBucket($provider, $model);

        // Exhaust the bucket
        for ($i = 0; $i < 3; $i++) {
            $result = $this->rateLimiter->attempt($provider, $model);
            $this->assertTrue($result->allowed, "Request $i should be allowed");
        }

        // Next request should be blocked
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertFalse($result->allowed);
        $this->assertEquals(0, $result->remaining);
        $this->assertNotNull($result->retryAfter);
        $this->assertGreaterThan(0, $result->retryAfter);
    }

    /**
     * Test that 429 response triggers backoff with jitter.
     */
    public function test_handles_429_response_with_jitter_backoff(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        // Record a 429 response
        $this->rateLimiter->recordRateLimitHit($provider, $model, 60);

        // Next attempt should respect backoff
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertFalse($result->allowed);
        $this->assertNotNull($result->retryAfter);
        // Retry-After should include jitter (base + 0-10% jitter)
        $this->assertGreaterThanOrEqual(60, $result->retryAfter);
        $this->assertLessThanOrEqual(66, $result->retryAfter); // 60 + 10% max jitter
    }

    /**
     * Test that 429 response without Retry-After uses default backoff.
     */
    public function test_handles_429_without_retry_after_uses_default(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        // Record a 429 without specific retry-after
        $this->rateLimiter->recordRateLimitHit($provider, $model);

        // Should use default backoff
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertFalse($result->allowed);
        $this->assertNotNull($result->retryAfter);
        // Default backoff is 60 seconds
        $this->assertGreaterThanOrEqual(60, $result->retryAfter);
    }

    /**
     * Test that token bucket refills over time.
     */
    public function test_token_bucket_refills_over_time(): void
    {
        $provider = 'test-provider';
        $model = 'test-model';

        // Override config for test
        config(['memory.rate_limits.test-provider' => [
            'requests_per_minute' => 2,
            'tokens_per_minute' => 1000,
            'requests_per_day' => 100,
        ]]);

        // Exhaust the bucket
        $this->rateLimiter->attempt($provider, $model);
        $this->rateLimiter->attempt($provider, $model);

        // Should be blocked now
        $result = $this->rateLimiter->attempt($provider, $model);
        $this->assertFalse($result->allowed);

        // Simulate time passing (refill the bucket)
        $this->rateLimiter->resetBucket($provider, $model);

        // Should be allowed again
        $result = $this->rateLimiter->attempt($provider, $model);
        $this->assertTrue($result->allowed);
    }

    /**
     * Test that getRateLimitState returns current state.
     */
    public function test_get_rate_limit_state_returns_current_state(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        // Make some requests
        $this->rateLimiter->attempt($provider, $model);
        $this->rateLimiter->attempt($provider, $model);

        $state = $this->rateLimiter->getRateLimitState($provider, $model);

        $this->assertArrayHasKey('remaining', $state);
        $this->assertArrayHasKey('limit', $state);
        $this->assertArrayHasKey('reset_at', $state);
        $this->assertArrayHasKey('blocked_until', $state);
    }

    /**
     * Test that different provider+model combinations have separate buckets.
     */
    public function test_separate_buckets_per_provider_model(): void
    {
        config(['memory.rate_limits.test-provider' => [
            'requests_per_minute' => 2,
            'tokens_per_minute' => 1000,
            'requests_per_day' => 100,
        ]]);

        // Exhaust bucket for model A
        $this->rateLimiter->attempt('test-provider', 'model-a');
        $this->rateLimiter->attempt('test-provider', 'model-a');

        // Model A should be blocked
        $result = $this->rateLimiter->attempt('test-provider', 'model-a');
        $this->assertFalse($result->allowed);

        // Model B should still be allowed (separate bucket)
        $result = $this->rateLimiter->attempt('test-provider', 'model-b');
        $this->assertTrue($result->allowed);
    }

    /**
     * Test that missing provider config uses defaults.
     */
    public function test_missing_provider_config_uses_defaults(): void
    {
        $provider = 'unknown-provider';
        $model = 'some-model';

        // Should not throw, uses default limits
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertTrue($result->allowed);
        $this->assertGreaterThan(0, $result->remaining);
    }

    /**
     * Test that recordTokenUsage tracks token consumption.
     */
    public function test_record_token_usage_tracks_consumption(): void
    {
        $provider = 'openai-token-test';
        $model = 'gpt-4o-mini';

        config(['memory.rate_limits.openai-token-test' => [
            'requests_per_minute' => 500,
            'tokens_per_minute' => 200000,
            'requests_per_day' => 10000,
        ]]);

        $this->rateLimiter->resetBucket($provider, $model);

        // Record token usage
        $this->rateLimiter->recordTokenUsage($provider, $model, 1000);

        $state = $this->rateLimiter->getRateLimitState($provider, $model);

        $this->assertArrayHasKey('tokens_used', $state);
        $this->assertEquals(1000, $state['tokens_used']);
    }

    /**
     * Test that token limit blocks when exceeded.
     */
    public function test_token_limit_blocks_when_exceeded(): void
    {
        $provider = 'test-provider';
        $model = 'test-model';

        config(['memory.rate_limits.test-provider' => [
            'requests_per_minute' => 100,
            'tokens_per_minute' => 5000,
            'requests_per_day' => 1000,
        ]]);

        // Record token usage that exceeds limit
        $this->rateLimiter->recordTokenUsage($provider, $model, 5001);

        // Should be blocked due to token limit
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertFalse($result->allowed);
        $this->assertNotNull($result->retryAfter);
    }

    /**
     * Test that daily limit is tracked separately.
     */
    public function test_daily_limit_tracked_separately(): void
    {
        $provider = 'test-daily-provider';
        $model = 'test-daily-model';

        config(['memory.rate_limits.test-daily-provider' => [
            'requests_per_minute' => 100,
            'tokens_per_minute' => 100000,
            'requests_per_day' => 5,
        ]]);

        // Reset the bucket to ensure clean state
        $this->rateLimiter->resetBucket($provider, $model);

        // Make requests up to daily limit
        for ($i = 0; $i < 5; $i++) {
            $result = $this->rateLimiter->attempt($provider, $model);
            $this->assertTrue($result->allowed, "Request $i should be allowed");
        }

        // Next request should be blocked by daily limit
        $result = $this->rateLimiter->attempt($provider, $model);

        $this->assertFalse($result->allowed);
    }
}
