<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityReasonCode;

final class GateEvaluator
{
    /**
     * @param  array{
     *   rolling_14d_score:?float,
     *   rolling_50_run_score:?float,
     *   rolling_14d_degraded_rate:?float,
     *   rolling_50_run_degraded_rate:?float,
     *   rolling_14d_scored_runs:int,
     *   rolling_50_run_scored_runs:int,
     *   consecutive_hard_fail_count:int,
     *   hard_fail_count_24h:int
     * }  $payload
     */
    public function evaluate(array $payload): GateEvaluationResult
    {
        $consecutiveHardFails = max(0, (int) $payload['consecutive_hard_fail_count']);
        $hardFails24h = max(0, (int) $payload['hard_fail_count_24h']);

        if ($consecutiveHardFails >= 2 || $hardFails24h >= 3) {
            return new GateEvaluationResult(
                gateState: 'hard_gate',
                reasonCode: ReliabilityReasonCode::HARD_FAIL_BURST->value,
                shouldAutoPause: true,
            );
        }

        $minimumScoredRuns = max(1, (int) config('agent.reliability.low_volume_min_scored_runs', 5));
        if ((int) $payload['rolling_14d_scored_runs'] < $minimumScoredRuns
            || (int) $payload['rolling_50_run_scored_runs'] < $minimumScoredRuns) {
            return new GateEvaluationResult(
                gateState: 'insufficient_data',
                reasonCode: ReliabilityReasonCode::INSUFFICIENT_DATA->value,
                shouldAutoPause: false,
            );
        }

        $threshold = (float) config('agent.reliability.weighted_reliability_threshold', 95.0);
        $degradedThreshold = (float) config('agent.reliability.degraded_rate_threshold', 3.0);

        $score14d = $payload['rolling_14d_score'];
        $score50 = $payload['rolling_50_run_score'];
        $degraded14d = $payload['rolling_14d_degraded_rate'];
        $degraded50 = $payload['rolling_50_run_degraded_rate'];

        $scoreBreach = $this->worstScore($score14d, $score50) < $threshold;
        $degradedBreach = $this->worstRate($degraded14d, $degraded50) > $degradedThreshold;

        if ($scoreBreach) {
            return new GateEvaluationResult(
                gateState: 'soft_gate',
                reasonCode: ReliabilityReasonCode::RELIABILITY_BELOW_THRESHOLD->value,
                shouldAutoPause: false,
            );
        }

        if ($degradedBreach) {
            return new GateEvaluationResult(
                gateState: 'soft_gate',
                reasonCode: ReliabilityReasonCode::DEGRADED_RATE_EXCEEDED->value,
                shouldAutoPause: false,
            );
        }

        return new GateEvaluationResult(
            gateState: 'healthy',
            reasonCode: null,
            shouldAutoPause: false,
        );
    }

    private function worstScore(?float $score14d, ?float $score50): float
    {
        if ($score14d === null && $score50 === null) {
            return 0.0;
        }

        if ($score14d === null) {
            return (float) $score50;
        }

        if ($score50 === null) {
            return (float) $score14d;
        }

        return min($score14d, $score50);
    }

    private function worstRate(?float $rate14d, ?float $rate50): float
    {
        if ($rate14d === null && $rate50 === null) {
            return 0.0;
        }

        if ($rate14d === null) {
            return (float) $rate50;
        }

        if ($rate50 === null) {
            return (float) $rate14d;
        }

        return max($rate14d, $rate50);
    }
}
