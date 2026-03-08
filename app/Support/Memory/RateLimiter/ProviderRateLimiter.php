<?php

declare(strict_types=1);

namespace App\Support\Memory\RateLimiter;

use Illuminate\Support\Facades\Redis;

/**
 * Token-bucket rate limiter for memory provider API calls.
 *
 * Implements proactive rate limiting per provider+model combination using Redis.
 * Supports:
 * - Requests per minute limits
 * - Tokens per minute limits
 * - Requests per day limits
 * - Reactive 429 handling with jitter backoff
 *
 * Redis keys:
 * - memory:ratelimit:{provider}:{model}:requests - Request bucket
 * - memory:ratelimit:{provider}:{model}:tokens - Token bucket
 * - memory:ratelimit:{provider}:{model}:daily - Daily request counter
 * - memory:ratelimit:{provider}:{model}:blocked - Blocked until timestamp
 */
class ProviderRateLimiter
{
    /**
     * Default rate limits when provider not configured.
     */
    private const DEFAULT_LIMITS = [
        'requests_per_minute' => 60,
        'tokens_per_minute' => 60000,
        'requests_per_day' => 5000,
    ];

    /**
     * Default backoff seconds for 429 responses.
     */
    private const DEFAULT_BACKOFF_SECONDS = 60;

    /**
     * Maximum jitter percentage for backoff.
     */
    private const JITTER_PERCENT = 0.10;

    /**
     * Redis connection name.
     */
    private string $connection;

    /**
     * Create a new rate limiter instance.
     */
    public function __construct(string $connection = 'memory')
    {
        $this->connection = $connection;
    }

    /**
     * Attempt to make a request to the provider.
     *
     * @param  string  $provider  Provider name (e.g., 'openai')
     * @param  string  $model  Model name (e.g., 'gpt-4o-mini')
     * @return RateLimitResult Result indicating if request is allowed
     */
    public function attempt(string $provider, string $model): RateLimitResult
    {
        $limits = $this->getLimits($provider);
        $prefix = $this->getKeyPrefix($provider, $model);

        // Check if we're in a backoff period from a 429
        $blockedUntil = $this->getBlockedUntil($prefix);
        if ($blockedUntil !== null && $blockedUntil > time()) {
            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $blockedUntil - time()
            );
        }

