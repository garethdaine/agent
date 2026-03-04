<?php

declare(strict_types=1);

namespace App\Services\Cost;

use InvalidArgumentException;

final class CanonicalCostCalculator
{
    public function calculate(
        int $inputTokens,
        int $outputTokens,
        float $inputCostPerThousandUsd,
        float $outputCostPerThousandUsd,
    ): float {
        if ($inputTokens < 0 || $outputTokens < 0) {
            throw new InvalidArgumentException('Token counts must be non-negative.');
        }

        if ($inputCostPerThousandUsd < 0 || $outputCostPerThousandUsd < 0) {
            throw new InvalidArgumentException('Rate values must be non-negative.');
        }

        $inputCost = ($inputTokens / 1000) * $inputCostPerThousandUsd;
        $outputCost = ($outputTokens / 1000) * $outputCostPerThousandUsd;

        return round($inputCost + $outputCost, 6);
    }
}
