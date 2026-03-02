<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provider usage tracking for memory API operations.
 *
 * Tracks token usage and cost estimates per provider operation.
 */
class MemoryProviderUsage extends Model
{
    use HasFactory;

    protected $table = 'memory_provider_usage';

    protected $guarded = [];

    /**
     * The name of the "updated at" column.
     * This table only has created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Provider constants.
     */
    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_ANTHROPIC = 'anthropic';

    /**
     * Operation constants.
     */
    public const OPERATION_EXTRACTION = 'extraction';

    public const OPERATION_SUMMARIZATION = 'summarization';

    public const OPERATION_EMBEDDING = 'embedding';

    public const OPERATION_IMPORTANCE_SCORING = 'importance_scoring';

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_estimate_usd' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentJobRun::class, 'run_id');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /**
     * Scope to filter by operation.
     */
    public function scopeForOperation(Builder $query, string $operation): void
    {
        $query->where('operation', $operation);
    }

    /**
     * Scope to filter by run.
     */
    public function scopeForRun(Builder $query, int $runId): void
    {
        $query->where('run_id', $runId);
    }

    /**
     * Scope for records within a date range.
     */
    public function scopeWithinDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for today's usage.
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('created_at', today());
    }

    /**
     * Scope for this month's usage.
     */
    public function scopeThisMonth(Builder $query): void
    {
        $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Calculate total tokens for this record.
     */
    public function getTotalTokens(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }

    /**
     * Calculate cost for given tokens using pricing config.
     */
    public static function calculateCost(
        string $provider,
        string $model,
        int $inputTokens,
        ?int $outputTokens = null
    ): float {
        $pricing = config("memory.pricing.{$provider}.{$model}");

        if (! $pricing) {
            return 0.0;
        }

        // Pricing is per 1M tokens
        $inputCost = ($inputTokens / 1_000_000) * ($pricing['input'] ?? 0);
        $outputCost = (($outputTokens ?? 0) / 1_000_000) * ($pricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * Record a provider usage entry.
     */
    public static function record(
        int $userId,
        string $provider,
        string $model,
        string $operation,
        int $inputTokens,
        ?int $outputTokens = null,
        ?int $runId = null
    ): static {
        $cost = self::calculateCost($provider, $model, $inputTokens, $outputTokens);

        return static::create([
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
     * Get aggregated usage statistics for a user.
     *
     * @return array{total_tokens: int, total_cost: float, by_provider: array<string, array{tokens: int, cost: float}>}
     */
    public static function getUsageStats(int $userId, ?\DateTimeInterface $since = null): array
    {
        $query = static::query()->forUser($userId);

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $records = $query->get();

        $stats = [
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'by_provider' => [],
        ];

        foreach ($records as $record) {
            $tokens = $record->getTotalTokens();
            $cost = $record->cost_estimate_usd ?? 0.0;

            $stats['total_tokens'] += $tokens;
            $stats['total_cost'] += $cost;

            $provider = $record->provider;
            if (! isset($stats['by_provider'][$provider])) {
                $stats['by_provider'][$provider] = ['tokens' => 0, 'cost' => 0.0];
            }
            $stats['by_provider'][$provider]['tokens'] += $tokens;
            $stats['by_provider'][$provider]['cost'] += $cost;
        }

        return $stats;
    }
}