        // Check token limit
        $tokensUsed = $this->getTokensUsed($prefix);
        if ($tokensUsed >= $limits['tokens_per_minute']) {
            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $this->getSecondsUntilMinuteReset()
            );
        }

        // Check daily limit
        $dailyRequests = $this->getDailyRequests($prefix);
        if ($dailyRequests >= $limits['requests_per_day']) {
            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $this->getSecondsUntilDayReset()
            );
        }

        // Check minute request limit using token bucket
        $remaining = $this->consumeFromBucket($prefix, $limits['requests_per_minute']);

        if ($remaining < 0) {
            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $this->getSecondsUntilMinuteReset()
            );
        }

        // Increment daily counter
        $this->incrementDailyRequests($prefix);

        return new RateLimitResult(
            allowed: true,
            remaining: $remaining,
            retryAfter: null
        );
    }

    /**
     * Record a 429 rate limit hit from the provider.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     * @param  int|null  $retryAfterSeconds  Retry-After header value (or null for default)
     */
    public function recordRateLimitHit(string $provider, string $model, ?int $retryAfterSeconds = null): void
    {
        $prefix = $this->getKeyPrefix($provider, $model);
        $backoff = $retryAfterSeconds ?? self::DEFAULT_BACKOFF_SECONDS;

        // Add jitter to prevent thundering herd
        $jitter = (int) ceil($backoff * self::JITTER_PERCENT * (mt_rand() / mt_getrandmax()));
        $totalBackoff = $backoff + $jitter;

        $blockedUntil = time() + $totalBackoff;

        $redis = Redis::connection($this->connection);
        $redis->set("{$prefix}:blocked", $blockedUntil, 'EX', $totalBackoff); // @phpstan-ignore argument.type, arguments.count
    }

    /**
     * Record token usage for the rate limiter.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     * @param  int  $tokens  Number of tokens consumed
     */
    public function recordTokenUsage(string $provider, string $model, int $tokens): void
    {
        $prefix = $this->getKeyPrefix($provider, $model);
        $key = "{$prefix}:tokens";

        $redis = Redis::connection($this->connection);
        $ttl = $this->getSecondsUntilMinuteReset();

        // Increment token counter with TTL
        if ($redis->exists($key)) {
            $redis->incrby($key, $tokens);
        } else {
            $redis->setex($key, max(1, $ttl), $tokens);
        }
    }

    /**
     * Get current rate limit state for a provider+model.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     * @return array{remaining: int, limit: int, reset_at: int, blocked_until: int|null, tokens_used: int}
     */
    public function getRateLimitState(string $provider, string $model): array
    {
        $limits = $this->getLimits($provider);
        $prefix = $this->getKeyPrefix($provider, $model);

        $redis = Redis::connection($this->connection);
        $requestsUsed = (int) ($redis->get("{$prefix}:requests") ?? 0);
        $tokensUsed = (int) ($redis->get("{$prefix}:tokens") ?? 0);
        $blockedUntil = $this->getBlockedUntil($prefix);

        return [
            'remaining' => max(0, $limits['requests_per_minute'] - $requestsUsed),
            'limit' => $limits['requests_per_minute'],
            'reset_at' => time() + $this->getSecondsUntilMinuteReset(),
            'blocked_until' => $blockedUntil,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Reset the bucket for a provider+model (for testing).
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     */
    public function resetBucket(string $provider, string $model): void
    {
        $prefix = $this->getKeyPrefix($provider, $model);

        $redis = Redis::connection($this->connection);
        $redis->del("{$prefix}:requests");
        $redis->del("{$prefix}:tokens");
        $redis->del("{$prefix}:blocked");
        $redis->del("{$prefix}:daily");
    }

    /**
     * Get rate limits for a provider.
     *
     * @return array{requests_per_minute: int, tokens_per_minute: int, requests_per_day: int}
     */
    private function getLimits(string $provider): array
    {
        $config = config("memory.rate_limits.{$provider}");

        if ($config === null) {
            return self::DEFAULT_LIMITS;
        }

        return [
            'requests_per_minute' => $config['requests_per_minute'] ?? self::DEFAULT_LIMITS['requests_per_minute'],
            'tokens_per_minute' => $config['tokens_per_minute'] ?? self::DEFAULT_LIMITS['tokens_per_minute'],
            'requests_per_day' => $config['requests_per_day'] ?? self::DEFAULT_LIMITS['requests_per_day'],
        ];
    }

    /**
     * Get Redis key prefix for a provider+model.
     */
    private function getKeyPrefix(string $provider, string $model): string
    {
        // Sanitize inputs to prevent key injection
        $provider = preg_replace('/[^a-zA-Z0-9_-]/', '', $provider);
        $model = preg_replace('/[^a-zA-Z0-9_.-]/', '', $model);

        return "memory:ratelimit:{$provider}:{$model}";
    }

    /**
     * Consume one request from the bucket.
     *
     * @return int Remaining requests (-1 if limit exceeded)
     */
    private function consumeFromBucket(string $prefix, int $limit): int
    {
        $key = "{$prefix}:requests";
        $redis = Redis::connection($this->connection);
        $ttl = $this->getSecondsUntilMinuteReset();

        // Use Lua script for atomic increment with limit check
        $script = <<<'LUA'
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])

local current = redis.call('GET', key)
if current == false then
    redis.call('SETEX', key, ttl, 1)
    return limit - 1
end

current = tonumber(current)
if current >= limit then
    return -1
end

redis.call('INCR', key)
return limit - current - 1
LUA;

        // @phpstan-ignore-next-line
        return (int) $redis->eval($script, 1, $key, $limit, max(1, $ttl));
    }

    /**
     * Get blocked until timestamp.
     */
    private function getBlockedUntil(string $prefix): ?int
    {
        $redis = Redis::connection($this->connection);
        $blocked = $redis->get("{$prefix}:blocked");

        return $blocked ? (int) $blocked : null;
    }

    /**
     * Get tokens used in current minute window.
     */
    private function getTokensUsed(string $prefix): int
    {
        $redis = Redis::connection($this->connection);

        return (int) ($redis->get("{$prefix}:tokens") ?? 0);
    }

    /**
     * Get daily requests count.
     */
    private function getDailyRequests(string $prefix): int
    {
        $redis = Redis::connection($this->connection);

        return (int) ($redis->get("{$prefix}:daily") ?? 0);
    }

    /**
     * Increment daily request counter.
     */
    private function incrementDailyRequests(string $prefix): void
    {
        $key = "{$prefix}:daily";
        $redis = Redis::connection($this->connection);
        $ttl = $this->getSecondsUntilDayReset();

        if ($redis->exists($key)) {
            $redis->incr($key);
        } else {
            $redis->setex($key, max(1, $ttl), 1);
        }
    }

    /**
     * Get seconds until the next minute boundary.
     */
    private function getSecondsUntilMinuteReset(): int
    {
        return 60 - (time() % 60);
    }

    /**
     * Get seconds until midnight (next day reset).
     */
    private function getSecondsUntilDayReset(): int
    {
        $now = now();
        $midnight = $now->copy()->addDay()->startOfDay();

        return (int) $now->diffInSeconds($midnight);
    }
}
