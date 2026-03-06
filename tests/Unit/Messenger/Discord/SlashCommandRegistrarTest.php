<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Discord;

use App\Models\ConnectorAccount;
use App\Services\Messenger\SlashCommandRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the unified SlashCommandRegistrar (Services).
 *
 * Verifies:
 * - Registration with ConnectorAccount and credentials array
 * - Full command schema (jobs, runs, status, sessions, mode, approve, deny, browser, ask, context, new, help, commands, whoami, compact)
 * - Version tracking and connector metadata
 * - Error handling (403, 429, 401, 5xx)
 */
final class SlashCommandRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private SlashCommandRegistrar $registrar;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = app(SlashCommandRegistrar::class);

        $this->account = ConnectorAccount::factory()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.test-token',
                'application_id' => '1123456789012345678',
            ],
            'config' => [],
        ]);
    }

    #[Test]
    public function register_commands_calls_put_applications_commands_endpoint(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'jobs'],
                ['id' => '999888777666555443', 'name' => 'runs'],
            ], 200),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertTrue($result->isSuccessful());

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->url() === 'https://discord.com/api/v10/applications/1123456789012345678/commands'
                && str_starts_with($request->header('Authorization')[0], 'Bot ');
        });
    }

    #[Test]
    public function command_schema_includes_all_commands(): void
    {
        $schema = $this->registrar->getCommands();

        $this->assertIsArray($schema);
        $names = collect($schema)->pluck('name')->all();

        $this->assertContains('jobs', $names);
        $this->assertContains('runs', $names);
        $this->assertContains('status', $names);
        $this->assertContains('sessions', $names);
        $this->assertContains('mode', $names);
        $this->assertContains('approve', $names);
        $this->assertContains('deny', $names);
        $this->assertContains('browser', $names);
        $this->assertContains('ask', $names);
        $this->assertContains('context', $names);
        $this->assertContains('new', $names);
        $this->assertContains('help', $names);
        $this->assertContains('commands', $names);
        $this->assertContains('whoami', $names);
        $this->assertContains('compact', $names);
        $this->assertContains('subagents', $names);
        $this->assertContains('progress', $names);
        $this->assertCount(17, $schema);
    }

    #[Test]
    public function jobs_command_has_expected_subcommands(): void
    {
        $schema = $this->registrar->getCommands();
        $jobs = collect($schema)->firstWhere('name', 'jobs');

        $this->assertNotNull($jobs);
        $this->assertArrayHasKey('options', $jobs);
        $subNames = collect($jobs['options'])->pluck('name')->all();
        $this->assertContains('list', $subNames);
        $this->assertContains('show', $subNames);
        $this->assertContains('create', $subNames);
        $this->assertContains('delete', $subNames);
        $this->assertContains('run', $subNames);
        $this->assertContains('enable', $subNames);
        $this->assertContains('disable', $subNames);
    }

    #[Test]
    public function version_tracking_updates_connector_metadata(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'jobs'],
            ], 200),
        ]);

        $this->registrar->register($this->account);

        $this->account->refresh();
        $config = $this->account->config;

        $this->assertArrayHasKey('slash_command_version', $config);
        $this->assertNotEmpty($config['slash_command_version']);
        $this->assertArrayHasKey('slash_command_ids', $config);
        $this->assertContains('999888777666555444', $config['slash_command_ids']);
    }

    #[Test]
    public function needs_update_returns_true_when_no_version_stored(): void
    {
        $this->assertTrue($this->registrar->needsUpdate($this->account));
    }

    #[Test]
    public function needs_update_returns_false_after_successful_register(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'jobs'],
            ], 200),
        ]);

        $this->registrar->register($this->account);
        $this->account->refresh();

        $this->assertFalse($this->registrar->needsUpdate($this->account));
    }

    #[Test]
    public function registration_errors_return_actionable_messages(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'code' => 50001,
                'message' => 'Missing Access',
            ], 403),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('Missing Access', $result->getMessage());
    }

    #[Test]
    public function registration_returns_success_with_command_names(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '111', 'name' => 'jobs'],
                ['id' => '222', 'name' => 'runs'],
            ], 200),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertTrue($result->isSuccessful());
        $names = $result->getCommandNames();
        $this->assertContains('jobs', $names);
        $this->assertContains('runs', $names);
    }

    #[Test]
    public function registration_handles_rate_limit_response(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'message' => 'You are being rate limited.',
                'retry_after' => 5.0,
                'global' => false,
            ], 429),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('rate limit', strtolower($result->getMessage()));
    }

    #[Test]
    public function registration_handles_invalid_credentials(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'message' => '401: Unauthorized',
            ], 401),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('Unauthorized', $result->getMessage());
    }

    #[Test]
    public function register_accepts_credentials_array(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '111', 'name' => 'jobs'],
            ], 200),
        ]);

        $result = $this->registrar->register([
            'bot_token' => 'test-token',
            'application_id' => '1123456789012345678',
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertGreaterThanOrEqual(1, $result->getCommandCount());
    }
}
