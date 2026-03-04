<?php

declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use App\Enums\TelemetryFailureClass;
use App\Enums\TelemetryFailureReasonCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelemetryEnumSerializationTest extends TestCase
{
    #[Test]
    public function telemetry_failure_class_values_are_stable(): void
    {
        $this->assertSame(
            ['hard_fail', 'soft_fail', 'degraded', 'control_flow'],
            array_map(
                static fn (TelemetryFailureClass $case): string => $case->value,
                TelemetryFailureClass::cases()
            )
        );
    }

    #[Test]
    public function telemetry_failure_reason_code_values_are_stable(): void
    {
        $this->assertSame(
            [
                'timeout',
                'rate_limited',
                'guardrail_blocked',
                'approval_required',
                'policy_blocked',
                'dependency_error',
                'infra_error',
                'provider_error',
                'validation_error',
                'output_quality_fail',
                'budget_breach',
                'telemetry_unobservable',
                'skipped',
                'cancelled',
            ],
            array_map(
                static fn (TelemetryFailureReasonCode $case): string => $case->value,
                TelemetryFailureReasonCode::cases()
            )
        );
    }

    #[Test]
    public function enums_round_trip_and_json_serialize(): void
    {
        $class = TelemetryFailureClass::from('degraded');
        $reason = TelemetryFailureReasonCode::from('output_quality_fail');

        $this->assertSame('degraded', $class->value);
        $this->assertSame('output_quality_fail', $reason->value);

        $json = json_encode([
            'failure_class' => $class,
            'failure_reason_code' => $reason,
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(
            '{"failure_class":"degraded","failure_reason_code":"output_quality_fail"}',
            $json
        );
    }
}
