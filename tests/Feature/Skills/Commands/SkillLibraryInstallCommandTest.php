<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillLibraryInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $teamId;

    private string $manifestPath;

    private ?string $originalManifest = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->teamId = $this->user->currentTeam->id;
        $this->manifestPath = config('agent.skills.library_manifest_path');

        if (File::exists($this->manifestPath)) {
            $this->originalManifest = File::get($this->manifestPath);
        }
    }

    public function test_installs_from_library_by_slug(): void
    {
        $fixtureSkill = base_path('tests/Fixtures/Skills/valid-skill.skill');
        $relPath = '../tests/Fixtures/Skills/valid-skill.skill';

        $manifest = [
            'version' => '1.0.0',
            'skills' => [[
                'slug' => 'financial-compliance-check',
                'name' => 'Financial Compliance Check',
                'description' => 'Validates outputs',
                'version' => '1.0.0',
                'author' => 'agentops',
                'category' => 'compliance',
                'industries' => ['financial-services'],
                'risk_level' => 'elevated',
                'file' => $relPath,
                'checksum' => 'sha256:'.hash_file('sha256', $fixtureSkill),
            ]],
        ];

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        $this->artisan('skill:library:install', [
            'slug' => 'financial-compliance-check',
            '--team' => $this->teamId,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('installed');
    }

    public function test_unknown_slug_shows_error(): void
    {
        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode(['version' => '1.0.0', 'skills' => []]));

        $this->artisan('skill:library:install', [
            'slug' => 'nonexistent-skill',
            '--team' => $this->teamId,
        ])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    protected function tearDown(): void
    {
        if ($this->originalManifest !== null) {
            File::put($this->manifestPath, $this->originalManifest);
        }

        $skillsPath = config('agent.skills.storage_path');
        if (File::isDirectory($skillsPath.'/'.$this->teamId)) {
            File::deleteDirectory($skillsPath.'/'.$this->teamId);
        }

        $tmpPath = storage_path('app/skills/tmp');
        if (File::isDirectory($tmpPath)) {
            File::deleteDirectory($tmpPath);
        }

        parent::tearDown();
    }
}
