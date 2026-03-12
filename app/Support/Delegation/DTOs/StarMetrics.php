<?php

declare(strict_types=1);

namespace App\Support\Delegation\DTOs;

readonly class StarMetrics
{
    public function __construct(
        public float $starCompletionRate,
        public float $situationCorrectRate,
        public float $taskCorrectRate,
        public float $actionCorrectRate,
        public float $resultCorrectRate,
        public float $firstPassSuccessRate,
        public float $recoveryRate,
        public float $livenessRate,
        public array $failureModeDistribution,
        public int $sampleSize,
    ) {}
}
