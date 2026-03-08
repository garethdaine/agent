<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\AgentJobRunFinished;
use App\Jobs\ExecuteInterrogationBuildJob;
use App\Listeners\DispatchBuildTickOnRunFinished;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchBuildTickOnRunFinishedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_tick_when_build_is_running_even_if_task_already_completed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'running']);
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'interrogation_build_task_id' => 999,
                'interrogation_session_id' => $session->id,
            ],
        ]);

        // Task has already been finalized by the self-sustaining loop.
        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Already completed',
            'status' => InterrogationBuildTask::STATUS_COMPLETED,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $event = new AgentJobRunFinished($run, AgentJobRun::STATUS_SUCCEEDED);
        $listener = new DispatchBuildTickOnRunFinished;
        $listener->handle($event);

        Queue::assertPushed(ExecuteInterrogationBuildJob::class, function (ExecuteInterrogationBuildJob $job) use ($session): bool {
            return $job->sessionId === (int) $session->id;
        });
    }

    public function test_does_not_dispatch_when_build_is_not_running(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'completed']);
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'interrogation_build_task_id' => 999,
                'interrogation_session_id' => $session->id,
            ],
        ]);

        $event = new AgentJobRunFinished($run, AgentJobRun::STATUS_SUCCEEDED);
        $listener = new DispatchBuildTickOnRunFinished;
        $listener->handle($event);

        Queue::assertNotPushed(ExecuteInterrogationBuildJob::class);
    }

    public function test_does_not_dispatch_when_run_has_no_build_metadata(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [],
        ]);

        $event = new AgentJobRunFinished($run, AgentJobRun::STATUS_SUCCEEDED);
        $listener = new DispatchBuildTickOnRunFinished;
        $listener->handle($event);

        Queue::assertNotPushed(ExecuteInterrogationBuildJob::class);
    }

    public function test_does_not_dispatch_when_session_does_not_exist(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $run = $this->makeRun($user, AgentJobRun::STATUS_SUCCEEDED, [
            'metadata_json' => [
                'interrogation_build_task_id' => 999,
                'interrogation_session_id' => 999999,
            ],
        ]);

        $event = new AgentJobRunFinished($run, AgentJobRun::STATUS_SUCCEEDED);
        $listener = new DispatchBuildTickOnRunFinished;
        $listener->handle($event);

        Queue::assertNotPushed(ExecuteInterrogationBuildJob::class);
    }

    public function test_does_not_dispatch_when_build_is_paused(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = $this->makeSession($user, ['status' => 'paused', 'pause_reason' => 'rate_limit']);
        $run = $this->makeRun($user, AgentJobRun::STATUS_FAILED, [
            'metadata_json' => [
                'interrogation_build_task_id' => 999,
                'interrogation_session_id' => $session->id,
            ],
        ]);

        $event = new AgentJobRunFinished($run, AgentJobRun::STATUS_FAILED);
        $listener = new DispatchBuildTickOnRunFinished;
        $listener->handle($event);

        Queue::assertNotPushed(ExecuteInterrogationBuildJob::class);
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
