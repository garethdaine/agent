<?php

namespace Tests\Unit\Support\Compliance\DTOs;

use App\Enums\TaskCategory;
use App\Support\Compliance\DTOs\GateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;
use PHPUnit\Framework\TestCase;

class PolicyEvaluationResultTest extends TestCase
{
    public function test_can_instantiate_with_all_properties(): void
    {
        $gateResult = new GateResult(
            gate: 'plan',
            status: 'pass',
            reasonCode: null,
            remediation: null,
            evidence: []
        );

        $result = new PolicyEvaluationResult(
            status: 'pass',
            complexity: 'simple',
            taskCategory: TaskCategory::FEATURE,
            planRequired: false,
            verificationRequired: true,
            eleganceRequired: false,
            gates: [$gateResult],
            metadataPatch: ['key' => 'value']
        );

        $this->assertSame('pass', $result->status);
        $this->assertSame('simple', $result->complexity);
        $this->assertSame(TaskCategory::FEATURE, $result->taskCategory);
        $this->assertFalse($result->planRequired);
        $this->assertTrue($result->verificationRequired);
        $this->assertFalse($result->eleganceRequired);
        $this->assertCount(1, $result->gates);
        $this->assertSame(['key' => 'value'], $result->metadataPatch);
    }

    public function test_is_blocked_returns_true_for_blocked_status(): void
    {
        $result = new PolicyEvaluationResult(
            status: 'blocked',
            complexity: 'non_trivial',
            taskCategory: TaskCategory::BUGFIX,
            planRequired: true,
            verificationRequired: true,
            eleganceRequired: false,
            gates: []
        );

        $this->assertTrue($result->isBlocked());
    }

    public function test_is_blocked_returns_false_for_pass_status(): void
    {
        $result = new PolicyEvaluationResult(
            status: 'pass',
            complexity: 'simple',
            taskCategory: TaskCategory::DOCS,
            planRequired: false,
            verificationRequired: false,
            eleganceRequired: false,
            gates: []
        );

        $this->assertFalse($result->isBlocked());
    }

    public function test_is_blocked_returns_false_for_advisory_status(): void
    {
        $result = new PolicyEvaluationResult(
            status: 'advisory',
            complexity: 'non_trivial',
            taskCategory: TaskCategory::REFACTOR,
            planRequired: true,
            verificationRequired: true,
            eleganceRequired: false,
            gates: []
        );

        $this->assertFalse($result->isBlocked());
    }

    public function test_metadata_patch_defaults_to_empty_array(): void
    {
        $result = new PolicyEvaluationResult(
            status: 'pass',
            complexity: 'simple',
            taskCategory: TaskCategory::TEST,
            planRequired: false,
            verificationRequired: false,
            eleganceRequired: false,
            gates: []
        );

        $this->assertSame([], $result->metadataPatch);
    }

    public function test_accepts_multiple_gate_results(): void
    {
        $gates = [
            new GateResult('plan', 'pass', null, null, []),
            new GateResult('verification', 'blocked', 'missing_evidence', 'Add test output', []),
            new GateResult('elegance', 'advisory', 'low_score', 'Consider refactoring', ['score' => 65]),
        ];

        $result = new PolicyEvaluationResult(
            status: 'blocked',
            complexity: 'non_trivial',
            taskCategory: TaskCategory::FEATURE,
            planRequired: true,
            verificationRequired: true,
            eleganceRequired: true,
            gates: $gates
        );

        $this->assertCount(3, $result->gates);
        $this->assertSame('plan', $result->gates[0]->gate);
        $this->assertSame('verification', $result->gates[1]->gate);
        $this->assertSame('elegance', $result->gates[2]->gate);
    }
}
