<?php

namespace Tests\Feature\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\AgentJobRunFinished;
use App\Listeners\Messenger\SendAgentJobFinishedNotification;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendAgentJobFinishedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_sends_notification_on_failed_run(): void
    {
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $this->job->id,
            'user_id' => $this->user->id,
            'status' => 'failed',
            'error_summary' => 'Process crashed',
            'error_code' => 'EXECUTION_ERROR',
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->type === 'agent.job_failed'
                    && $payload->severity === SystemNotificationPayload::SEVERITY_ERROR
                    && $payload->userId === $this->user->id
                    && str_contains($payload->title, 'failed');
            });

        $listener = new SendAgentJobFinishedNotification($dispatcher);
        $listener->handle(new AgentJobRunFinished($run, 'failed'));
    }

    public function test_sends_notification_on_timed_out_run(): void
    {
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $this->job->id,
            'user_id' => $this->user->id,
            'status' => 'timed_out',
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return str_contains($payload->title, 'timed_out');
            });

        $listener = new SendAgentJobFinishedNotification($dispatcher);
        $listener->handle(new AgentJobRunFinished($run, 'timed_out'));
    }

    public function test_skips_succeeded_runs(): void
    {
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $this->job->id,
            'user_id' => $this->user->id,
            'status' => 'succeeded',
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $listener = new SendAgentJobFinishedNotification($dispatcher);
        $listener->handle(new AgentJobRunFinished($run, 'succeeded'));
    }

    public function test_sends_notification_on_killed_run(): void
    {
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $this->job->id,
            'user_id' => $this->user->id,
            'status' => 'killed',
        ]);

        $dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return str_contains($payload->title, 'killed');
            });

        $listener = new SendAgentJobFinishedNotification($dispatcher);
        $listener->handle(new AgentJobRunFinished($run, 'killed'));
    }
}
