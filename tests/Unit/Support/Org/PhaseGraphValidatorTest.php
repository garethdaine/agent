<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org;

use App\Support\Org\Exceptions\ConditionalBranchingNotAllowedException;
use App\Support\Org\Exceptions\InvalidPhaseGraphException;
use App\Support\Org\PhaseGraphValidator;
use Tests\TestCase;

class PhaseGraphValidatorTest extends TestCase
{
    private PhaseGraphValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PhaseGraphValidator;
    }

    public function test_validates_linear_phase_graph(): void
    {
        $graph = [
            ['id' => 'phase1', 'name' => 'Phase 1', 'depends_on' => []],
            ['id' => 'phase2', 'name' => 'Phase 2', 'depends_on' => ['phase1']],
            ['id' => 'phase3', 'name' => 'Phase 3', 'depends_on' => ['phase2']],
        ];

        $result = $this->validator->validate($graph);

        $this->assertTrue($result);
    }

    public function test_validates_dag_phase_graph(): void
    {
        $graph = [
            ['id' => 'phase1', 'name' => 'Phase 1', 'depends_on' => []],
            ['id' => 'phase2', 'name' => 'Phase 2', 'depends_on' => ['phase1']],
            ['id' => 'phase3', 'name' => 'Phase 3', 'depends_on' => ['phase1']],
            ['id' => 'phase4', 'name' => 'Phase 4', 'depends_on' => ['phase2', 'phase3']],
        ];

        $result = $this->validator->validate($graph);

        $this->assertTrue($result);
    }

    public function test_rejects_cyclic_graph(): void
    {
        $graph = [
            ['id' => 'phase1', 'depends_on' => ['phase3']],
            ['id' => 'phase2', 'depends_on' => ['phase1']],
            ['id' => 'phase3', 'depends_on' => ['phase2']],
        ];

        $this->expectException(InvalidPhaseGraphException::class);
        $this->expectExceptionMessage('cycle');

        $this->validator->validate($graph);
    }

    public function test_rejects_conditional_branching(): void
    {
        $graph = [
            ['id' => 'phase1', 'depends_on' => []],
            ['id' => 'phase2', 'depends_on' => ['phase1'], 'condition' => 'output.success == true'],
        ];

        $this->expectException(ConditionalBranchingNotAllowedException::class);

        $this->validator->validate($graph);
    }

    public function test_rejects_if_else_structures(): void
    {
        $graph = [
            ['id' => 'phase1', 'depends_on' => []],
            ['id' => 'phase2', 'depends_on' => ['phase1'], 'when' => 'someCondition'],
            ['id' => 'phase3', 'depends_on' => ['phase1'], 'unless' => 'someCondition'],
        ];

        $this->expectException(ConditionalBranchingNotAllowedException::class);

        $this->validator->validate($graph);
    }
}
