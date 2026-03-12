<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Messenger\SlashCommands\BrowserCommandHandler;
use App\Models\ChatSession;
use App\Models\Runtime\RuntimeSession;
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

    // --- profile command ---

    #[Test]
    public function browser_profile_sets_persistent_mode(): void
    {
        config()->set('runtime.browser.profiles_base_path', sys_get_temp_dir().'/browser-profiles-test-'.uniqid());
        $user = User::factory()->create();
        $chatSession = ChatSession::factory()->create(['user_id' => $user->id]);
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'chat_session_id' => $chatSession->id,
        ]);

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['profile', 'twitter'], $chatSession->id);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('twitter', $result->message);
        $this->assertEquals('profile', $result->data['action']);
        $this->assertEquals('persistent', $result->data['persistence_mode']);

        $session->refresh();
        $this->assertEquals(BrowserPersistenceMode::Persistent, $session->browser_persistence_mode);
        $this->assertEquals('twitter', $session->browser_profile_name);
        $this->assertNull($session->browser_cdp_endpoint);
    }

    #[Test]
    public function browser_profile_requires_name_argument(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['profile']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage:', $result->message);
    }

    #[Test]
    public function browser_profile_fails_without_active_session(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['profile', 'twitter']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No active runtime session', $result->message);
    }

    // --- profiles command ---

    #[Test]
    public function browser_profiles_lists_user_profiles(): void
    {
        config()->set('runtime.browser.profiles_base_path', sys_get_temp_dir().'/browser-profiles-test-'.uniqid());
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['profiles']);

        $this->assertTrue($result->success);
        $this->assertEquals([], $result->data['profiles']);
    }

    // --- cdp command ---

    #[Test]
    public function browser_cdp_sets_cdp_mode(): void
    {
        $user = User::factory()->create();
        $chatSession = ChatSession::factory()->create(['user_id' => $user->id]);
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'chat_session_id' => $chatSession->id,
        ]);

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['cdp', 'ws://localhost:9222/devtools/browser/abc'], $chatSession->id);

        $this->assertTrue($result->success);
        $this->assertEquals('cdp', $result->data['action']);
        $this->assertEquals('cdp', $result->data['persistence_mode']);

        $session->refresh();
        $this->assertEquals(BrowserPersistenceMode::Cdp, $session->browser_persistence_mode);
        $this->assertEquals('ws://localhost:9222/devtools/browser/abc', $session->browser_cdp_endpoint);
        $this->assertNull($session->browser_profile_name);
    }

    #[Test]
    public function browser_cdp_validates_websocket_url(): void
    {
        $user = User::factory()->create();
        $chatSession = ChatSession::factory()->create(['user_id' => $user->id]);
        RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
            'chat_session_id' => $chatSession->id,
        ]);

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['cdp', 'http://localhost:9222'], $chatSession->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('ws://', $result->message);
    }

    #[Test]
    public function browser_cdp_requires_endpoint_argument(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['cdp']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage:', $result->message);
    }

    // --- headed command ---

    #[Test]
    public function browser_headed_reports_current_state(): void
    {
        config()->set('runtime.browser.headed', false);
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, ['headed']);

        $this->assertTrue($result->success);
        $this->assertEquals('headed', $result->data['action']);
        $this->assertFalse($result->data['headed']);
    }

    // --- usage includes new commands ---

    #[Test]
    public function browser_usage_includes_new_commands(): void
    {
        $user = User::factory()->create();

        $handler = new BrowserCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertStringContainsString('profile', $result->message);
        $this->assertStringContainsString('profiles', $result->message);
        $this->assertStringContainsString('cdp', $result->message);
        $this->assertStringContainsString('headed', $result->message);
    }
}
