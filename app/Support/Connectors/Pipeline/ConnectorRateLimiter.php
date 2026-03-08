<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\RateLimitExceededException;
use Closure;
use Illuminate\Support\Facades\Redis;

class ConnectorRateLimiter
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $connectionId = $request->connection->id;
        $rateLimits = $request->connector->rate_limits ?? [];

        $perMinute = $rateLimits['requests_per_minute'] ?? 60;
        $dailyLimit = $rateLimits['daily_limit'] ?? null;
        $concurrentLimit = $rateLimits['concurrent_limit'] ?? null;

        $this->checkPerMinuteLimit($connectionId, $perMinute);

        if ($dailyLimit) {
            $this->checkDailyLimit($connectionId, $dailyLimit);
        }

        if ($concurrentLimit) {
            $this->checkConcurrentLimit($connectionId, $concurrentLimit);
        }

        $this->incrementCounters($connectionId);

        try {
            $result = $next($request);
        } finally {
            if ($concurrentLimit) {
                $this->decrementConcurrent($connectionId);
            }
        }

        return $result;
    }

    private function checkPerMinuteLimit(string $connectionId, int $limit): void
    {
        $key = "connector_rate:{$connectionId}:minute";
        $count = (int) Redis::get($key);

        if ($count >= $limit) {
            $ttl = max(1, (int) Redis::ttl($key));
            throw new RateLimitExceededException($ttl);
        }
    }

    private function checkDailyLimit(string $connectionId, int $limit): void
    {
        $key = "connector_rate:{$connectionId}:daily";
        $count = (int) Redis::get($key);

        if ($count >= $limit) {
            $ttl = max(1, (int) Redis::ttl($key));
            throw new RateLimitExceededException($ttl);
        }
    }

    private function checkConcurrentLimit(string $connectionId, int $limit): void
    {
        $key = "connector_rate:{$connectionId}:concurrent";
        $count = (int) Redis::get($key);

        if ($count >= $limit) {
            throw new RateLimitExceededException(1, 'Concurrent request limit exceeded.');
        }
    }

    private function incrementCounters(string $connectionId): void
    {
        $minuteKey = "connector_rate:{$connectionId}:minute";
        $dailyKey = "connector_rate:{$connectionId}:daily";
        $concurrentKey = "connector_rate:{$connectionId}:concurrent";

        Redis::multi();
        Redis::incr($minuteKey);
        Redis::expire($minuteKey, 60);
        Redis::incr($dailyKey);
        Redis::expire($dailyKey, 86400);
        Redis::incr($concurrentKey);
        Redis::exec();
    }

    private function decrementConcurrent(string $connectionId): void
    {
        $key = "connector_rate:{$connectionId}:concurrent";
        Redis::decr($key);
    }
}
