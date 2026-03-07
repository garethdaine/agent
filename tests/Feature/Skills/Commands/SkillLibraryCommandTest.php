<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillLibraryCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestPath;

    private ?string $originalManifest = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifestPath = config('agent.skills.library_manifest_path');

        if (File::exists($this->manifestPath)) {
            $this->originalManifest = File::get($this->manifestPath);
        }
    }

    public function test_lists_library_skills(): void
    {
        $this->writeManifest([
            ['slug' => 'financial-compliance-check', 'name' => 'Financial Compliance Check', 'description' => 'Validates outputs', 'version' => '1.0.0', 'author' => 'agentops', 'category' => 'compliance', 'industries' => ['financial-services'], 'risk_level' => 'elevated', 'file' => 'dist/financial-compliance-check.skill', 'checksum' => 'sha256:abc'],
            ['slug' => 'pii-detector-redactor', 'name' => 'PII Detector Redactor', 'description' => 'Detects and redacts PII', 'version' => '1.0.0', 'author' => 'agentops', 'category' => 'cross-industry', 'industries' => ['cross-industry'], 'risk_level' => 'standard', 'file' => 'dist/pii-detector-redactor.skill', 'checksum' => 'sha256:def'],
        ]);

        $this->artisan('skill:library')
            ->assertSuccessful()
            ->expectsOutputToContain('financial-compliance-check')
            ->expectsOutputToContain('pii-detector-redactor');
    }

    public function test_filters_by_category(): void
    {
        $this->writeManifest([
            ['slug' => 'financial-compliance-check', 'name' => 'Financial Compliance Check', 'description' => 'Validates outputs', 'version' => '1.0.0', 'author' => 'agentops', 'category' => 'compliance', 'industries' => ['financial-services'], 'risk_level' => 'elevated', 'file' => 'dist/financial-compliance-check.skill', 'checksum' => 'sha256:abc'],
            ['slug' => 'pii-detector-redactor', 'name' => 'PII Detector Redactor', 'description' => 'Detects and redacts PII', 'version' => '1.0.0', 'author' => 'agentops', 'category' => 'cross-industry', 'industries' => ['cross-industry'], 'risk_level' => 'standard', 'file' => 'dist/pii-detector-redactor.skill', 'checksum' => 'sha256:def'],
        ]);

        $this->artisan('skill:library', ['--category' => 'compliance'])
            ->assertSuccessful()
            ->expectsOutputToContain('financial-compliance-check')
            ->doesntExpectOutputToContain('pii-detector-redactor');
    }

    private function writeManifest(array $skills): void
    {
        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode(['version' => '1.0.0', 'skills' => $skills], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if ($this->originalManifest !== null) {
            File::put($this->manifestPath, $this->originalManifest);
        }

        parent::tearDown();
    }
}
