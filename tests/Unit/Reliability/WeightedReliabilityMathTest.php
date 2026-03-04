<?php

declare(strict_types=1);

namespace Tests\Unit\Reliability;

use App\Enums\ReliabilityRunClassification;
use App\Services\Reliability\WeightedReliabilityScorer;
use Tests\TestCase;

class WeightedReliabilityMathTest extends TestCase
{
    public function test_weighted_reliability_formula_uses_v1_weights(): void
    {
        $scorer = app(WeightedReliabilityScorer::class);

        $result = $scorer->score([
            ReliabilityRunClassification::SUCCESS,
            ReliabilityRunClassification::ASSISTED,
            ReliabilityRunClassification::DEGRADED,
            ReliabilityRunClassification::FAILED,
        ]);

        $this->assertSame(4, $result->scoredRuns);
        $this->assertSame(0, $result->skippedRuns);
        $this->assertSame(55.0, $result->weightedReliability);
        $this->assertSame(25.0, $result->degradedRate);
    }

    public function test_skipped_runs_are_excluded_from_weighted_numerator_and_denominator(): void
    {
        $scorer = app(WeightedReliabilityScorer::class);

        $result = $scorer->score([
            ReliabilityRunClassification::SUCCESS,
            ReliabilityRunClassification::SKIPPED,
            ReliabilityRunClassification::FAILED,
        ]);

        $this->assertSame(2, $result->scoredRuns);
        $this->assertSame(1, $result->skippedRuns);
        $this->assertSame(50.0, $result->weightedReliability);
    }

    public function test_all_skipped_runs_return_null_weighted_reliability(): void
    {
        $scorer = app(WeightedReliabilityScorer::class);

        $result = $scorer->score([
            ReliabilityRunClassification::SKIPPED,
            ReliabilityRunClassification::SKIPPED,
        ]);

        $this->assertSame(0, $result->scoredRuns);
        $this->assertSame(2, $result->skippedRuns);
        $this->assertNull($result->weightedReliability);
        $this->assertNull($result->degradedRate);
    }
}
