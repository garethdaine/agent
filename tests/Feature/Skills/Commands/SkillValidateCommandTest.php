<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = base_path('tests/Fixtures/Skills');
    }

    public function test_validates_skill_without_installing(): void
    {
        $this->artisan('skill:validate', ['path' => $this->fixturesPath.'/valid-skill'])
            ->expectsOutputToContain('Validation');

        $this->assertDatabaseCount('agent_skills', 0);
    }

    public function test_returns_exit_code_0_on_pass(): void
    {
        $this->artisan('skill:validate', ['path' => $this->fixturesPath.'/valid-skill'])
            ->assertExitCode(0);
    }

    public function test_returns_exit_code_1_on_fail(): void
    {
        $this->artisan('skill:validate', ['path' => $this->fixturesPath.'/no-skill-md'])
            ->assertExitCode(1);
    }
}
