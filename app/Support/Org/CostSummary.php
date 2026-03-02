<?php

namespace App\Support\Org;

/**
 * Data transfer object representing aggregated cost information.
 */
class CostSummary
{
    public function __construct(
        public readonly int $total_tokens,
        public readonly int $total_runtime_ms,
        public readonly float $total_cost_usd,
        public readonly int $entry_count = 0,
    ) {}

    /**
     * Create a zero-value summary.
     */
    public static function zero(): self
    {
        return new self(
            total_tokens: 0,
            total_runtime_ms: 0,
            total_cost_usd: 0.0,
            entry_count: 0,
        );
    }

    /**
     * Create a summary from aggregated database results.
     */
    public static function fromAggregate(array $aggregate): self
    {
        return new self(
            total_tokens: (int) ($aggregate['total_tokens'] ?? 0),
            total_runtime_ms: (int) ($aggregate['total_runtime_ms'] ?? 0),
            total_cost_usd: (float) ($aggregate['total_cost_usd'] ?? 0.0),
            entry_count: (int) ($aggregate['entry_count'] ?? 0),
        );
    }

    /**
     * Check if the summary represents zero costs.
     */
    public function isEmpty(): bool
    {
        return $this->total_tokens === 0
            && $this->total_runtime_ms === 0
            && $this->total_cost_usd === 0.0;
    }
}
