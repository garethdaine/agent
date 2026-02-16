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

    private function makeRun(User $user, string $status): AgentJobRun
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

        return AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => $status,
            'metadata_json' => [],
        ]);
    }
}
