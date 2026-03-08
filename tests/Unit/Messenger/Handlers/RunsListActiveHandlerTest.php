<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\RunsListActiveHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsListActiveHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_active_runs_for_user(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);
        AgentJobRun::factory()->create(['agent_job_id' => $job->id, 'user_id' => $user->id, 'status' => AgentJobRun::STATUS_QUEUED]);
        AgentJobRun::factory()->succeeded()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user);
        $handler = app(RunsListActiveHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getData()['runs']); // running + queued
    }

    #[Test]
    public function it_excludes_other_users_runs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userJob = AgentJob::factory()->create(['user_id' => $user->id]);
        $otherJob = AgentJob::factory()->create(['user_id' => $otherUser->id]);

        AgentJobRun::factory()->running()->create(['agent_job_id' => $userJob->id, 'user_id' => $user->id]);
        AgentJobRun::factory()->running()->create(['agent_job_id' => $otherJob->id, 'user_id' => $otherUser->id]);

        $context = $this->createContext($user);
        $handler = app(RunsListActiveHandler::class);

        $result = $handler->handle($context);

        $this->assertCount(1, $result->getData()['runs']);
    }

    #[Test]
    public function it_returns_empty_when_no_active_runs(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        AgentJobRun::factory()->succeeded()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user);
        $handler = app(RunsListActiveHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $result->getData()['runs']);
    }

    private function createContext(User $user): ChatActionContext
    {
        return new ChatActionContext(user: $user, parameters: [], action: 'runs.list-active');
    }
}
