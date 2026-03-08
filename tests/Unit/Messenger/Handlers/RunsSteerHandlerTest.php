<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\RunsSteerHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsSteerHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_mutable_parameters(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
            'max_runtime_seconds' => 30,
        ]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, ['max_runtime_seconds' => 60]);
        $handler = app(RunsSteerHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_filters_credential_fields(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, [
            'max_runtime_seconds' => 60,
            'api_key' => 'should-be-filtered',
            'secret' => 'should-be-filtered',
            'token' => 'should-be-filtered',
        ]);
        $handler = app(RunsSteerHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertNotContains('api_key', $result->getData()['updated_fields'] ?? []);
        $this->assertNotContains('secret', $result->getData()['updated_fields'] ?? []);
        $this->assertNotContains('token', $result->getData()['updated_fields'] ?? []);
    }

    #[Test]
    public function it_fails_for_non_running_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->succeeded()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = $this->createContext($user, $run, ['max_runtime_seconds' => 60]);
        $handler = app(RunsSteerHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
    }

    #[Test]
    public function it_requires_confirmation_when_preference_enabled(): void
    {
        $user = User::factory()->create();
        \App\Models\UserChatPreference::create(['user_id' => $user->id, 'require_confirmation_for_steer' => true]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->running()->create(['agent_job_id' => $job->id, 'user_id' => $user->id]);

        $context = new ChatActionContext(
            user: $user,
            parameters: ['run_id' => $run->id, 'max_runtime_seconds' => 60],
            action: 'runs.steer',
            targetRun: $run,
            confirmed: false
        );
        $handler = app(RunsSteerHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->requiresConfirmation());
    }

    private function createContext(User $user, AgentJobRun $run, array $params): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: array_merge(['run_id' => $run->id], $params),
            action: 'runs.steer',
            targetRun: $run,
            confirmed: true
        );
    }
}
