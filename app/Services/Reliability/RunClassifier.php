<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityReasonCode;
use App\Enums\ReliabilityRunClassification;

final class RunClassifier
{
    public function __construct(
        private readonly FailureTaxonomyMapper $failureTaxonomyMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $signals
     */
    public function classify(array $signals): RunClassificationResult
    {
        if ((bool) ($signals['skipped'] ?? false) === true) {
            return new RunClassificationResult(
                classification: ReliabilityRunClassification::SKIPPED,
                weight: ReliabilityRunClassification::SKIPPED->weight(),
                hardFail: false,
                reasonCode: 'skipped',
            );
        }

        $failure = $this->failureTaxonomyMapper->resolve($signals);
        if ($failure['hard_fail'] === true) {
            return $this->failed($failure['reason_code']);
        }

        if ((bool) ($signals['assisted_sla_expired'] ?? false) === true) {
            return $this->failed(ReliabilityReasonCode::ASSISTED_SLA_EXPIRED->value);
        }

        $verificationPassed = (bool) ($signals['verification_passed'] ?? false);
        $humanApprovalUsed = (bool) ($signals['human_approval_used'] ?? false);
        $degradedOutput = (bool) ($signals['degraded_output'] ?? false);

        if ($humanApprovalUsed) {
            if ($verificationPassed) {
                return new RunClassificationResult(
                    classification: ReliabilityRunClassification::ASSISTED,
                    weight: ReliabilityRunClassification::ASSISTED->weight(),
                    hardFail: false,
                    reasonCode: null,
                );
            }

            return $this->failed(ReliabilityReasonCode::VERIFICATION_FAILED->value);
        }

        if (! $verificationPassed) {
            return $this->failed(ReliabilityReasonCode::VERIFICATION_FAILED->value);
        }

        if ($degradedOutput) {
            return new RunClassificationResult(
                classification: ReliabilityRunClassification::DEGRADED,
                weight: ReliabilityRunClassification::DEGRADED->weight(),
                hardFail: false,
                reasonCode: null,
            );
        }

        return new RunClassificationResult(
            classification: ReliabilityRunClassification::SUCCESS,
            weight: ReliabilityRunClassification::SUCCESS->weight(),
            hardFail: false,
            reasonCode: null,
        );
    }

    private function failed(?string $reasonCode): RunClassificationResult
    {
        return new RunClassificationResult(
            classification: ReliabilityRunClassification::FAILED,
            weight: ReliabilityRunClassification::FAILED->weight(),
            hardFail: $reasonCode === ReliabilityReasonCode::HARD_FAIL->value
                || $reasonCode === ReliabilityReasonCode::POLICY_BLOCKED->value
                || $reasonCode === ReliabilityReasonCode::GUARDRAIL_BLOCKED->value,
            reasonCode: $reasonCode,
        );
    }
}
