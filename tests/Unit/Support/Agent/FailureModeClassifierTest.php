<?php

namespace Tests\Unit\Support\Agent;

use App\Models\AgentJob;
use App\Support\Agent\FailureModeClassifier;
use Tests\TestCase;

class FailureModeClassifierTest extends TestCase
{
    private FailureModeClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new FailureModeClassifier();
    }

    public function test_classifies_type_1_missing_subject(): void
    {
        $job = AgentJob::factory()->make([
            'name' => 'Update user authentication',
            'description' => null,
        ]);
        $summary = [
            'steps' => [
                'task' => ['content' => 'Optimize database queries for performance'],
            ],
        ];

        $hint = $this->classifier->classify($summary, $job);

        $this->assertEquals(1, $hint?->type);
    }

    public function test_classifies_type_2_action_contradicts_task(): void
    {
        $job = AgentJob::factory()->make([
            'name' => 'Fix login bug',
            'description' => null,
        ]);
        $summary = [
            'steps' => [
                'task' => ['content' => 'Ensure users can login successfully'],
                'action' => ['content' => 'Instead of fixing login, I will refactor the entire auth system'],
            ],
        ];

        $hint = $this->classifier->classify($summary, $job);

        $this->assertEquals(2, $hint?->type);
    }

    public function test_returns_null_for_unclassifiable_failure(): void
    {
        $job = AgentJob::factory()->make([
            'name' => 'Generic task',
            'description' => null,
        ]);
        $summary = ['steps' => []];

        $hint = $this->classifier->classify($summary, $job);

        $this->assertNull($hint);
    }
}
