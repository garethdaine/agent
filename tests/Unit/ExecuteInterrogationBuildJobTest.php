<?php

namespace Tests\Unit;

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

class ExecuteInterrogationBuildJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_starts_next_pending_task_and_sets_active_run_pointers(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Run task',
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

        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $task->status);
        $this->assertSame((int) $run->id, (int) $task->agent_job_run_id);
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame('running', data_get($session->metadata_json, 'build.status'));
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));

        Queue::assertPushed(ExecuteInterrogationBuildJob::class);
    }

    public function test_job_marks_build_completed_after_terminal_successful_task(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Complete task',
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

        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame('completed', data_get($session->metadata_json, 'build.status'));
    }

    public function test_job_preserves_failed_permission_blocker_context_for_build_retry_and_visibility(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'error_code' => 'PERMISSION_REQUIRED',
            'error_summary' => 'Write permissions were denied for this task.',
            'metadata_json' => [
                'permission_blocker_detected' => true,
                'permission_blocker_excerpt' => 'All file write operations are denied by the permission system.',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Blocked task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_FAILED, (string) $task->status);
        $this->assertSame('Write permissions were denied for this task.', (string) $task->last_error);
        $this->assertSame('failed', data_get($session->metadata_json, 'build.status'));
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.permission_required'));
        $this->assertSame(
            'All file write operations are denied by the permission system.',
            data_get($session->metadata_json, 'build.permission_excerpt')
        );
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
    }

    public function test_job_pauses_build_when_run_requests_clarification_even_if_run_succeeded(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, [
            'status' => 'running',
        ]);

        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'clarification_required' => true,
                'clarification_excerpt' => 'Could you clarify whether we should use existing event names?',
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Clarify task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $factory = $this->mock(BuildTaskRunFactory::class);
        $factory->shouldReceive('create')->never();

        $job = new ExecuteInterrogationBuildJob((int) $session->id);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_BLOCKED, (string) $task->status);
        $this->assertSame('paused', data_get($session->metadata_json, 'build.status'));
        $this->assertSame('clarification', data_get($session->metadata_json, 'build.pause_reason'));
        $this->assertTrue((bool) data_get($session->metadata_json, 'build.clarification_required'));
        $this->assertSame(
            'Could you clarify whether we should use existing event names?',
            data_get($session->metadata_json, 'build.clarification_excerpt')
        );
        $this->assertSame($task->id, data_get($session->metadata_json, 'build.active_task_id'));
        $this->assertSame($run->id, data_get($session->metadata_json, 'build.active_run_id'));
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function makeSession(User $user, array $build): InterrogationSession
    {
        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'phase' => InterrogationSession::PHASE_PLANNING,
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
            'name' => 'Synthetic build run job',
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
