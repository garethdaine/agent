<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\MemoryProviderUsage;
use DateTimeInterface;

/**
 * Tracks provider API usage and estimates costs.
 *
 * Provides:
 * - Token usage recording per operation
 * - Cost estimation from hardcoded pricing tables
 * - Usage aggregation per user
 * - Sensitive data stripping for logging
 *
 * IMPORTANT: This class never logs API keys or other sensitive data.
 * All context passed for logging is sanitized via getLoggableContext().
 */
class ProviderUsageTracker
{
    /**
     * Sensitive field patterns that should never be logged.
     * These are checked for exact match (case-insensitive).
     */
    private const SENSITIVE_FIELDS_EXACT = [
        'api_key',
        'apiKey',
        'api-key',
        'authorization',
        'secret',
        'password',
        'token',
        'bearer',
        'credential',
        'credentials',
        'access_token',
        'accessToken',
        'refresh_token',
        'refreshToken',
        'private_key',
        'privateKey',
    ];

    /**
     * Sensitive field suffixes to check.
     * Fields ending with these (case-insensitive) are considered sensitive.
     */
    private const SENSITIVE_SUFFIXES = [
        '_key',
        '_secret',
        '_token',
        '_password',
        '_credential',
    ];

    /**
     * Record provider usage.
     *
     * @param  int  $userId  User ID
     * @param  string  $provider  Provider name (e.g., 'openai')
     * @param  string  $model  Model name (e.g., 'gpt-4o-mini')
     * @param  string  $operation  Operation type (extraction, summarization, embedding)
     * @param  int  $inputTokens  Number of input tokens
     * @param  int|null  $outputTokens  Number of output tokens (null for embeddings)
     * @param  int|null  $runId  Optional run ID for tracking
     * @param  array<string, mixed>  $metadata  Optional metadata (sensitive fields stripped)
     * @return MemoryProviderUsage Created usage record
     */
    public function record(
        int $userId,
        string $provider,
        string $model,
        string $operation,
        int $inputTokens,
        ?int $outputTokens = null,
        ?int $runId = null,
        array $metadata = []
    ): MemoryProviderUsage {
        // Calculate cost from pricing table
        $cost = $this->estimateCost($provider, $model, $inputTokens, $outputTokens);

        return MemoryProviderUsage::create([
            'user_id' => $userId,
            'run_id' => $runId,
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_estimate_usd' => $cost,
            'created_at' => now(),
        ]);
    }

    /**
     * Estimate cost for a provider API call.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     * @param  int  $inputTokens  Number of input tokens
     * @param  int|null  $outputTokens  Number of output tokens
     * @return float Estimated cost in USD
     */
    public function estimateCost(
        string $provider,
        string $model,
        int $inputTokens,
        ?int $outputTokens = null
    ): float {
        $pricing = config("memory.pricing.{$provider}.{$model}");

        if ($pricing === null) {
            return 0.0;
        }

        // Pricing is per 1M tokens
        $inputCost = ($inputTokens / 1_000_000) * ($pricing['input'] ?? 0);
        $outputCost = (($outputTokens ?? 0) / 1_000_000) * ($pricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * Get usage statistics for a user.
     *
     * @param  int  $userId  User ID
     * @param  DateTimeInterface|null  $since  Optional start date filter
     * @param  DateTimeInterface|null  $until  Optional end date filter
     * @return array{total_tokens: int, total_cost: float, by_provider: array<string, array{tokens: int, cost: float}>, by_operation: array<string, array{tokens: int, cost: float}>}
     */
    public function getUsageStats(
        int $userId,
        ?DateTimeInterface $since = null,
        ?DateTimeInterface $until = null
    ): array {
        $query = MemoryProviderUsage::query()->forUser($userId);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        if ($until !== null) {
            $query->where('created_at', '<=', $until);
        }

        $records = $query->get();

        $stats = [
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'by_provider' => [],
            'by_operation' => [],
        ];

        foreach ($records as $record) {
            $tokens = $record->getTotalTokens();
            $cost = $record->cost_estimate_usd ?? 0.0;

            $stats['total_tokens'] += $tokens;
            $stats['total_cost'] += $cost;

            // Aggregate by provider
            $provider = $record->provider;
            if (! isset($stats['by_provider'][$provider])) {
                $stats['by_provider'][$provider] = ['tokens' => 0, 'cost' => 0.0];
            }
            $stats['by_provider'][$provider]['tokens'] += $tokens;
            $stats['by_provider'][$provider]['cost'] += $cost;

            // Aggregate by operation
            $operation = $record->operation;
            if (! isset($stats['by_operation'][$operation])) {
                $stats['by_operation'][$operation] = ['tokens' => 0, 'cost' => 0.0];
            }
            $stats['by_operation'][$operation]['tokens'] += $tokens;
            $stats['by_operation'][$operation]['cost'] += $cost;
        }

        return $stats;
    }

    /**
     * Get total cost for a user.
     *
     * @param  int  $userId  User ID
     * @param  DateTimeInterface|null  $since  Optional start date filter
     * @return float Total cost in USD
     */
    public function getTotalCost(int $userId, ?DateTimeInterface $since = null): float
    {
        $query = MemoryProviderUsage::query()
            ->forUser($userId)
            ->selectRaw('COALESCE(SUM(cost_estimate_usd), 0) as total_cost');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return (float) $query->value('total_cost');
    }

    /**
     * Strip sensitive fields from context for logging.
     *
     * IMPORTANT: Always use this method before logging any provider-related context.
     * This ensures API keys and other sensitive data never appear in logs.
     *
     * @param  array<string, mixed>  $context  Context to sanitize
     * @return array<string, mixed> Sanitized context safe for logging
     */
    public function getLoggableContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            // Check if key is sensitive
            if ($this->isSensitiveKey($key)) {
                continue; // Skip sensitive fields entirely
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->getLoggableContext($value);
            } else {
                // Check if value looks like an API key or bearer token
                if (is_string($value) && $this->looksLikeSensitiveValue($value)) {
                    continue; // Skip values that look like secrets
                }
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a key name indicates sensitive data.
     */
    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);

        // Check exact matches
        foreach (self::SENSITIVE_FIELDS_EXACT as $sensitiveField) {
            if (strcasecmp($key, $sensitiveField) === 0) {
                return true;
            }
        }

        // Check suffixes
        foreach (self::SENSITIVE_SUFFIXES as $suffix) {
            if (str_ends_with($keyLower, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value looks like sensitive data.
     */
    private function looksLikeSensitiveValue(string $value): bool
    {
        // Check for common API key patterns
        $patterns = [
            '/^sk-[a-zA-Z0-9]{20,}/', // OpenAI keys
            '/^Bearer\s+/', // Bearer tokens
            '/^Basic\s+/', // Basic auth
            '/^[a-zA-Z0-9]{32,}$/', // Long alphanumeric strings (likely keys)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
