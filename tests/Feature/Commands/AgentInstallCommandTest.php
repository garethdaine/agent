<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentInstallCommandTest extends TestCase
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

    public function test_runs_without_connectors_in_non_interactive_mode(): void
    {
        $this->artisan('agent:install', [
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Installation Complete');
    }

    public function test_preflight_checks_verify_redis_connectivity(): void
    {
        // The preflight check should test Redis connectivity
        // This test verifies the command includes Redis check output
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Redis Connectivity');
    }

    public function test_preflight_checks_verify_database_connectivity(): void
    {
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Database Connectivity');
    }

    public function test_preflight_checks_verify_writable_storage_directories(): void
    {
        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Writable:');
    }

    public function test_connector_configuration_validates_slack_credentials(): void
    {
        // Mock successful Slack API response
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'testbot',
                'team' => 'Test Workspace',
            ], 200),
        ]);

        // Set environment variables for non-interactive mode
        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Credentials validated');

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }

    public function test_connector_configuration_validates_telegram_credentials(): void
    {
        // Mock successful Telegram API response
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'testbot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Credentials validated');

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN');
    }

    public function test_connector_configuration_fails_with_invalid_slack_credentials(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => false,
                'error' => 'invalid_auth',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=invalid-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Credential validation failed');

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }

    public function test_migrations_are_skipped_when_flag_present(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'testbot',
                'team' => 'Test Workspace',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Skipping migrations');

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }

    public function test_health_check_reports_service_status(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'testbot',
                'team' => 'Test Workspace',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Running health check')
            ->expectsOutputToContain('Database')
            ->expectsOutputToContain('Redis');

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }

    public function test_connector_account_is_created_with_valid_credentials(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Agent Bot',
                    'username' => 'agentbot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('connector_accounts', [
            'provider' => 'telegram',
            'connection_mode' => 'local',
        ]);

        $account = ConnectorAccount::where('provider', 'telegram')->first();
        $this->assertNotNull($account);
        $this->assertArrayHasKey('bot_token', $account->credentials);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN');
    }

    public function test_existing_connector_account_is_updated_on_reinstall(): void
    {
        // Create existing account
        $accountKey = hash('sha256', 'telegram:123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

        ConnectorAccount::create([
            'provider' => 'telegram',
            'name' => 'Old Bot Name',
            'credentials' => ['bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz'],
            'connection_mode' => 'webhook',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'account_key' => $accountKey,
            'config' => [],
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Updated Bot',
                    'username' => 'updatedbot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

        $this->artisan('agent:install', [
            '--connector' => 'telegram',
            '--mode' => 'local',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])
            ->expectsOutputToContain('Updating existing')
            ->assertSuccessful();

        // Should still have only one account
        $this->assertDatabaseCount('connector_accounts', 1);

        // Account should be updated
        $account = ConnectorAccount::where('provider', 'telegram')->first();
        $this->assertNotNull($account);
        $this->assertSame('local', $account->connection_mode);
        // Status should be reset to disconnected when updating
        $this->assertSame(ConnectorAccount::STATUS_DISCONNECTED, $account->status);

        putenv('MESSENGER_TELEGRAM_BOT_TOKEN');
    }

    public function test_multiple_connectors_can_be_configured(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'slackbot',
                'team' => 'Slack Workspace',
            ], 200),
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Telegram Bot',
                    'username' => 'telegrambot',
                ],
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');
        putenv('MESSENGER_TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

        $this->artisan('agent:install', [
            '--connector' => ['slack', 'telegram'],
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('connector_accounts', ['provider' => 'slack']);
        $this->assertDatabaseHas('connector_accounts', ['provider' => 'telegram']);

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
        putenv('MESSENGER_TELEGRAM_BOT_TOKEN');
    }

    public function test_webhook_mode_is_stored_correctly(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'testbot',
                'team' => 'Test Workspace',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--mode' => 'webhook',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])->assertSuccessful();

        $account = ConnectorAccount::where('provider', 'slack')->first();
        $this->assertNotNull($account);
        $this->assertSame('webhook', $account->connection_mode);

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }

    public function test_installation_is_idempotent(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'user' => 'testbot',
                'team' => 'Test Workspace',
            ], 200),
        ]);

        putenv('MESSENGER_SLACK_BOT_TOKEN=xoxb-test-token');
        putenv('MESSENGER_SLACK_SIGNING_SECRET=test-secret');

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])->assertSuccessful();

        $firstAccount = ConnectorAccount::where('provider', 'slack')->first();
        $firstId = $firstAccount->id;

        $this->artisan('agent:install', [
            '--connector' => 'slack',
            '--non-interactive' => true,
            '--skip-migrations' => true,
            '--skip-health-check' => true,
            '--skip-license' => true,
        ])->assertSuccessful();

        // Should still have only one account
        $this->assertDatabaseCount('connector_accounts', 1);

        // Should be the same account (same ID)
        $secondAccount = ConnectorAccount::where('provider', 'slack')->first();
        $this->assertSame($firstId, $secondAccount->id);

        putenv('MESSENGER_SLACK_BOT_TOKEN');
        putenv('MESSENGER_SLACK_SIGNING_SECRET');
    }
}
