<?php

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
        public array $failureModeDistribution,
        public int $sampleSize,
    ) {}
}
