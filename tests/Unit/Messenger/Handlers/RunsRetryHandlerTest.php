<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\RunsRetryHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsRetryHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_retries_failed_run(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->failed()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run);
        $handler = app(RunsRetryHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('run', $result->getData());
        $this->assertDatabaseHas('agent_job_runs', [
            'agent_job_id' => $job->id,
            'status' => AgentJobRun::STATUS_QUEUED,
        ]);
    }

    #[Test]
    public function it_retries_killed_run(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_KILLED,
        ]);

        $context = $this->createContext($user, $run);
        $handler = app(RunsRetryHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_fails_for_running_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run);
        $handler = app(RunsRetryHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
    }

    #[Test]
    public function it_fails_for_succeeded_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->succeeded()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run);
        $handler = app(RunsRetryHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
    }

    #[Test]
    public function it_sets_initiated_by_user(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->failed()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run);
        $handler = app(RunsRetryHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $newRun = AgentJobRun::where('id', '!=', $run->id)->latest('id')->first();
        $this->assertEquals($user->id, $newRun->initiated_by_user_id);
    }

    private function createContext(User $user, AgentJobRun $run): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: ['run_id' => $run->id],
            action: 'runs.retry',
            targetRun: $run
        );
    }
}
