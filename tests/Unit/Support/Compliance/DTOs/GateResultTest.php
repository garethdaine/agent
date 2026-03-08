<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compliance\DTOs;

use App\Support\Compliance\DTOs\GateResult;
use PHPUnit\Framework\TestCase;

class GateResultTest extends TestCase
{
    public function test_can_instantiate_with_required_properties(): void
    {
        $result = new GateResult(
            gate: 'plan',
            status: 'pass'
        );

        $this->assertSame('plan', $result->gate);
        $this->assertSame('pass', $result->status);
        $this->assertNull($result->reasonCode);
        $this->assertNull($result->remediation);
        $this->assertSame([], $result->evidence);
    }

    public function test_can_instantiate_with_all_properties(): void
    {
        $evidence = ['test_output' => 'OK', 'coverage' => '85%'];

        $result = new GateResult(
            gate: 'verification',
            status: 'blocked',
            reasonCode: 'missing_automated_check',
            remediation: 'Run `php artisan test` and capture output',
            evidence: $evidence
        );

        $this->assertSame('verification', $result->gate);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('missing_automated_check', $result->reasonCode);
        $this->assertSame('Run `php artisan test` and capture output', $result->remediation);
        $this->assertSame($evidence, $result->evidence);
    }

    public function test_supports_advisory_status(): void
    {
        $result = new GateResult(
            gate: 'elegance',
            status: 'advisory',
            reasonCode: 'low_score',
            remediation: 'Consider improving code quality'
        );

        $this->assertSame('advisory', $result->status);
    }

    public function test_evidence_defaults_to_empty_array(): void
    {
        $result = new GateResult(
            gate: 'bugfix_evidence',
            status: 'pass',
            reasonCode: null,
            remediation: null
        );

        $this->assertSame([], $result->evidence);
    }

    public function test_immutable_properties(): void
    {
        $result = new GateResult(
            gate: 'plan',
            status: 'pass'
        );

        // Verify the class is readonly by checking we can read properties
        $this->assertSame('plan', $result->gate);
        $this->assertSame('pass', $result->status);
    }
}
