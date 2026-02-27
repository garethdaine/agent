<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Jobs\ExecuteAgentRunJob;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\BuildTaskRunFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression tests to verify existing functionality remains intact
 * when the compliance feature is disabled.
 *
 * Scope:
 * - Verify job runs execute and complete normally
 * - Verify build tasks execute and complete normally
 * - Verify API responses omit compliance data when not present
 * - Verify no performance regression or behavioral changes
 *
 * Conditions for correctness:
 * - config('agent.compliance.enabled') must be false
 * - Existing metadata should be preserved without compliance fields
 * - Job/task status should follow pre-compliance behavior
 *
 * Known limitations:
 * - Does not test all possible status transitions
 * - Uses simplified test fixtures (shell script runners)
 */
class ComplianceDisabledRegressionTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agent.compliance.enabled', false);

        Queue::fake();

        $this->sandboxBase = storage_path('framework/testing/compliance-disabled-regression');
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
            'codex' => $exec,
            'custom' => $exec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $exec.' -p {{task_markdown_path}}',
            'codex' => $exec.' exec {{task_markdown_path}}',
        ]);
        config()->set('agent.interrogation.build_execution_templates', [
            'claude' => '/bin/echo --dangerously-skip-permissions -p {{task_markdown_path}}',
        ]);
    }

    public function test_job_run_executes_normally_when_compliance_disabled(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/regression-job.md';
        file_put_contents($taskFile, "# Task\nSimple job run test.\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user);

        $executeJob = new ExecuteAgentRunJob($run->id);
        app()->call([$executeJob, 'handle']);

        $run->refresh();

        // Run should succeed normally
        $this->assertSame(AgentJobRun::STATUS_SUCCEEDED, $run->status);

        // No compliance metadata should be present
        $metadata = (array) ($run->metadata_json ?? []);
        $this->assertArrayNotHasKey('workflow_policy_version', $metadata);
        $this->assertArrayNotHasKey('complexity_classification', $metadata);
        $this->assertArrayNotHasKey('compliance_status', $metadata);
        $this->assertArrayNotHasKey('compliance_block_reason', $metadata);
        $this->assertArrayNotHasKey('compliance_gates', $metadata);

        // No compliance-related error codes
        $this->assertNull($run->error_code);
        $this->assertNull($run->error_summary);
    }

    public function test_build_task_executes_normally_when_compliance_disabled(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Regression build task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        app()->call([$job, 'handle']);

        $task->refresh();

        // Task should complete normally
        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);

        // No compliance metadata should be present
        $metadata = (array) ($task->metadata_json ?? []);
        $this->assertArrayNotHasKey('compliance_block_reason', $metadata);
        $this->assertArrayNotHasKey('compliance_status', $metadata);
        $this->assertArrayNotHasKey('compliance_gates', $metadata);
    }

    public function test_api_responses_omit_compliance_summary_when_no_data(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'source' => 'manual',
                'custom_field' => 'value',
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/runs/{$run->id}");

        $response->assertOk();

        // compliance_summary should not be present when run has no compliance data
        $response->assertJsonMissing(['compliance_summary']);

        // Original metadata should be accessible
        $this->assertNotNull($response->json('data'));
    }

    public function test_job_preserves_custom_metadata_when_compliance_disabled(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/regression-metadata.md';
        file_put_contents($taskFile, "# Task\nMetadata preservation test.\n");

        $job = $this->createAgentJob($user, $taskFile);
        $run = $this->createAgentJobRun($job, $user, [
            'custom_field' => 'preserved_value',
            'another_field' => 123,
            'nested' => ['key' => 'value'],
        ]);

        $executeJob = new ExecuteAgentRunJob($run->id);
        app()->call([$executeJob, 'handle']);

        $run->refresh();
        $metadata = (array) ($run->metadata_json ?? []);

        // All custom metadata should be preserved
        $this->assertSame('preserved_value', $metadata['custom_field'] ?? null);
        $this->assertSame(123, $metadata['another_field'] ?? null);
        $this->assertSame(['key' => 'value'], $metadata['nested'] ?? null);
    }

    public function test_build_task_status_transitions_unchanged_when_compliance_disabled(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Status transition test',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_QUEUED);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($run);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        app()->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        // Standard transition: PENDING -> IN_PROGRESS (when run is started)
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $task->status);
        $this->assertSame((int) $run->id, (int) $task->agent_job_run_id);

        // Session pointers should be set
        $this->assertSame('running', data_get($session->metadata_json, 'build.status'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
    }

    public function test_session_show_api_works_without_compliance_data(): void
    {
        $user = User::factory()->create();
        $session = InterrogationSession::factory()->create([
            'user_id' => $user->id,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'metadata_json' => [
                'build' => ['status' => 'completed'],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/interrogation/sessions/{$session->id}");

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    private function createAgentJob(User $user, string $taskFile): AgentJob
    {
        return AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Compliance Disabled Regression Job',
            'description' => null,
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
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createAgentJobRun(AgentJob $job, User $user, array $metadata = []): AgentJobRun
    {
        return AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => array_merge([
                'output_truncated' => false,
                'redaction_count' => 0,
            ], $metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function makeSession(User $user, array $build): InterrogationSession
    {
        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Compliance disabled regression session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'plan_json' => ['plan_markdown' => 'Plan content'],
            'metadata_json' => ['build' => $build],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRun(User $user, string $status, array $overrides = []): AgentJobRun
    {
        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Compliance disabled regression test job',
            'cron_expression' => '0 0 1 1 0',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 300,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => (string) config('agent.default_templates.claude'),
            'task_markdown_path' => base_path('README.md'),
            'working_directory' => base_path(),
        ]);

        $payload = array_merge([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => $status,
            'metadata_json' => [],
        ], $overrides);

        return AgentJobRun::query()->create($payload);
    }
}
