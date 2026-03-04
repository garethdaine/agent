<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityReasonCode;
use App\Enums\TelemetryFailureClass;
use App\Enums\TelemetryFailureReasonCode;

final class FailureTaxonomyMapper
{
    /**
     * @param  array<string, mixed>  $signals
     * @return array{hard_fail: bool, reason_code: ?string}
     */
    public function resolve(array $signals): array
    {
        $failureClass = $this->parseFailureClass($signals['failure_class'] ?? null);
        $failureReason = $this->parseFailureReason($signals['failure_reason_code'] ?? null);

        $policyBlocked = (bool) ($signals['policy_blocked'] ?? false);
        $guardrailBlocked = (bool) ($signals['guardrail_blocked'] ?? false);
        $explicitHardFail = (bool) ($signals['hard_fail'] ?? false);

        if ($policyBlocked || $failureReason === TelemetryFailureReasonCode::POLICY_BLOCKED) {
            return [
                'hard_fail' => true,
                'reason_code' => ReliabilityReasonCode::POLICY_BLOCKED->value,
            ];
        }

        if ($guardrailBlocked || $failureReason === TelemetryFailureReasonCode::GUARDRAIL_BLOCKED) {
            return [
                'hard_fail' => true,
                'reason_code' => ReliabilityReasonCode::GUARDRAIL_BLOCKED->value,
            ];
        }

        if ($explicitHardFail || $failureClass === TelemetryFailureClass::HARD_FAIL) {
            return [
                'hard_fail' => true,
                'reason_code' => ReliabilityReasonCode::HARD_FAIL->value,
            ];
        }

        return [
            'hard_fail' => false,
            'reason_code' => null,
        ];
    }

    private function parseFailureClass(mixed $value): ?TelemetryFailureClass
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return TelemetryFailureClass::tryFrom($value);
    }

    private function parseFailureReason(mixed $value): ?TelemetryFailureReasonCode
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return TelemetryFailureReasonCode::tryFrom($value);
    }
}
