<?php

namespace App\Support\Messenger;

use App\Models\ConnectorAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerHttpClient
{
    private const CIRCUIT_BREAKER_KEY_PREFIX = 'messenger:circuit_breaker:';

    private const CIRCUIT_STATE_CLOSED = 'closed';

    private const CIRCUIT_STATE_OPEN = 'open';

    private const CIRCUIT_STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly ConnectorAccount $account,
    ) {}

    /**
     * Make a POST request with retry logic and circuit breaker.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array{success: bool, response: ?Response, error: ?string}
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        if ($this->isCircuitOpen()) {
            Log::warning('[MessengerHttpClient] Circuit breaker open, rejecting request', [
                'account_id' => $this->account->id,
                'provider' => $this->account->provider,
                'url' => $url,
            ]);

            return [
                'success' => false,
                'response' => null,
                'error' => 'Circuit breaker open - service temporarily unavailable',
            ];
        }

        $config = $this->getRateLimitConfig();
        $maxAttempts = 3;
        $attempt = 0;
        $lastError = null;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $this->makeRequest($url, $data, $headers);
                $lastResponse = $response;

                if ($response->successful()) {
                    $this->recordSuccess();

                    return [
                        'success' => true,
                        'response' => $response,
                        'error' => null,
                    ];
                }

                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $retryAfter = $this->getRetryAfterSeconds($response, $attempt, $config);

                    Log::info('[MessengerHttpClient] Rate limited, backing off', [
                        'account_id' => $this->account->id,
                        'provider' => $this->account->provider,
                        'attempt' => $attempt,
                        'retry_after' => $retryAfter,
                    ]);

                    if ($attempt < $maxAttempts) {
                        usleep($retryAfter * 1000000);

                        continue;
                    }
                }

                // Handle server errors (5xx) with retry
                if ($response->serverError()) {
                    $this->recordFailure();
                    $lastError = sprintf('Server error: %d', $response->status());

                    if ($attempt < $maxAttempts) {
                        $backoffDelay = $this->calculateBackoffDelay($attempt, $config);
                        usleep($backoffDelay * 1000000);

                        continue;
                    }
                }

                // Client errors (4xx except 429) - don't retry
                if ($response->clientError() && $response->status() !== 429) {
                    $lastError = sprintf('Client error: %d - %s', $response->status(), $response->body());

                    return [
                        'success' => false,
                        'response' => $response,
                        'error' => $lastError,
                    ];
                }
            } catch (\Throwable $e) {
                $this->recordFailure();
                $lastError = $e->getMessage();

                Log::error('[MessengerHttpClient] Request exception', [
                    'account_id' => $this->account->id,
                    'provider' => $this->account->provider,
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    $backoffDelay = $this->calculateBackoffDelay($attempt, $config);
                    usleep($backoffDelay * 1000000);

                    continue;
                }
            }
        }

        return [
            'success' => false,
            'response' => $lastResponse,
            'error' => $lastError ?? 'Max retry attempts exceeded',
        ];
    }

    /**
     * Check if the circuit breaker is open.
     */
    public function isCircuitOpen(): bool
    {
        $state = Cache::get($this->getCircuitBreakerKey());

        if ($state === self::CIRCUIT_STATE_OPEN) {
            // Check if cooldown period has passed
            $cooldownKey = $this->getCircuitBreakerKey().':cooldown_until';
            $cooldownUntil = Cache::get($cooldownKey);

            if ($cooldownUntil && now()->timestamp < $cooldownUntil) {
                return true;
            }

            // Transition to half-open state
            Cache::put($this->getCircuitBreakerKey(), self::CIRCUIT_STATE_HALF_OPEN, 300);

            return false;
        }

        return false;
    }

    /**
     * Record a successful request (resets circuit breaker).
     */
    private function recordSuccess(): void
    {
        Cache::forget($this->getCircuitBreakerKey());
        Cache::forget($this->getCircuitBreakerKey().':failures');
        Cache::forget($this->getCircuitBreakerKey().':cooldown_until');
    }

    /**
     * Record a failed request (may trip circuit breaker).
     */
    private function recordFailure(): void
    {
        $config = $this->getRateLimitConfig();
        $threshold = $config['circuit_breaker_threshold'] ?? 10;
        $cooldownSeconds = $config['circuit_breaker_cooldown_seconds'] ?? 60;

        $failuresKey = $this->getCircuitBreakerKey().':failures';
        $failures = (int) Cache::get($failuresKey, 0) + 1;
        Cache::put($failuresKey, $failures, $cooldownSeconds * 2);

        if ($failures >= $threshold) {
            Log::warning('[MessengerHttpClient] Circuit breaker tripped', [
                'account_id' => $this->account->id,
                'provider' => $this->account->provider,
                'failures' => $failures,
                'threshold' => $threshold,
            ]);

            Cache::put($this->getCircuitBreakerKey(), self::CIRCUIT_STATE_OPEN, $cooldownSeconds * 2);
            Cache::put($this->getCircuitBreakerKey().':cooldown_until', now()->timestamp + $cooldownSeconds, $cooldownSeconds * 2);
        }
    }

    /**
     * Make the actual HTTP request.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function makeRequest(string $url, array $data, array $headers): Response
    {
        $timeout = $this->getTimeoutSeconds();

        $request = Http::timeout($timeout)->withHeaders($headers);

        Log::debug('[MessengerHttpClient] Sending request', [
            'account_id' => $this->account->id,
            'provider' => $this->account->provider,
            'url' => $url,
            'timeout' => $timeout,
        ]);

        $response = $request->post($url, $data);

        Log::debug('[MessengerHttpClient] Received response', [
            'account_id' => $this->account->id,
            'provider' => $this->account->provider,
            'url' => $url,
            'status' => $response->status(),
        ]);

        return $response;
    }

    /**
     * Get the retry-after delay in seconds.
     *
     * @param  array<string, mixed>  $config
     */
    private function getRetryAfterSeconds(Response $response, int $attempt, array $config): int
    {
        // Check for Retry-After header
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter) {
            // Could be seconds or HTTP date
            if (is_numeric($retryAfter)) {
                return (int) $retryAfter;
            }

            // Parse HTTP date
            $retryTime = strtotime($retryAfter);
            if ($retryTime !== false) {
                return max(1, $retryTime - time());
            }
        }

        // Fall back to exponential backoff
        return $this->calculateBackoffDelay($attempt, $config);
    }

    /**
     * Calculate exponential backoff delay with jitter.
     *
     * @param  array<string, mixed>  $config
     */
    private function calculateBackoffDelay(int $attempt, array $config): int
    {
        $baseSeconds = $config['backoff_base_seconds'] ?? 1;
        $multiplier = $config['backoff_multiplier'] ?? 2;
        $maxSeconds = $config['backoff_max_seconds'] ?? 300;
        $jitterPercent = $config['jitter_percent'] ?? 20;

        $delay = min($baseSeconds * pow($multiplier, $attempt - 1), $maxSeconds);

        // Apply jitter
        $jitterRange = $delay * ($jitterPercent / 100);
        $jitter = mt_rand(-intval($jitterRange * 1000), intval($jitterRange * 1000)) / 1000;

        return max(1, intval($delay + $jitter));
    }

    /**
     * Get the circuit breaker cache key.
     */
    private function getCircuitBreakerKey(): string
    {
        return self::CIRCUIT_BREAKER_KEY_PREFIX.$this->account->id;
    }

    /**
     * Get rate limit configuration for this provider.
     *
     * @return array<string, mixed>
     */
    private function getRateLimitConfig(): array
    {
        return config(sprintf('messenger.providers.%s.rate_limit', $this->account->provider), []);
    }

    /**
     * Get timeout in seconds for this provider.
     */
    private function getTimeoutSeconds(): int
    {
        return config(sprintf('messenger.providers.%s.timeout_seconds', $this->account->provider), 30);
    }
}
