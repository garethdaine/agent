<?php

declare(strict_types=1);

namespace App\Services\Reliability;

final readonly class WeightedReliabilityResult
{
    public function __construct(
        public ?float $weightedReliability,
        public ?float $degradedRate,
        public int $scoredRuns,
        public int $skippedRuns,
    ) {}
}
