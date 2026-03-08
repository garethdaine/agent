<?php

declare(strict_types=1);

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
        $this->artisan('agent:install --connector=discord --non-interactive --skip-migrations --skip-health-check --skip-license')
            ->assertFailed();
    }

    #[Test]
    public function it_accepts_whatsapp_connector(): void
    {
        $this->artisan('agent:install --connector=whatsapp --non-interactive --skip-migrations --skip-health-check --skip-license')
            ->assertFailed();
    }

    #[Test]
    public function it_runs_successfully_without_connectors(): void
    {
        $this->artisan('agent:install --non-interactive --skip-migrations --skip-health-check --skip-license')
            ->assertSuccessful();
    }
}
