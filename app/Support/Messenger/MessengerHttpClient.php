<?php

declare(strict_types=1);

namespace App\Support\Messenger;

use App\Messenger\Exceptions\CircuitOpenException;
use App\Messenger\Reliability\CircuitBreaker;
use App\Models\ConnectorAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerHttpClient
{
    private ?CircuitBreaker $circuitBreaker = null;

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
        $circuit = $this->getCircuitBreaker();

        // Check circuit state before making request
        try {
            $circuit->canRequest();
        } catch (CircuitOpenException $e) {
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
                    $circuit->recordSuccess();

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
                    $circuit->recordFailure();
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
                $circuit->recordFailure();
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
        try {
            $this->getCircuitBreaker()->canRequest();

            return false;
        } catch (CircuitOpenException) {
            return true;
        }
    }

    /**
     * Get the circuit breaker for this connector.
     */
    private function getCircuitBreaker(): CircuitBreaker
    {
        if ($this->circuitBreaker === null) {
            $this->circuitBreaker = CircuitBreaker::forConnector($this->account->id);
        }

        return $this->circuitBreaker;
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
        $jitter = random_int(-intval($jitterRange * 1000), intval($jitterRange * 1000)) / 1000;

        return max(1, intval($delay + $jitter));
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
