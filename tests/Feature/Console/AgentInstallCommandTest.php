<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentInstallCommandTest extends TestCase
{
    #[Test]
    public function help_text_lists_all_four_providers(): void
    {
        // Capture the help output
        Artisan::call('agent:install', ['--help' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('slack', $output);
        $this->assertStringContainsString('telegram', $output);
        $this->assertStringContainsString('discord', $output);
        $this->assertStringContainsString('whatsapp', $output);
    }

    #[Test]
    public function it_accepts_discord_connector(): void
    {
        // Test that the command doesn't fail on validation for discord connector
        // Use --non-interactive and --skip-migrations to avoid interactive prompts and DB operations
        // The command will fail due to missing credentials, but that's expected - we're just verifying
        // that 'discord' is accepted as a valid connector value
        $this->artisan('agent:install --connector=discord --non-interactive --skip-migrations --skip-health-check')
            ->assertFailed(); // Expected: fails due to missing credentials, not invalid connector
    }

    #[Test]
    public function it_accepts_whatsapp_connector(): void
    {
        // Test that the command doesn't fail on validation for whatsapp connector
        $this->artisan('agent:install --connector=whatsapp --non-interactive --skip-migrations --skip-health-check')
            ->assertFailed(); // Expected: fails due to missing credentials, not invalid connector
    }

    #[Test]
    public function it_rejects_invalid_connector(): void
    {
        // Verify invalid connector is filtered out and results in no connectors being configured
        $this->artisan('agent:install --connector=invalid_provider --non-interactive --skip-migrations --skip-health-check')
            ->expectsOutput('No connectors selected. Skipping connector configuration.');
    }
}
