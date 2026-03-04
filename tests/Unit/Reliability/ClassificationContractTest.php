<?php

declare(strict_types=1);

namespace Tests\Unit\Reliability;

use App\Enums\ReliabilityRunClassification;
use App\Enums\TelemetryFailureClass;
use App\Enums\TelemetryFailureReasonCode;
use App\Services\Reliability\GateEvaluator;
use App\Services\Reliability\RunClassifier;
use Tests\TestCase;

class ClassificationContractTest extends TestCase
{
    public function test_success_rule_requires_verified_outcome_without_hard_fail_or_human_intervention(): void
    {
        $classifier = app(RunClassifier::class);

        $result = $classifier->classify([
            'verification_passed' => true,
            'human_approval_used' => false,
            'degraded_output' => false,
            'failure_class' => null,
            'failure_reason_code' => null,
            'policy_blocked' => false,
            'guardrail_blocked' => false,
            'assisted_sla_expired' => false,
        ]);

        $this->assertSame(ReliabilityRunClassification::SUCCESS, $result->classification);
        $this->assertSame(1.0, $result->weight);
        $this->assertFalse($result->hardFail);
    }

    public function test_assisted_rule_requires_human_approval_and_verification_within_sla(): void
    {
        $classifier = app(RunClassifier::class);

        $result = $classifier->classify([
            'verification_passed' => true,
            'human_approval_used' => true,
            'degraded_output' => false,
            'failure_class' => null,
            'failure_reason_code' => null,
            'policy_blocked' => false,
            'guardrail_blocked' => false,
            'assisted_sla_expired' => false,
        ]);

        $this->assertSame(ReliabilityRunClassification::ASSISTED, $result->classification);
        $this->assertSame(0.7, $result->weight);
    }

    public function test_degraded_rule_applies_when_quality_reduced_without_human_approval(): void
    {
        $classifier = app(RunClassifier::class);

        $result = $classifier->classify([
            'verification_passed' => true,
            'human_approval_used' => false,
            'degraded_output' => true,
            'failure_class' => TelemetryFailureClass::DEGRADED->value,
            'failure_reason_code' => TelemetryFailureReasonCode::OUTPUT_QUALITY_FAIL->value,
            'policy_blocked' => false,
            'guardrail_blocked' => false,
            'assisted_sla_expired' => false,
        ]);

        $this->assertSame(ReliabilityRunClassification::DEGRADED, $result->classification);
        $this->assertSame(0.5, $result->weight);
    }

    public function test_failed_rule_covers_policy_blocked_and_assisted_sla_expiry(): void
    {
        $classifier = app(RunClassifier::class);

        $policyBlocked = $classifier->classify([
            'verification_passed' => true,
            'human_approval_used' => false,
            'degraded_output' => false,
            'failure_class' => TelemetryFailureClass::SOFT_FAIL->value,
            'failure_reason_code' => TelemetryFailureReasonCode::POLICY_BLOCKED->value,
            'policy_blocked' => true,
            'guardrail_blocked' => false,
            'assisted_sla_expired' => false,
        ]);

        $slaExpired = $classifier->classify([
            'verification_passed' => true,
            'human_approval_used' => true,
            'degraded_output' => false,
            'failure_class' => null,
            'failure_reason_code' => null,
            'policy_blocked' => false,
            'guardrail_blocked' => false,
            'assisted_sla_expired' => true,
        ]);

        $this->assertSame(ReliabilityRunClassification::FAILED, $policyBlocked->classification);
        $this->assertTrue($policyBlocked->hardFail);
        $this->assertSame('policy_blocked', $policyBlocked->reasonCode);

        $this->assertSame(ReliabilityRunClassification::FAILED, $slaExpired->classification);
        $this->assertSame('assisted_sla_expired', $slaExpired->reasonCode);
    }

    public function test_hard_fail_burst_override_triggers_immediate_hard_gate(): void
    {
        $evaluator = app(GateEvaluator::class);

        $result = $evaluator->evaluate([
            'rolling_14d_score' => 99.0,
            'rolling_50_run_score' => 98.0,
            'rolling_14d_degraded_rate' => 1.0,
            'rolling_50_run_degraded_rate' => 2.0,
            'rolling_14d_scored_runs' => 50,
            'rolling_50_run_scored_runs' => 50,
            'consecutive_hard_fail_count' => 2,
            'hard_fail_count_24h' => 2,
        ]);

        $this->assertSame('hard_gate', $result->gateState);
        $this->assertSame('hard_fail_burst', $result->reasonCode);
        $this->assertTrue($result->shouldAutoPause);
    }

    public function test_low_volume_sets_insufficient_data_without_auto_pause(): void
    {
        $evaluator = app(GateEvaluator::class);

        $result = $evaluator->evaluate([
            'rolling_14d_score' => null,
            'rolling_50_run_score' => null,
            'rolling_14d_degraded_rate' => null,
            'rolling_50_run_degraded_rate' => null,
            'rolling_14d_scored_runs' => 1,
            'rolling_50_run_scored_runs' => 1,
            'consecutive_hard_fail_count' => 0,
            'hard_fail_count_24h' => 0,
        ]);

        $this->assertSame('insufficient_data', $result->gateState);
        $this->assertSame('insufficient_data', $result->reasonCode);
        $this->assertFalse($result->shouldAutoPause);
    }
}
