<?php

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\JobsListHandler;
use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobsListHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_users_jobs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        AgentJob::factory()->count(3)->create(['user_id' => $user->id]);
        AgentJob::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $context = $this->createContext($user);
        $handler = app(JobsListHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->getData()['jobs']);
    }

    #[Test]
    public function it_returns_empty_list_when_no_jobs(): void
    {
        $user = User::factory()->create();
        $context = $this->createContext($user);
        $handler = app(JobsListHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $result->getData()['jobs']);
    }

    private function createContext(User $user): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: [],
            action: 'jobs.list'
        );
    }
}
