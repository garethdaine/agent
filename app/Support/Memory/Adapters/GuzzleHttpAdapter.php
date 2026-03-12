<?php

declare(strict_types=1);

namespace App\Support\Memory\Adapters;

use App\Support\Observability\LlmTelemetry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Base HTTP adapter for provider API calls using Guzzle.
 *
 * Provides common functionality for all provider adapters:
 * - HTTP client configuration
 * - Rate limit handling with retry and jitter
 * - Error logging and graceful degradation
 * - Token usage tracking helpers
 *
 * This is the primary implementation path since laravel/ai is not available.
 */
abstract class GuzzleHttpAdapter
{
    protected Client $client;

    protected string $apiKey;

    protected string $baseUrl;

    protected int $timeout;

    /**
     * Maximum retry attempts for rate-limited requests.
     */
    protected int $maxRetries = 3;

    /**
     * Base delay in seconds for exponential backoff.
     */
    protected float $baseDelay = 1.0;

    /**
     * Create a new HTTP adapter instance.
     *
     * @param  string  $apiKey  Provider API key
     * @param  string  $baseUrl  API base URL
     * @param  int  $timeout  Request timeout in seconds
     */
    public function __construct(string $apiKey, string $baseUrl, int $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;

        // Guzzle's base_uri follows RFC 3986 resolution rules:
        // Without a trailing slash, relative URIs with a leading slash replace the
        // entire path. e.g. 'https://api.openai.com/v1' + '/embeddings' resolves
        // to 'https://api.openai.com/embeddings' (losing /v1).
        // A trailing slash ensures the path is treated as a directory, so
        // 'https://api.openai.com/v1/' + 'embeddings' → 'https://api.openai.com/v1/embeddings'.
        $normalizedBaseUrl = rtrim($baseUrl, '/').'/';

        $this->client = new Client([
            'base_uri' => $normalizedBaseUrl,
            'timeout' => $timeout,
            'connect_timeout' => 5,
            'http_errors' => true,
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    /**
     * Get default headers for API requests.
     *
     * Override in subclasses for provider-specific headers.
     *
     * @return array<string, string>
     */
    abstract protected function getDefaultHeaders(): array;

    /**
     * Make an HTTP POST request with retry handling.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array<string, mixed>  $payload  Request body
     * @return array<string, mixed>|null Response data or null on failure
     */
    protected function post(string $endpoint, array $payload): ?array
    {
        // Strip leading slash so Guzzle resolves against base_uri correctly.
        // '/embeddings' would replace the entire path; 'embeddings' appends to it.
        $endpoint = ltrim($endpoint, '/');

        return $this->request('POST', $endpoint, ['json' => $payload]);
    }

    /**
     * Make an HTTP request with retry handling.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint path
     * @param  array<string, mixed>  $options  Guzzle request options
     * @return array<string, mixed>|null Response data or null on failure
     */
    protected function request(string $method, string $endpoint, array $options = []): ?array
    {
        $attempt = 0;
        $startTime = microtime(true);

        while ($attempt < $this->maxRetries) {
            try {
                $response = $this->client->request($method, $endpoint, $options);
                $body = $response->getBody()->getContents();
                $decoded = json_decode($body, true);

                $this->recordTelemetry($endpoint, $options, $decoded, $startTime);

                return $decoded;
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                if ($statusCode === 429) {
                    // Rate limited - handle with backoff
                    $retryAfter = $this->getRetryAfter($e);
                    $this->handleRateLimit($attempt, $retryAfter);
                    $attempt++;

                    continue;
                }

                // Other client errors (4xx) - don't retry
                $this->logError('Client error', $e, $endpoint);

                return null;
            } catch (RequestException $e) {
                // Server errors (5xx) - may retry
                if ($attempt < $this->maxRetries - 1) {
                    $this->handleRateLimit($attempt);
                    $attempt++;

                    continue;
                }

                $this->logError('Request error', $e, $endpoint);

                return null;
            } catch (GuzzleException $e) {
                $this->logError('HTTP error', $e, $endpoint);

                return null;
            }
        }

        return null;
    }

    /**
     * Extract Retry-After header value from rate limit response.
     *
     * @param  ClientException  $e  The rate limit exception
     * @return int|null Seconds to wait or null if not specified
     */
    protected function getRetryAfter(ClientException $e): ?int
    {
        $response = $e->getResponse();
        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter === '') {
            return null;
        }

        // Retry-After can be seconds or HTTP date
        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        // Parse HTTP date format
        $timestamp = strtotime($retryAfter);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        return null;
    }

    /**
     * Handle rate limiting with exponential backoff and jitter.
     *
     * @param  int  $attempt  Current retry attempt (0-indexed)
     * @param  int|null  $retryAfter  Server-specified delay in seconds
     */
    protected function handleRateLimit(int $attempt, ?int $retryAfter = null): void
    {
        if ($retryAfter !== null) {
            // Use server-specified delay with small jitter
            $delay = $retryAfter + $this->getJitter(0.1);
        } else {
            // Exponential backoff: 1s, 2s, 4s, 8s...
            $delay = $this->baseDelay * (2 ** $attempt);
            // Add jitter (up to 25% of delay)
            $delay += $this->getJitter($delay * 0.25);
        }

        // Cap at 60 seconds
        $delay = min($delay, 60.0);

        Log::debug('Memory provider rate limited, waiting', [
            'provider' => $this->getProviderName(),
            'attempt' => $attempt + 1,
            'delay_seconds' => $delay,
        ]);

        usleep((int) ($delay * 1_000_000));
    }

    /**
     * Generate random jitter for delay.
     *
     * @param  float  $maxJitter  Maximum jitter in seconds
     * @return float Random jitter value
     */
    protected function getJitter(float $maxJitter): float
    {
        return random_int(0, (int) ($maxJitter * 1000)) / 1000;
    }

    /**
     * Log an API error without exposing sensitive data.
     *
     * @param  string  $context  Error context description
     * @param  \Throwable  $e  The exception
     * @param  string  $endpoint  API endpoint that failed
     */
    protected function logError(string $context, \Throwable $e, string $endpoint): void
    {
        Log::warning("Memory provider {$context}", [
            'provider' => $this->getProviderName(),
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            // Never log API keys
        ]);
    }

    /**
     * Get the provider name for logging.
     *
     * @return string Provider identifier
     */
    abstract public function getProviderName(): string;

    /**
     * Estimate token count for text using character approximation.
     *
     * Uses 4 chars/token approximation from config.
     *
     * @param  string  $text  Text to estimate
     * @return int Estimated token count
     */
    protected function estimateTokens(string $text): int
    {
        $charsPerToken = config('memory.context_injection.chars_per_token', 4);

        return (int) ceil(strlen($text) / $charsPerToken);
    }

    /**
     * Record LLM telemetry for a successful API call.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array<string, mixed>  $options  Request options (contains json payload)
     * @param  array<string, mixed>|null  $response  Decoded response
     * @param  float  $startTime  Request start microtime
     */
    private function recordTelemetry(string $endpoint, array $options, ?array $response, float $startTime): void
    {
        try {
            $telemetry = app(LlmTelemetry::class);
            $latencyMs = (microtime(true) - $startTime) * 1000;
            $payload = $options['json'] ?? [];
            $model = $payload['model'] ?? 'unknown';
            $operation = $this->inferOperation($endpoint);

            // Extract token usage from response
            $usage = $response['usage'] ?? [];
            $tokensIn = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
            $tokensOut = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;

            $telemetry->record(
                $this->getProviderName(),
                $model,
                (int) $tokensIn,
                (int) $tokensOut,
                $latencyMs,
                $operation,
            );
        } catch (\Throwable) {
            // Telemetry must never break API calls
        }
    }

    /**
     * Infer the operation type from the API endpoint.
     */
    private function inferOperation(string $endpoint): string
    {
        if (str_contains($endpoint, 'embedding')) {
            return 'embedding';
        }

        if (str_contains($endpoint, 'chat') || str_contains($endpoint, 'message')) {
            return 'inference';
        }

        return 'api_call';
    }
}
