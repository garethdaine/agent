<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Messenger\Validation\IngressProbe;
use App\Messenger\Validation\ProbeResult;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for mode-aware install flow and webhook ingress validation.
 *
 * Assumptions:
 * - agent:install command exists and handles connector setup
 * - Credential validators implemented for all providers
 * - IngressProbe service validates webhook endpoints
 *
 * Test Scenarios:
 * 1. Mode selection shows available modes per provider
 * 2. WhatsApp shows webhook-only (local mode disabled)
 * 3. Local mode skips webhook URL prompt
 * 4. Webhook mode requires and validates URL
 * 5. Credential prompts match selected mode
 * 6. Ingress probe runs for webhook mode
 * 7. Probe failure shows actionable diagnostic
 */
class MessengerInstallModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directories exist
        $storageDirs = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($storageDirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    protected function tearDown(): void
    {
        // Cleanup environment variables
        $envVars = [
            'MESSENGER_SLACK_BOT_TOKEN',
            'MESSENGER_SLACK_SIGNING_SECRET',
            'MESSENGER_SLACK_APP_TOKEN',
            'MESSENGER_TELEGRAM_BOT_TOKEN',
            'MESSENGER_DISCORD_BOT_TOKEN',
            'MESSENGER_DISCORD_APPLICATION_ID',
            'MESSENGER_DISCORD_PUBLIC_KEY',
            'MESSENGER_WHATSAPP_ACCESS_TOKEN',
            'MESSENGER_WHATSAPP_PHONE_NUMBER_ID',
            'MESSENGER_WHATSAPP_APP_SECRET',
            'MESSENGER_WHATSAPP_VERIFY_TOKEN',
            'MESSENGER_WEBHOOK_URL',
        ];

        foreach ($envVars as $var) {
            putenv($var);
        }

        parent::tearDown();
    }

    // =========================================================================
    // Mode Selection Tests
    // =========================================================================

    public function test_mode_selection_shows_available_modes_for_slack(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
                'team_id' => 'T12345',
                'user_id' => 'U12345',
                'bot_id' => 'B12345',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        // Slack supports both local (Socket Mode) and webhook
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'slack')->first();

        $this->assertNotNull($account);
        $this->assertEquals('local', $account->connection_mode);
    }

    public function test_mode_selection_shows_available_modes_for_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');

        // Telegram supports both local (Long Polling) and webhook
        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'telegram')->first();

        $this->assertNotNull($account);
        $this->assertEquals('local', $account->connection_mode);
    }

    public function test_mode_selection_shows_available_modes_for_discord(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/*/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        // Discord supports both local (Gateway) and webhook
        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'discord')->first();

        $this->assertNotNull($account);
        $this->assertEquals('local', $account->connection_mode);
    }

    public function test_whatsapp_shows_webhook_only_and_disables_local_mode(): void
    {
        Http::fake([
            'https://graph.facebook.com/v18.0/me' => Http::response([
                'id' => '123456789',
                'name' => 'Test App',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        putenv('MESSENGER_WHATSAPP_ACCESS_TOKEN=test-access-token');
        putenv('MESSENGER_WHATSAPP_PHONE_NUMBER_ID=123456789');
        putenv('MESSENGER_WHATSAPP_APP_SECRET=test-app-secret');
        putenv('MESSENGER_WHATSAPP_VERIFY_TOKEN=my-verify-token');

        // WhatsApp only supports webhook mode (Cloud API)
        // Even if local mode is requested, it should use webhook
        $this->artisan('agent:install', [
            '--connector' => 'whatsapp',
            '--mode' => 'local', // This should be ignored for WhatsApp
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'whatsapp')->first();

        $this->assertNotNull($account);
        $this->assertEquals('webhook', $account->connection_mode);
    }

    // =========================================================================
    // Local Mode Tests
    // =========================================================================

    public function test_local_mode_skips_webhook_url_prompt(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
                'team_id' => 'T12345',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        // Local mode should not require webhook URL
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->assertSuccessful()
            ->doesntExpectOutputToContain('webhook URL');
    }

    public function test_local_mode_does_not_run_ingress_probe(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        // Mock IngressProbe to ensure it's not called
        $mockProbe = Mockery::mock(IngressProbe::class);
        $mockProbe->shouldNotReceive('probe');
        $this->app->instance(IngressProbe::class, $mockProbe);

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();
    }

    // =========================================================================
    // Webhook Mode Tests
    // =========================================================================

    public function test_webhook_mode_requires_webhook_url(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
            ], 200),
            // Mock successful ingress probe
            'https://example.com/webhook/slack*' => function ($request) {
                $body = $request->data();
                if (($body['type'] ?? '') === 'url_verification') {
                    return Http::response(['challenge' => $body['challenge']], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/slack');

        // Webhook mode with valid URL
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'slack')->first();
        $this->assertEquals('webhook', $account->connection_mode);
    }

    public function test_webhook_mode_validates_url_with_ingress_probe(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/*/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
            // Mock the Discord webhook endpoint
            'https://example.com/webhook/discord*' => function ($request) {
                $body = json_decode($request->body(), true);
                if (($body['type'] ?? 0) === 1) {
                    return Http::response(['type' => 1], 200); // PONG
                }

                return Http::response('OK', 200);
            },
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');
        putenv('MESSENGER_DISCORD_PUBLIC_KEY='.str_repeat('a', 64));
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/discord');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Validating webhook endpoint')
            ->assertSuccessful();
    }

    // =========================================================================
    // Credential Prompt Tests
    // =========================================================================

    public function test_credential_prompts_match_selected_mode_for_slack_local(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_APP_TOKEN=xapp-test-token'); // Socket Mode requires app token
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        // Slack local mode (Socket Mode) requires app_token
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();
    }

    public function test_credential_prompts_match_selected_mode_for_discord_webhook(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/*/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
            'https://example.com/webhook/discord*' => Http::response(['type' => 1], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');
        putenv('MESSENGER_DISCORD_PUBLIC_KEY='.str_repeat('a', 64)); // Required for webhook
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/discord');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'discord')->first();
        $this->assertArrayHasKey('public_key', $account->credentials);
    }

    // =========================================================================
    // Ingress Probe Tests
    // =========================================================================

    public function test_ingress_probe_runs_for_webhook_mode(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ], 200),
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
            'https://example.com/webhook/telegram*' => Http::response('OK', 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/telegram');

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Validating webhook endpoint')
            ->assertSuccessful();
    }

    public function test_probe_failure_shows_actionable_diagnostic(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        putenv('MESSENGER_WEBHOOK_URL=https://unreachable.example.com/webhook/telegram');

        // Mock the IngressProbe to return a failure with actionable diagnostic
        $mockProbe = Mockery::mock(IngressProbe::class);
        $mockProbe->shouldReceive('probe')
            ->andReturn(ProbeResult::failure(
                'Webhook URL not reachable',
                'Check firewall rules and ensure the URL is publicly accessible.'
            ));
        $this->app->instance(IngressProbe::class, $mockProbe);

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Webhook validation failed')
            ->expectsOutputToContain('firewall')
            ->assertExitCode(1);
    }

    public function test_probe_warning_displays_but_continues(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
            ], 200),
            'https://example.com/webhook/slack*' => function ($request) {
                $body = $request->data();
                if (($body['type'] ?? '') === 'url_verification') {
                    return Http::response(['challenge' => $body['challenge']], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/slack');

        // Mock IngressProbe to return success with warning
        $mockProbe = Mockery::mock(IngressProbe::class);
        $mockProbe->shouldReceive('probe')
            ->andReturn(ProbeResult::successWithWarning(
                'TLS certificate expiring in 5 days. Consider renewing soon.'
            ));
        $this->app->instance(IngressProbe::class, $mockProbe);

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('expiring')
            ->assertSuccessful();
    }

    // =========================================================================
    // Provider-Specific Verification Tests
    // =========================================================================

    public function test_slack_webhook_mode_verifies_url_challenge(): void
    {
        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'agentbot',
                'team' => 'Test Team',
            ], 200),
            // Slack URL verification challenge
            'https://example.com/webhook/slack*' => function ($request) {
                $body = $request->data();
                if (($body['type'] ?? '') === 'url_verification') {
                    return Http::response(['challenge' => $body['challenge']], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/slack');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Webhook endpoint validated')
            ->assertSuccessful();
    }

    public function test_telegram_webhook_mode_verifies_setwebhook(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ], 200),
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was set',
            ], 200),
            'https://example.com/webhook/telegram*' => Http::response('OK', 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/telegram');

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Webhook endpoint validated')
            ->assertSuccessful();

        // Verify setWebhook was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook');
        });
    }

    public function test_discord_webhook_mode_verifies_ping_pong(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/*/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
            // Discord PING/PONG verification
            'https://example.com/webhook/discord*' => function ($request) {
                $body = json_decode($request->body(), true);
                if (($body['type'] ?? 0) === 1) {
                    return Http::response(['type' => 1], 200); // PONG
                }

                return Http::response('OK', 200);
            },
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');
        putenv('MESSENGER_DISCORD_PUBLIC_KEY='.str_repeat('a', 64));
        putenv('MESSENGER_WEBHOOK_URL=https://example.com/webhook/discord');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Webhook endpoint validated')
            ->assertSuccessful();
    }

    public function test_whatsapp_webhook_mode_validates_verify_token(): void
    {
        Http::fake([
            'https://graph.facebook.com/v18.0/me' => Http::response([
                'id' => '123456789',
                'name' => 'Test App',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        putenv('MESSENGER_WHATSAPP_ACCESS_TOKEN=test-access-token');
        putenv('MESSENGER_WHATSAPP_PHONE_NUMBER_ID=123456789');
        putenv('MESSENGER_WHATSAPP_APP_SECRET=test-app-secret');
        putenv('MESSENGER_WHATSAPP_VERIFY_TOKEN=my-verify-token');

        // WhatsApp verification happens on Meta's side
        $this->artisan('agent:install', [
            '--connector' => 'whatsapp',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('WhatsApp Webhook Configuration')
            ->expectsOutputToContain('my-verify-token')
            ->assertSuccessful();
    }
}
