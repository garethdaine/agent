<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Messenger\SlashCommands\RunsCommandHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /runs slash command handler (agent job runs).
 */
final class RunsCommandTest extends TestCase
{
    use RefreshDatabase;

    private RunsCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(RunsCommandHandler::class);
    }

    #[Test]
    public function runs_active_lists_active_job_runs(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id, 'name' => 'Test Job']);
        AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_RUNNING,
        ]);

        $result = $this->handler->handle($user, ['active']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active runs', $result->message);
        $this->assertStringContainsString('Test Job', $result->message);
    }

    #[Test]
    public function runs_active_shows_no_runs_when_empty(): void
    {
        $user = User::factory()->create();

        $result = $this->handler->handle($user, ['active']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No active runs', $result->message);
    }

    #[Test]
    public function runs_without_subcommand_returns_usage(): void
    {
        $user = User::factory()->create();

        $result = $this->handler->handle($user, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage', $result->message);
    }

    #[Test]
    public function runs_active_excludes_other_users_runs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherJob = AgentJob::factory()->create(['user_id' => $otherUser->id]);
        AgentJobRun::factory()->create([
            'agent_job_id' => $otherJob->id,
            'user_id' => $otherUser->id,
            'status' => AgentJobRun::STATUS_RUNNING,
        ]);

        $result = $this->handler->handle($user, ['active']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No active runs', $result->message);
    }
}
