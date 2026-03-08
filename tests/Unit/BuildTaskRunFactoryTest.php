<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\BuildTaskRunFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BuildTaskRunFactoryTest extends TestCase
{
    use RefreshDatabase;

    private string $taskBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskBase = storage_path('framework/testing/build-task-run-factory/tasks');
        @mkdir($this->taskBase, 0777, true);

        config()->set('agent.allowed_task_markdown_bases', [$this->taskBase]);
        config()->set('agent.default_templates', [
            'claude' => '/bin/echo -p {{task_markdown_path}}',
            'codex' => '/bin/echo exec {{task_markdown_path}}',
        ]);
        config()->set('agent.interrogation.build_execution_templates', [
            'claude' => '/bin/echo --dangerously-skip-permissions -p {{task_markdown_path}}',
            'codex' => '/bin/echo --dangerously-bypass-approvals-and-sandbox exec {{task_markdown_path}}',
        ]);
    }

    public function test_factory_reuses_existing_job_for_same_task_across_retries(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user);
        $task = $this->makeTask($session, 1);

        $factory = app(BuildTaskRunFactory::class);

        $runOne = $factory->create($session, $task);
        $runTwo = $factory->create($session, $task);

        $this->assertSame((int) $runOne->agent_job_id, (int) $runTwo->agent_job_id);
        $this->assertSame(1, AgentJob::query()->count());

        $job = AgentJob::query()->firstOrFail();
        $this->assertSame(sprintf('Interrogation Build S%d T01', (int) $session->id), (string) $job->name);

        // Interrogation-specific metadata keys stored in env_json
        $this->assertSame('interrogation_build', data_get($job->env_json, 'AGENT_JOB_SOURCE'));
        $this->assertSame((string) $session->id, data_get($job->env_json, 'INTERROGATION_SESSION_ID'));
        $this->assertSame((string) $task->id, data_get($job->env_json, 'INTERROGATION_BUILD_TASK_ID'));
        $this->assertSame('1', data_get($job->env_json, 'INTERROGATION_DB_ISOLATED'));
        $this->assertStringContainsString(
            '/storage/framework/interrogation-build/session-'.$session->id.'.sqlite',
            (string) data_get($job->env_json, 'INTERROGATION_BUILD_DB_PATH')
        );
        $this->assertFileExists((string) data_get($job->env_json, 'INTERROGATION_BUILD_DB_PATH'));
        $this->assertSame('array', data_get($job->env_json, 'CACHE_STORE'));
        $this->assertSame('array', data_get($job->env_json, 'SESSION_DRIVER'));

        // Database isolation keys must NOT be in env_json (they are forbidden by
        // EnvPolicy and instead injected at execution time by
        // DatabaseIsolationEnvironment::safeTestingDatabaseVars())
        $this->assertNull(data_get($job->env_json, 'DB_CONNECTION'));
        $this->assertNull(data_get($job->env_json, 'DB_DATABASE'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_HOST'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_PORT'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_DATABASE'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_USERNAME'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_PASSWORD'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_CHARSET'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_SEARCH_PATH'));
        $this->assertNull(data_get($job->env_json, 'TEST_DB_SSLMODE'));

        $markdown = @file_get_contents((string) $job->task_markdown_path);
        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Safety Constraints', $markdown);
        $this->assertStringContainsString('Never run destructive database commands', $markdown);

        Queue::assertPushed(ExecuteAgentRunJob::class, 2);
    }

    public function test_factory_reuses_legacy_timestamped_job_name_and_normalizes_it(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user);
        $task = $this->makeTask($session, 2);

        $legacy = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Interrogation Build S%d T%02d 20260217123456', (int) $session->id, (int) $task->sequence),
            'description' => 'legacy',
            'cron_expression' => '0 0 1 1 0',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => '/bin/echo -p {{task_markdown_path}}',
            'task_markdown_path' => base_path('README.md'),
            'working_directory' => base_path(),
            'env_json' => [
                'INTERROGATION_SESSION_ID' => (string) $session->id,
                'INTERROGATION_BUILD_TASK_ID' => (string) $task->id,
            ],
        ]);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $legacy->refresh();

        $this->assertSame((int) $legacy->id, (int) $run->agent_job_id);
        $this->assertSame(sprintf('Interrogation Build S%d T%02d', (int) $session->id, (int) $task->sequence), (string) $legacy->name);
        $this->assertSame('interrogation_build', data_get($legacy->env_json, 'AGENT_JOB_SOURCE'));
        $this->assertSame(1, AgentJob::query()->count());
    }

    public function test_factory_includes_project_rules_and_test_first_workflow_in_task_markdown(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'build' => [
                'status' => 'ready',
                'project_rules' => [
                    [
                        'title' => 'Architecture Constraints',
                        'markdown' => "- Keep service boundaries explicit\n- Avoid hidden side effects",
                        'source' => 'manual',
                    ],
                ],
            ],
        ]);
        $task = $this->makeTask($session, 1);

        $factory = app(BuildTaskRunFactory::class);
        $run = $factory->create($session, $task);

        $job = AgentJob::query()->findOrFail((int) $run->agent_job_id);
        $markdown = @file_get_contents((string) $job->task_markdown_path);

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Runner Mode', $markdown);
        $this->assertStringContainsString('non-interactive and must complete end-to-end', $markdown);
        $this->assertStringContainsString('Do not pause for user check-ins', $markdown);
        $this->assertStringContainsString('## Mandatory Workflow', $markdown);
        $this->assertStringContainsString('Write or update tests first', $markdown);
        $this->assertStringContainsString('run tests with `php artisan test` (or `composer test` when full preflight is needed)', $markdown);
        $this->assertStringContainsString('DB_CONNECTION=pgsql_testing', $markdown);
        $this->assertStringContainsString('Do not emit generic PostgreSQL disclaimers', $markdown);
        $this->assertStringContainsString('## Code Field Rules', $markdown);
        $this->assertStringContainsString('Do not write code before stating assumptions', $markdown);
        $this->assertStringContainsString('## Project Rules Context', $markdown);
        $this->assertStringContainsString('Architecture Constraints', $markdown);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function makeSession(User $user, array $metadata = [], string $runnerType = 'claude'): InterrogationSession
    {
        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build Session',
            'runner_type' => $runnerType,
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'summary_json' => ['summary_markdown' => 'Summary'],
            'plan_json' => ['plan_markdown' => 'Plan'],
            'metadata_json' => $metadata !== [] ? $metadata : ['build' => ['status' => 'running']],
        ]);
    }

    private function makeTask(InterrogationSession $session, int $sequence): InterrogationBuildTask
    {
        return InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => $sequence,
            'title' => 'Build task '.$sequence,
            'description' => 'Task description',
            'instructions_markdown' => 'Implement changes.',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);
    }
}
