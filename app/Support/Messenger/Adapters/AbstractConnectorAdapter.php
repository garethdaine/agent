<?php

namespace App\Support\Messenger\Adapters;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\StreamingConfig;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

abstract class AbstractConnectorAdapter implements ConnectorAdapterInterface
{
    /**
     * The provider name for this adapter.
     */
    abstract protected function getProviderName(): string;

    /**
     * Get the rate limiter key for this adapter.
     */
    protected function getRateLimiterKey(): string
    {
        return sprintf('messenger:%s:rate_limit', $this->getProviderName());
    }

    /**
     * Check if the rate limit has been exceeded.
     */
    protected function isRateLimited(): bool
    {
        $config = config(sprintf('messenger.providers.%s.rate_limit', $this->getProviderName()), []);
        $maxAttempts = $config['requests_per_second'] ?? 1;
        $burstLimit = $config['burst_limit'] ?? 5;

        return RateLimiter::tooManyAttempts($this->getRateLimiterKey(), $burstLimit);
    }

    /**
     * Record a rate limit hit.
     */
    protected function recordRateLimitHit(): void
    {
        $config = config(sprintf('messenger.providers.%s.rate_limit', $this->getProviderName()), []);
        $decaySeconds = intval(1 / ($config['requests_per_second'] ?? 1));

        RateLimiter::hit($this->getRateLimiterKey(), max(1, $decaySeconds));
    }

    /**
     * Get the circuit breaker key for this adapter.
     */
    protected function getCircuitBreakerKey(): string
    {
        return sprintf('messenger:%s:circuit_breaker', $this->getProviderName());
    }

    /**
     * Check if the circuit breaker is open (tripped).
     */
    protected function isCircuitBreakerOpen(): bool
    {
        $config = config(sprintf('messenger.providers.%s.rate_limit', $this->getProviderName()), []);
        $threshold = $config['circuit_breaker_threshold'] ?? 10;

        return RateLimiter::tooManyAttempts($this->getCircuitBreakerKey(), $threshold);
    }

    /**
     * Record a failure for the circuit breaker.
     */
    protected function recordCircuitBreakerFailure(): void
    {
        $config = config(sprintf('messenger.providers.%s.rate_limit', $this->getProviderName()), []);
        $cooldownSeconds = $config['circuit_breaker_cooldown_seconds'] ?? 60;

        RateLimiter::hit($this->getCircuitBreakerKey(), $cooldownSeconds);
    }

    /**
     * Reset the circuit breaker on success.
     */
    protected function resetCircuitBreaker(): void
    {
        RateLimiter::clear($this->getCircuitBreakerKey());
    }

    public function editMessage(ChatSession $session, string $providerMessageId, string $content): ProviderResponse
    {
        return ProviderResponse::failure('Message editing not supported by this provider');
    }

    public function supportsMessageEditing(): bool
    {
        return false;
    }

    public function supportsReactions(): bool
    {
        return false;
    }

    public function addReaction(ChatSession $session, string $messageId, string $emoji): ProviderResponse
    {
        return ProviderResponse::failure('Reactions not supported by this provider');
    }

    public function getStreamingConfig(): StreamingConfig
    {
        return StreamingConfig::fallback();
    }

    /**
     * Log an error with context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error(sprintf('[Messenger:%s] %s', $this->getProviderName(), $message), $context);
    }

    /**
     * Log info with context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info(sprintf('[Messenger:%s] %s', $this->getProviderName(), $message), $context);
    }

    /**
     * Log debug with context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug(sprintf('[Messenger:%s] %s', $this->getProviderName(), $message), $context);
    }

    /**
     * Calculate exponential backoff delay with jitter.
     */
    protected function calculateBackoffDelay(int $attempt): int
    {
        $config = config(sprintf('messenger.providers.%s.rate_limit', $this->getProviderName()), []);
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
     * Normalize content for messenger display.
     *
     * Converts escaped newline sequences to actual newlines and handles
     * other common formatting issues from AI-generated responses.
     */
    protected function normalizeContent(string $content): string
    {
        // Convert escaped newline sequences to actual newlines
        // This handles cases where AI responses contain literal \n instead of newlines
        $content = str_replace('\n', "\n", $content);

        // Normalize multiple consecutive newlines to at most two
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        // Trim leading/trailing whitespace
        return trim($content);
    }
}
