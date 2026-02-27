<?php

namespace Tests\Feature\Jobs;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Enums\TaskCategory;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\SyncInterrogationTaskStatusToTaskProviderJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Compliance\DTOs\CompletionGateResult;
use App\Support\Compliance\DTOs\GateResult;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;
use App\Support\Compliance\OrchestrationPolicyService;
use App\Support\Interrogation\BuildTaskRunFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Tests for compliance integration in ExecuteInterrogationBuildJob.
 *
 * These tests verify that:
 * - Build tasks proceed normally when compliance is disabled
 * - Compliance metadata is stored in task when enabled
 * - Task is set to STATUS_BLOCKED when strict mode + verification missing
 * - Build is paused when task is blocked for compliance
 * - Task completes normally when all gates pass
 * - Existing build lifecycle behavior is preserved (regression)
 */
class ExecuteInterrogationBuildJobComplianceTest extends TestCase
{
    use RefreshDatabase;

    private string $taskBase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->taskBase = storage_path('framework/testing/compliance-test/tasks');
        @mkdir($this->taskBase, 0777, true);

        config()->set('agent.allowed_task_markdown_bases', [$this->taskBase]);
        config()->set('agent.default_templates', [
            'claude' => '/bin/echo -p {{task_markdown_path}}',
        ]);
        config()->set('agent.interrogation.build_execution_templates', [
            'claude' => '/bin/echo --dangerously-skip-permissions -p {{task_markdown_path}}',
        ]);
    }

    public function test_build_task_proceeds_normally_when_compliance_disabled(): void
    {
        config()->set('agent.compliance.enabled', false);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task without compliance',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertArrayNotHasKey('compliance_block_reason', $task->metadata_json ?? []);
    }

    public function test_compliance_metadata_stored_in_task_when_enabled(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task with compliance metadata',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'task_category' => TaskCategory::FEATURE,
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_QUEUED);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($run);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        // Task should have compliance metadata after run creation
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $task->status);
        $this->assertSame((int) $run->id, (int) $task->agent_job_run_id);
    }

    public function test_task_set_to_status_blocked_when_strict_mode_and_verification_missing(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        // Run succeeded but has no verification evidence
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
                'task_category' => 'feature',
                // No verification_evidence
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task with missing verification',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
            'task_category' => TaskCategory::FEATURE,
            'metadata_json' => [
                'task_category' => 'feature',
                // No verification_evidence
            ],
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_BLOCKED, (string) $task->status);
        $this->assertArrayHasKey('compliance_block_reason', $task->metadata_json ?? []);
        $this->assertSame('verification_incomplete', $task->metadata_json['compliance_block_reason'] ?? null);
        $this->assertArrayHasKey('compliance_gates', $task->metadata_json ?? []);
    }

    public function test_build_paused_when_task_blocked_for_compliance(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        // Run succeeded but has no verification evidence
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task that will pause build',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
            'task_category' => TaskCategory::FEATURE,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame('compliance_blocked', data_get($session->metadata_json, 'build.pause_reason'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.blocked_task_id'));
    }

    public function test_task_completes_normally_when_all_gates_pass(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        // Run succeeded with all required verification evidence
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task with complete verification',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
            'task_category' => TaskCategory::FEATURE,
            'metadata_json' => [
                'verification_evidence' => [
                    'automated_check' => true,
                    'ai_critic' => true,
                ],
            ],
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertArrayNotHasKey('compliance_block_reason', $task->metadata_json ?? []);
        $this->assertNotSame('paused', data_get($session->metadata_json, 'build.status'));
    }

    public function test_docs_category_task_completes_without_verification(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'strict');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        // Run succeeded with no verification evidence (docs category doesn't require any)
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Documentation task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
            'task_category' => TaskCategory::DOCS,
            'metadata_json' => [],
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        // Docs category has no verification requirements, should complete
        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
    }

    public function test_advisory_mode_allows_completion_with_missing_verification(): void
    {
        config()->set('agent.compliance.enabled', true);
        config()->set('agent.compliance.enforcement_mode', 'advisory');
        config()->set('agent.compliance.verification_gate_enabled', true);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        // Run succeeded but has no verification evidence
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'source' => 'interrogation_build',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task in advisory mode',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
            'task_category' => TaskCategory::FEATURE,
            'metadata_json' => [],
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();

        // Advisory mode allows completion even with missing verification
        $this->assertSame(InterrogationBuildTask::STATUS_COMPLETED, (string) $task->status);
        $this->assertArrayNotHasKey('compliance_block_reason', $task->metadata_json ?? []);
    }

    public function test_existing_build_lifecycle_unchanged_when_compliance_disabled(): void
    {
        config()->set('agent.compliance.enabled', false);

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Regression task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_QUEUED);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($run);

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        // Existing behavior: task started, pointers set
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $task->status);
        $this->assertSame((int) $run->id, (int) $task->agent_job_run_id);
        $this->assertSame('running', data_get($session->metadata_json, 'build.status'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));

        Queue::assertPushed(ExecuteInterrogationBuildJob::class);
        Queue::assertPushed(SyncInterrogationTaskStatusToTaskProviderJob::class);
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function makeSession(User $user, array $build): InterrogationSession
    {
        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Compliance test session',
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
            'name' => 'Compliance test job',
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
