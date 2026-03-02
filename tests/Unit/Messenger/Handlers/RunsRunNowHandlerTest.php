<?php

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\RunsRunNowHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsRunNowHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_new_run_and_queues_execution(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $context = $this->createContext($user, $job);
        $handler = app(RunsRunNowHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('run', $result->getData());
        $this->assertDatabaseHas('agent_job_runs', [
            'agent_job_id' => $job->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);
    }

    #[Test]
    public function it_fails_without_target_job(): void
    {
        $user = User::factory()->create();

        $context = new ChatActionContext(
            user: $user,
            parameters: [],
            action: 'runs.run-now'
        );
        $handler = app(RunsRunNowHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
    }

    #[Test]
    public function it_sets_initiated_by_user(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $context = $this->createContext($user, $job);
        $handler = app(RunsRunNowHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $run = AgentJobRun::latest('id')->first();
        $this->assertEquals($user->id, $run->initiated_by_user_id);
    }

    private function createContext(User $user, AgentJob $job): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: ['job_id' => $job->id],
            action: 'runs.run-now',
            targetJob: $job
        );
    }
}
