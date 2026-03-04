<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityRunClassification;

final class WeightedReliabilityScorer
{
    /**
     * @param  iterable<ReliabilityRunClassification|RunClassificationResult|string>  $classifications
     */
    public function score(iterable $classifications): WeightedReliabilityResult
    {
        $totalWeight = 0.0;
        $scoredRuns = 0;
        $skippedRuns = 0;
        $degradedRuns = 0;

        foreach ($classifications as $classification) {
            $normalized = $this->normalize($classification);
            if ($normalized === null) {
                continue;
            }

            if (! $normalized->countsTowardReliability()) {
                $skippedRuns++;

                continue;
            }

            $scoredRuns++;
            $totalWeight += $normalized->weight();

            if ($normalized === ReliabilityRunClassification::DEGRADED) {
                $degradedRuns++;
            }
        }

        if ($scoredRuns === 0) {
            return new WeightedReliabilityResult(
                weightedReliability: null,
                degradedRate: null,
                scoredRuns: 0,
                skippedRuns: $skippedRuns,
            );
        }

        return new WeightedReliabilityResult(
            weightedReliability: round(($totalWeight / $scoredRuns) * 100, 2),
            degradedRate: round(($degradedRuns / $scoredRuns) * 100, 2),
            scoredRuns: $scoredRuns,
            skippedRuns: $skippedRuns,
        );
    }

    private function normalize(ReliabilityRunClassification|RunClassificationResult|string $value): ?ReliabilityRunClassification
    {
        if ($value instanceof RunClassificationResult) {
            return $value->classification;
        }

        if ($value instanceof ReliabilityRunClassification) {
            return $value;
        }

        return ReliabilityRunClassification::tryFrom(trim($value));
    }
}
