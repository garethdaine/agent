<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Messenger\CommandRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the CommandRouter's route method.
 */
final class CommandRouterRouteTest extends TestCase
{
    use RefreshDatabase;

    private CommandRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = app(CommandRouter::class);
    }

    #[Test]
    public function route_returns_null_for_non_commands(): void
    {
        $user = User::factory()->create();

        $result = $this->router->route('Hello, help me with a task', $user);

        $this->assertNull($result);
    }

    #[Test]
    public function route_returns_failure_for_unknown_commands(): void
    {
        $user = User::factory()->create();

        $result = $this->router->route('/unknown', $user);

        $this->assertNotNull($result);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown command: /unknown', $result->message);
    }

    #[Test]
    public function route_executes_status_command(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $result = $this->router->route('/status', $user);

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active sessions: 1', $result->message);
    }

    #[Test]
    public function route_executes_sessions_list_command(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'title' => 'My Session',
        ]);

        $result = $this->router->route('/sessions list', $user);

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('My Session', $result->message);
    }

    #[Test]
    public function route_executes_mode_command_with_args(): void
    {
        $user = User::factory()->create();
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $result = $this->router->route('/mode standard', $user);

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('standard', $result->message);
    }

    #[Test]
    public function route_executes_browser_command(): void
    {
        $user = User::factory()->create();

        $result = $this->router->route('/browser status', $user);

        $this->assertNotNull($result);
        $this->assertTrue($result->success);
    }

    #[Test]
    public function get_available_commands_returns_all_commands(): void
    {
        $commands = $this->router->getAvailableCommands();

        $this->assertContains('jobs', $commands);
        $this->assertContains('runs', $commands);
        $this->assertContains('status', $commands);
        $this->assertContains('sessions', $commands);
        $this->assertContains('mode', $commands);
        $this->assertContains('approve', $commands);
        $this->assertContains('deny', $commands);
        $this->assertContains('browser', $commands);
        $this->assertContains('ask', $commands);
        $this->assertContains('context', $commands);
        $this->assertContains('new', $commands);
        $this->assertContains('help', $commands);
        $this->assertContains('commands', $commands);
        $this->assertContains('whoami', $commands);
        $this->assertContains('compact', $commands);
        $this->assertContains('subagents', $commands);
        $this->assertContains('progress', $commands);
        $this->assertContains('skills', $commands);
        $this->assertCount(18, $commands);
    }
}
