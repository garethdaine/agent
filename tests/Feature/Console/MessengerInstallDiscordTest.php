<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Messenger\Discord\SlashCommandRegistrar;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for Discord connector installation with slash command registration.
 *
 * Assumptions:
 * - Discord connector already configured with bot_token and application_id
 * - Commands registered globally (up to 1 hour propagation)
 * - agent:install command triggers slash command registration for Discord
 *
 * Test Scenarios:
 * 1. Discord connector setup triggers command registration
 * 2. Registration success displays registered commands
 * 3. Registration failure shows actionable error (but does not fail installation)
 * 4. Command IDs stored in connector metadata
 * 5. Version tracking enables re-registration on updates
 */
class MessengerInstallDiscordTest extends TestCase
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
        putenv('MESSENGER_DISCORD_BOT_TOKEN');
        putenv('MESSENGER_DISCORD_APPLICATION_ID');
        putenv('MESSENGER_DISCORD_PUBLIC_KEY');

        parent::tearDown();
    }

    public function test_discord_connector_setup_triggers_command_registration(): void
    {
        // Mock successful Discord API responses
        Http::fake([
            // Credential validation
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
                'verified' => true,
            ], 200),
            // Slash command registration
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '999888777666555444',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                    'type' => 1,
                ],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Registering slash commands')
            ->assertSuccessful();

        // Verify command registration was called
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/applications/1123456789012345678/commands');
        });
    }

    public function test_registration_success_displays_registered_commands(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '999888777666555444',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Slash commands registered: /agent')
            ->assertSuccessful();
    }

    public function test_registration_failure_shows_actionable_error_but_continues(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'code' => 50001,
                'message' => 'Missing Access - Bot does not have applications.commands scope',
            ], 403),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Failed to register slash commands')
            ->assertSuccessful(); // Installation should still succeed
    }

    public function test_command_ids_stored_in_connector_metadata(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '111111111111111111',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'discord')->first();

        $this->assertNotNull($account);
        $this->assertArrayHasKey('slash_command_ids', $account->config);
        $this->assertArrayHasKey('slash_command_version', $account->config);
        $this->assertContains('111111111111111111', $account->config['slash_command_ids']);
    }

    public function test_discord_connector_validates_credentials_via_api(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestAgentBot',
                'discriminator' => '0000',
                'bot' => true,
                'verified' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Credentials validated: Bot: TestAgentBot')
            ->assertSuccessful();
    }

    public function test_discord_connector_fails_with_invalid_credentials(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'code' => 0,
                'message' => '401: Unauthorized',
            ], 401),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=invalid-token');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Credential validation failed');
    }

    public function test_discord_webhook_mode_requires_public_key(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');
        putenv('MESSENGER_DISCORD_PUBLIC_KEY=a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'discord')->first();

        $this->assertNotNull($account);
        $this->assertEquals('webhook', $account->connection_mode);
        $this->assertArrayHasKey('public_key', $account->credentials);
    }

    public function test_discord_connector_account_is_created_with_correct_structure(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('connector_accounts', [
            'provider' => 'discord',
            'connection_mode' => 'local',
        ]);

        $account = ConnectorAccount::where('provider', 'discord')->first();

        $this->assertNotNull($account);
        $this->assertArrayHasKey('bot_token', $account->credentials);
        $this->assertArrayHasKey('application_id', $account->credentials);
    }

    public function test_reinstall_updates_existing_discord_connector(): void
    {
        // Create existing account
        $accountKey = hash('sha256', 'discord:FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');

        ConnectorAccount::create([
            'provider' => 'discord',
            'name' => 'Old Discord Bot',
            'credentials' => [
                'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
                'application_id' => '1123456789012345678',
            ],
            'connection_mode' => 'webhook',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => $accountKey,
            'config' => ['old_setting' => 'value'],
        ]);

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'UpdatedBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');

        $this->artisan('agent:install', [
            '--connector' => 'discord',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])
            ->expectsOutputToContain('Updating existing')
            ->assertSuccessful();

        // Should still have only one account
        $this->assertDatabaseCount('connector_accounts', 1);

        // Account should be updated with new mode
        $account = ConnectorAccount::where('provider', 'discord')->first();
        $this->assertSame('local', $account->connection_mode);
        $this->assertSame(ConnectorAccount::STATUS_DISCONNECTED, $account->status);

        // Should have slash command registration data
        $this->assertArrayHasKey('slash_command_version', $account->config);
    }

    public function test_discord_works_with_multiple_connectors(): void
    {
        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'slackbot',
                'team' => 'Test Team',
            ], 200),
        ]);

        putenv('MESSENGER_DISCORD_BOT_TOKEN=FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890');
        putenv('MESSENGER_DISCORD_APPLICATION_ID=1123456789012345678');
        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => ['discord', 'slack'],
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('connector_accounts', ['provider' => 'discord']);
        $this->assertDatabaseHas('connector_accounts', ['provider' => 'slack']);

        // Cleanup
        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }
}
