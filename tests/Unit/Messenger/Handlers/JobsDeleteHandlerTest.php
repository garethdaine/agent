<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\JobsDeleteHandler;
use App\Models\AgentJob;
use App\Models\User;
use App\Models\UserChatPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobsDeleteHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_job_when_confirmation_not_required(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create([
            'user_id' => $user->id,
            'require_confirmation_for_delete' => false,
        ]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $context = $this->createContext($user, $job);
        $handler = app(JobsDeleteHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSoftDeleted('agent_jobs', ['id' => $job->id]);
    }

    #[Test]
    public function it_requests_confirmation_when_required(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create([
            'user_id' => $user->id,
            'require_confirmation_for_delete' => true,
        ]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $context = $this->createContext($user, $job, confirmed: false);
        $handler = app(JobsDeleteHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->requiresConfirmation());
        $this->assertDatabaseHas('agent_jobs', ['id' => $job->id]);
    }

    #[Test]
    public function it_deletes_when_confirmation_provided(): void
    {
        $user = User::factory()->create();
        UserChatPreference::create([
            'user_id' => $user->id,
            'require_confirmation_for_delete' => true,
        ]);
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $context = $this->createContext($user, $job, confirmed: true);
        $handler = app(JobsDeleteHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSoftDeleted('agent_jobs', ['id' => $job->id]);
    }

    private function createContext(User $user, AgentJob $job, bool $confirmed = false): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: ['job_id' => $job->id],
            action: 'jobs.delete',
            targetJob: $job,
            confirmed: $confirmed
        );
    }
}
