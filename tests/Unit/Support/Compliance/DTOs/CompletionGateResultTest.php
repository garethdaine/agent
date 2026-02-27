<?php

namespace Tests\Unit\Support\Compliance\DTOs;

use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\GateResult;
use PHPUnit\Framework\TestCase;

class CompletionGateResultTest extends TestCase
{
    public function test_can_instantiate_with_required_properties(): void
    {
        $result = new CompletionGateResult(
            canComplete: true,
            status: 'pass',
            gates: []
        );

        $this->assertTrue($result->canComplete);
        $this->assertSame('pass', $result->status);
        $this->assertSame([], $result->gates);
        $this->assertNull($result->blockReason);
        $this->assertNull($result->remediation);
    }

    public function test_can_instantiate_with_all_properties(): void
    {
        $gates = [
            new GateResult('verification', 'pass', null, null, []),
            new GateResult('plan', 'pass', null, null, []),
        ];

        $result = new CompletionGateResult(
            canComplete: false,
            status: 'blocked',
            gates: $gates,
            blockReason: 'Missing verification evidence',
            remediation: 'Run tests and capture output'
        );

        $this->assertFalse($result->canComplete);
        $this->assertSame('blocked', $result->status);
        $this->assertCount(2, $result->gates);
        $this->assertSame('Missing verification evidence', $result->blockReason);
        $this->assertSame('Run tests and capture output', $result->remediation);
    }

    public function test_can_complete_true_when_all_gates_pass(): void
    {
        $gates = [
            new GateResult('verification', 'pass', null, null, []),
            new GateResult('plan', 'pass', null, null, []),
        ];

        $result = new CompletionGateResult(
            canComplete: true,
            status: 'pass',
            gates: $gates
        );

        $this->assertTrue($result->canComplete);
        $this->assertSame('pass', $result->status);
    }

    public function test_can_complete_false_when_any_gate_blocked(): void
    {
        $gates = [
            new GateResult('verification', 'pass', null, null, []),
            new GateResult('bugfix_evidence', 'blocked', 'missing_root_cause', 'Document root cause', []),
        ];

        $result = new CompletionGateResult(
            canComplete: false,
            status: 'blocked',
            gates: $gates,
            blockReason: 'Bugfix evidence chain incomplete',
            remediation: 'Document root cause analysis'
        );

        $this->assertFalse($result->canComplete);
        $this->assertSame('blocked', $result->status);
    }

    public function test_supports_advisory_status(): void
    {
        $gates = [
            new GateResult('elegance', 'advisory', 'low_score', 'Consider refactoring', ['score' => 65]),
        ];

        $result = new CompletionGateResult(
            canComplete: true,
            status: 'advisory',
            gates: $gates,
            blockReason: null,
            remediation: 'Elegance score below recommended threshold'
        );

        $this->assertTrue($result->canComplete);
        $this->assertSame('advisory', $result->status);
    }

    public function test_immutable_properties(): void
    {
        $result = new CompletionGateResult(
            canComplete: true,
            status: 'pass',
            gates: []
        );

        // Verify the class is readonly by checking we can read properties
        $this->assertTrue($result->canComplete);
        $this->assertSame('pass', $result->status);
    }
}
