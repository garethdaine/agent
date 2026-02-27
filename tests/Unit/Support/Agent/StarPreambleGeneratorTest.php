<?php

namespace Tests\Unit\Support\Agent;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Support\Agent\StarPreambleGenerator;
use App\Support\Compliance\LessonsManager;
use Mockery;
use Tests\TestCase;

class StarPreambleGeneratorTest extends TestCase
{
    private StarPreambleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $lessonsManager = Mockery::mock(LessonsManager::class);
        $lessonsManager->shouldReceive('queryLessons')->andReturn([]);
        $this->generator = new StarPreambleGenerator($lessonsManager);
    }

    public function test_generates_preamble_with_hardcoded_template(): void
    {
        $job = AgentJob::factory()->make();
        $run = AgentJobRun::factory()->make(['agent_job_id' => $job->id]);

        $preamble = $this->generator->generate($job, $run);

        $this->assertStringContainsString('## Pre-Execution Goal Articulation', $preamble);
        $this->assertStringContainsString('### SITUATION', $preamble);
        $this->assertStringContainsString('### TASK', $preamble);
        $this->assertStringContainsString('### ACTION', $preamble);
        $this->assertStringContainsString('### RESULT', $preamble);
    }

    public function test_respects_global_enabled_config(): void
    {
        config(['agent.star_preamble.enabled' => false]);
        $job = AgentJob::factory()->make(['star_preamble_enabled' => null]);

        $this->assertFalse($this->generator->isEnabled($job));
    }

    public function test_per_job_override_takes_precedence_over_global(): void
    {
        config(['agent.star_preamble.enabled' => false]);
        $job = AgentJob::factory()->make(['star_preamble_enabled' => true]);

        $this->assertTrue($this->generator->isEnabled($job));
    }

    public function test_ab_test_assignment_returns_treatment_or_control(): void
    {
        config(['agent.star_preamble.ab_test_enabled' => true]);
        config(['agent.star_preamble.ab_test_treatment_percent' => 50]);
        $job = AgentJob::factory()->make();

        $groups = [];
        for ($i = 0; $i < 100; $i++) {
            $groups[] = $this->generator->assignAbGroup($job);
        }

        $this->assertContains('treatment', $groups);
        $this->assertContains('control', $groups);
    }

    public function test_ab_test_disabled_returns_null_group(): void
    {
        config(['agent.star_preamble.ab_test_enabled' => false]);
        $job = AgentJob::factory()->make();

        $this->assertNull($this->generator->assignAbGroup($job));
    }

    public function test_lessons_enrichment_appends_filtered_lessons(): void
    {
        $lessonsManager = Mockery::mock(LessonsManager::class);
        $lessonsManager->shouldReceive('queryLessons')
            ->once()
            ->andReturn([['content' => 'Always verify outputs']]);

        $generator = new StarPreambleGenerator($lessonsManager);
        $job = AgentJob::factory()->make(['runner_type' => 'claude']);
        $run = AgentJobRun::factory()->make();

        $preamble = $generator->generate($job, $run);

        $this->assertStringContainsString('Always verify outputs', $preamble);
    }
}
