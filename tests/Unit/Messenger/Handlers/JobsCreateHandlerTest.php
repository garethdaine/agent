<?php

namespace Tests\Unit\Messenger\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\JobsCreateHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobsCreateHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_job_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $context = $this->createContext($user, [
            'name' => 'Test Job',
            'description' => 'A test job',
        ]);
        $handler = app(JobsCreateHandler::class);

        $result = $handler->handle($context);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('agent_jobs', [
            'user_id' => $user->id,
            'name' => 'Test Job',
        ]);
    }

    #[Test]
    public function it_requires_job_name(): void
    {
        $user = User::factory()->create();
        $context = $this->createContext($user, ['description' => 'No name']);
        $handler = app(JobsCreateHandler::class);

        $result = $handler->handle($context);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('name', strtolower($result->getMessage()));
    }

    private function createContext(User $user, array $params): ChatActionContext
    {
        return new ChatActionContext(
            user: $user,
            parameters: $params,
            action: 'jobs.create'
        );
    }
}
