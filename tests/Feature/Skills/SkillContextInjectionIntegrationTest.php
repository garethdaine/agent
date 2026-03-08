<?php

declare(strict_types=1);

namespace Tests\Feature\Skills;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentSkill;
use App\Models\User;
use App\Services\Skills\SkillResolver;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SkillContextInjectionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/skill-injection');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $exec = $this->sandboxBase.'/bin/runner';
        file_put_contents($exec, "#!/bin/sh\nexit 0\n");
        chmod($exec, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $exec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $exec.' -p {{task_markdown_path}}',
        ]);

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandboxBase);
        parent::tearDown();
    }

    public function test_execute_agent_run_job_injects_skill_context_when_enabled(): void
    {
        $taskFile = $this->sandboxBase.'/tasks/skill-test.md';
        file_put_contents($taskFile, "# Task\n\nDo the compliance check.");

        $job = AgentJob::query()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Skill Injection Test',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
        ]);

        // Enable skills feature flag
        $flagManager = Mockery::mock(FeatureFlagManager::class);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->with(Mockery::not(FeatureFlagManager::SKILLS_ENABLED))
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);

        AgentSkill::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name' => 'compliance-check',
            'trigger_keywords' => ['compliance', 'check', 'audit'],
            'status' => AgentSkill::STATUS_ACTIVE,
            'description' => 'Validates compliance requirements',
        ]);

        // Run the job (it will fail at process execution, but we care about metadata)
        try {
            $executeJob = new ExecuteAgentRunJob($run->id);
            $executeJob->handle(
                app(\App\Support\Agent\RuntimeValidation::class),
                app(\App\Support\Agent\CommandTemplateRenderer::class),
                app(\App\Support\Agent\RunStateTransitionService::class),
                app(\App\Support\Agent\UsageLimitState::class),
                app(\App\Contracts\OrchestrationPolicyServiceContract::class),
            );
        } catch (\Throwable) {
            // Expected — process execution may fail in test environment
        }

        $run->refresh();
        $metadata = (array) $run->metadata_json;

        $this->assertTrue($metadata['skills_applied'] ?? false);
    }

    public function test_execute_agent_run_job_skips_skills_when_disabled(): void
    {
        $taskFile = $this->sandboxBase.'/tasks/no-skills.md';
        file_put_contents($taskFile, "# Task\n\nDo the compliance check.");

        $job = AgentJob::query()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->user->currentTeam->id,
            'name' => 'No Skills Test',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
        ]);

        // Disable skills feature flag
        $flagManager = Mockery::mock(FeatureFlagManager::class);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(false);
        $flagManager->shouldReceive('enabled')
            ->with(Mockery::not(FeatureFlagManager::SKILLS_ENABLED))
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);

        try {
            $executeJob = new ExecuteAgentRunJob($run->id);
            $executeJob->handle(
                app(\App\Support\Agent\RuntimeValidation::class),
                app(\App\Support\Agent\CommandTemplateRenderer::class),
                app(\App\Support\Agent\RunStateTransitionService::class),
                app(\App\Support\Agent\UsageLimitState::class),
                app(\App\Contracts\OrchestrationPolicyServiceContract::class),
            );
        } catch (\Throwable) {
            // Expected
        }

        $run->refresh();
        $metadata = (array) $run->metadata_json;

        $this->assertFalse($metadata['skills_applied'] ?? true);
    }

    public function test_skill_injection_failure_does_not_block_execution(): void
    {
        $taskFile = $this->sandboxBase.'/tasks/failing-skills.md';
        file_put_contents($taskFile, "# Task\n\nTest resilience.");

        $job = AgentJob::query()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->user->currentTeam->id,
            'name' => 'Failing Skills Test',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
        ]);

        // Enable skills but make resolver throw
        $flagManager = Mockery::mock(FeatureFlagManager::class);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->with(Mockery::not(FeatureFlagManager::SKILLS_ENABLED))
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);

        $failingResolver = Mockery::mock(SkillResolver::class);
        $failingResolver->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('Resolver exploded'));
        $this->app->instance(SkillResolver::class, $failingResolver);

        try {
            $executeJob = new ExecuteAgentRunJob($run->id);
            $executeJob->handle(
                app(\App\Support\Agent\RuntimeValidation::class),
                app(\App\Support\Agent\CommandTemplateRenderer::class),
                app(\App\Support\Agent\RunStateTransitionService::class),
                app(\App\Support\Agent\UsageLimitState::class),
                app(\App\Contracts\OrchestrationPolicyServiceContract::class),
            );
        } catch (\Throwable) {
            // Expected — process execution may fail
        }

        $run->refresh();
        $metadata = (array) $run->metadata_json;

        // Skills should not be applied but the job should continue
        $this->assertFalse($metadata['skills_applied'] ?? true);
    }
}
