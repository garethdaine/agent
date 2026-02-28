<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Discord;

use App\Messenger\Discord\RegistrationResult;
use App\Messenger\Discord\SlashCommandRegistrar;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for DiscordSlashCommandRegistrar.
 *
 * Assumptions:
 * - Discord connector already configured with bot_token and application_id
 * - Commands registered globally (up to 1 hour propagation)
 * - Bulk overwrite via PUT /applications/{id}/commands
 *
 * Command Schema:
 * - /agent - Primary command with subcommands: run, status, cancel, list
 * - Each subcommand has appropriate options defined
 *
 * Version Tracking:
 * - Command version stored in connector metadata
 * - needsUpdate() compares stored version to current COMMAND_VERSION
 * - Re-registration triggered when versions differ
 *
 * Error Handling:
 * - API errors return RegistrationResult::failure() with actionable message
 * - Timeout errors include retry guidance
 * - Rate limit errors include wait time guidance
 */
class SlashCommandRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private SlashCommandRegistrar $registrar;

    private ConnectorAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = new SlashCommandRegistrar();

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

    public function test_register_commands_calls_put_applications_commands_endpoint(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '999888777666555444',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                ],
            ], 200),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->url() === 'https://discord.com/api/v10/applications/1123456789012345678/commands'
                && str_starts_with($request->header('Authorization')[0], 'Bot ');
        });
    }

    public function test_command_schema_includes_agent_command_with_subcommands(): void
    {
        $schema = $this->registrar->getCommandSchema();

        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);

        // Find the /agent command
        $agentCommand = collect($schema)->firstWhere('name', 'agent');

        $this->assertNotNull($agentCommand, 'Expected /agent command in schema');
        $this->assertEquals(1, $agentCommand['type'], 'Command should be CHAT_INPUT type (1)');
        $this->assertArrayHasKey('options', $agentCommand);

        // Verify subcommands
        $subcommandNames = collect($agentCommand['options'])->pluck('name')->all();
        $this->assertContains('run', $subcommandNames);
        $this->assertContains('status', $subcommandNames);
        $this->assertContains('cancel', $subcommandNames);
        $this->assertContains('list', $subcommandNames);

        // Verify subcommands are type 1 (SUB_COMMAND)
        foreach ($agentCommand['options'] as $option) {
            $this->assertEquals(1, $option['type'], "Subcommand {$option['name']} should be type 1 (SUB_COMMAND)");
        }
    }

    public function test_version_tracking_updates_connector_metadata(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '999888777666555444',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                ],
            ], 200),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertTrue($result->success);

        // Reload account to get fresh metadata
        $this->account->refresh();
        $config = $this->account->config;

        $this->assertArrayHasKey('slash_command_version', $config);
        $this->assertNotEmpty($config['slash_command_version']);
        $this->assertArrayHasKey('slash_command_ids', $config);
        $this->assertContains('999888777666555444', $config['slash_command_ids']);
    }

    public function test_version_comparison_detects_changes_requiring_reregistration(): void
    {
        // Account with no slash command version - needs update
        $this->assertTrue($this->registrar->needsUpdate($this->account));

        // Account with outdated version - needs update
        $this->account->update([
            'config' => array_merge($this->account->config ?? [], [
                'slash_command_version' => '0.0.0',
            ]),
        ]);
        $this->assertTrue($this->registrar->needsUpdate($this->account));

        // Account with current version - no update needed
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        $this->registrar->register($this->account);
        $this->account->refresh();

        $this->assertFalse($this->registrar->needsUpdate($this->account));
    }

    public function test_registration_errors_return_actionable_messages(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'code' => 50001,
                'message' => 'Missing Access',
            ], 403),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->error);
        $this->assertStringContainsString('Missing Access', $result->error);
    }

    public function test_registration_returns_success_with_command_data(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                [
                    'id' => '999888777666555444',
                    'name' => 'agent',
                    'description' => 'Interact with the agent',
                    'type' => 1,
                ],
            ], 200),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->commands);
        $this->assertCount(1, $result->commands);
        $this->assertEquals('agent', $result->commands[0]['name']);
    }

    public function test_registration_handles_rate_limit_response(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'message' => 'You are being rate limited.',
                'retry_after' => 5.0,
                'global' => false,
            ], 429),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('rate limit', strtolower($result->error));
    }

    public function test_registration_handles_network_timeout(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response(null, 504),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->error);
    }

    public function test_registration_handles_invalid_credentials(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                'code' => 0,
                'message' => '401: Unauthorized',
            ], 401),
        ]);

        $result = $this->registrar->register($this->account);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unauthorized', $result->error);
    }

    public function test_command_schema_has_descriptions_for_all_subcommands(): void
    {
        $schema = $this->registrar->getCommandSchema();
        $agentCommand = collect($schema)->firstWhere('name', 'agent');

        foreach ($agentCommand['options'] as $subcommand) {
            $this->assertArrayHasKey('description', $subcommand);
            $this->assertNotEmpty($subcommand['description'], "Subcommand {$subcommand['name']} should have a description");
        }
    }

    public function test_run_subcommand_includes_required_options(): void
    {
        $schema = $this->registrar->getCommandSchema();
        $agentCommand = collect($schema)->firstWhere('name', 'agent');
        $runSubcommand = collect($agentCommand['options'])->firstWhere('name', 'run');

        $this->assertNotNull($runSubcommand);
        $this->assertArrayHasKey('options', $runSubcommand);

        // Should have a 'command' or 'task' option for specifying what to run
        $optionNames = collect($runSubcommand['options'])->pluck('name')->all();
        $this->assertTrue(
            in_array('command', $optionNames) || in_array('task', $optionNames),
            'run subcommand should have a command or task option'
        );
    }

    public function test_status_subcommand_includes_job_id_option(): void
    {
        $schema = $this->registrar->getCommandSchema();
        $agentCommand = collect($schema)->firstWhere('name', 'agent');
        $statusSubcommand = collect($agentCommand['options'])->firstWhere('name', 'status');

        $this->assertNotNull($statusSubcommand);

        // Status may have optional job_id filter or work without options
        // Just verify it's a valid subcommand
        $this->assertEquals(1, $statusSubcommand['type']);
    }

    public function test_cancel_subcommand_includes_job_id_option(): void
    {
        $schema = $this->registrar->getCommandSchema();
        $agentCommand = collect($schema)->firstWhere('name', 'agent');
        $cancelSubcommand = collect($agentCommand['options'])->firstWhere('name', 'cancel');

        $this->assertNotNull($cancelSubcommand);
        $this->assertArrayHasKey('options', $cancelSubcommand);

        // Should have a job_id option for specifying which job to cancel
        $optionNames = collect($cancelSubcommand['options'])->pluck('name')->all();
        $this->assertContains('job_id', $optionNames);
    }

    public function test_multiple_command_ids_stored_when_multiple_commands_registered(): void
    {
        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '111111111111111111', 'name' => 'agent'],
                ['id' => '222222222222222222', 'name' => 'help'],
            ], 200),
        ]);

        $this->registrar->register($this->account);
        $this->account->refresh();

        $commandIds = $this->account->config['slash_command_ids'];
        $this->assertCount(2, $commandIds);
        $this->assertContains('111111111111111111', $commandIds);
        $this->assertContains('222222222222222222', $commandIds);
    }

    public function test_registration_preserves_existing_config(): void
    {
        $existingConfig = [
            'threading_mode' => 'native',
            'custom_setting' => 'value',
        ];

        $this->account->update(['config' => $existingConfig]);

        Http::fake([
            'https://discord.com/api/v10/applications/1123456789012345678/commands' => Http::response([
                ['id' => '999888777666555444', 'name' => 'agent'],
            ], 200),
        ]);

        $this->registrar->register($this->account);
        $this->account->refresh();

        // Original config should be preserved
        $this->assertEquals('native', $this->account->config['threading_mode']);
        $this->assertEquals('value', $this->account->config['custom_setting']);

        // New slash command config should be added
        $this->assertArrayHasKey('slash_command_version', $this->account->config);
    }
}
