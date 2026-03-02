<?php

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\RunsStopHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Models\UserChatPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsStopHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stops_running_run(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create(['user_id' => $user->id, 'require_confirmation_for_stop' => false]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, confirmed: true);
        $handler = app(RunsStopHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(AgentJobRun::STATUS_KILLED, $run->fresh()->status);
    }

    #[Test]
    public function it_fails_for_completed_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->succeeded()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, confirmed: true);
        $handler = app(RunsStopHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
    }

    #[Test]
    public function it_requires_confirmation_when_preference_enabled(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create(['user_id' => $user->id, 'require_confirmation_for_stop' => true]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, confirmed: false);
        $handler = app(RunsStopHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->requiresConfirmation());
        $this->assertEquals(AgentJobRun::STATUS_RUNNING, $run->fresh()->status);
    }

    #[Test]
    public function it_stops_when_confirmed(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create(['user_id' => $user->id, 'require_confirmation_for_stop' => true]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, confirmed: true);
        $handler = app(RunsStopHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(AgentJobRun::STATUS_KILLED, $run->fresh()->status);
    }

    private function createContext(User $user, AgentJobRun $run, bool $confirmed = true): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: ['run_id' => $run->id],
            action: 'runs.stop',
            targetRun: $run,
            confirmed: $confirmed
        );
    }
}
