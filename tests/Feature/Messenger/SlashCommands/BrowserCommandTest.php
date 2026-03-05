<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Messenger\SlashCommands\BrowserCommandHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /browser slash command handler.
 */
final class BrowserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function browser_shows_usage_when_no_args(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage:', $result->message);
        $this->assertStringContainsString('start', $result->message);
        $this->assertStringContainsString('stop', $result->message);
        $this->assertStringContainsString('reset', $result->message);
        $this->assertStringContainsString('status', $result->message);
    }

    #[Test]
    public function browser_shows_usage_for_invalid_action(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['invalid']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage:', $result->message);
    }

    #[Test]
    public function browser_start_requests_sidecar_start(): void
    {
        config()->set('runtime.browser.sidecar_binary', '/usr/bin/chromium');
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['start']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Browser sidecar is ready', $result->message);
        $this->assertEquals('start', $result->data['action']);
    }

    #[Test]
    public function browser_stop_requests_sidecar_stop(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['stop']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('stop requested', $result->message);
        $this->assertEquals('stop', $result->data['action']);
    }

    #[Test]
    public function browser_reset_requests_sidecar_reset(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['reset']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('reset requested', $result->message);
        $this->assertEquals('reset', $result->data['action']);
    }

    #[Test]
    public function browser_status_returns_configuration(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['status']);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('configured', $result->data);
    }

    #[Test]
    public function browser_action_is_case_insensitive(): void
    {
        config()->set('runtime.browser.sidecar_binary', '/usr/bin/chromium');
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['START']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Browser sidecar is ready', $result->message);
    }
}
