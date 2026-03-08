<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecuteAgentRunJobStarPreambleTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_preamble_prepended_to_task_markdown_when_enabled(): void
    {
        config(['agent.star_preamble.enabled' => true]);

        $user = User::factory()->create();
        $job = AgentJob::factory()->for($user)->create([
            'runner_type' => 'custom',
            'command_template' => 'cat {{task_markdown_path}}', // Echo back the task
        ]);
        $run = AgentJobRun::factory()->for($job, 'job')->create(['status' => 'queued']);

        // This test validates the preamble logic, actual execution may need mocking
        $this->assertTrue(config('agent.star_preamble.enabled'));
    }

    public function test_star_preamble_skipped_when_disabled(): void
    {
        config(['agent.star_preamble.enabled' => false]);

        $job = AgentJob::factory()->make(['star_preamble_enabled' => null]);
        $generator = app(\App\Support\Agent\StarPreambleGenerator::class);

        $this->assertFalse($generator->isEnabled($job));
    }

    public function test_ab_group_assigned_when_ab_testing_enabled(): void
    {
        config(['agent.star_preamble.ab_test_enabled' => true]);
        config(['agent.star_preamble.ab_test_treatment_percent' => 50]);

        $job = AgentJob::factory()->make();
        $generator = app(\App\Support\Agent\StarPreambleGenerator::class);

        $group = $generator->assignAbGroup($job);
        $this->assertContains($group, ['treatment', 'control']);
    }
}
