<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Services\Skills\DTOs\SkillLibraryEntry;
use App\Services\Skills\SkillLibrary;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SkillLibraryTest extends TestCase
{
    private string $manifestPath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/skill-library-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->manifestPath = $this->tempDir.'/manifest.json';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeLibrary(?string $path = null): SkillLibrary
    {
        return new SkillLibrary($path ?? $this->manifestPath);
    }

    private function writeManifest(array $data): void
    {
        file_put_contents($this->manifestPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function sampleManifest(int $count = 3): array
    {
        $skills = [];
        $templates = [
            ['slug' => 'financial-compliance-check', 'name' => 'Financial Compliance Check', 'description' => 'Validates outputs against FCA, SOX, and GDPR requirements for financial services', 'category' => 'compliance', 'industries' => ['financial-services', 'accounting'], 'risk_level' => 'elevated'],
            ['slug' => 'pii-detector-redactor', 'name' => 'PII Detector & Redactor', 'description' => 'Detects and redacts personally identifiable information from documents', 'category' => 'security', 'industries' => ['cross-industry'], 'risk_level' => 'standard'],
            ['slug' => 'rota-optimizer', 'name' => 'Rota Optimizer', 'description' => 'Optimizes staff scheduling rotas for healthcare and care providers', 'category' => 'operations', 'industries' => ['healthcare'], 'risk_level' => 'low'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $t = $templates[$i % count($templates)];
            $slug = $i < count($templates) ? $t['slug'] : $t['slug'].'-'.$i;
            $skills[] = array_merge($t, [
                'slug' => $slug,
                'version' => '1.0.0',
                'author' => 'agentops',
                'file' => 'dist/'.$slug.'.skill',
                'checksum' => 'sha256:'.hash('sha256', $slug),
            ]);
        }

        return ['version' => '1.0.0', 'generated_at' => '2026-03-07T00:00:00Z', 'skills' => $skills];
    }

    // ---------------------------------------------------------------
    // Core functionality
    // ---------------------------------------------------------------

    public function test_reads_manifest_successfully(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $result = $library->browse();

        $this->assertCount(3, $result);
        $this->assertContainsOnlyInstancesOf(SkillLibraryEntry::class, $result);
    }

    public function test_search_filters_by_name_and_description(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $result = $library->search('compliance');

        $this->assertTrue($result->count() >= 1);
        $this->assertTrue($result->contains(fn (SkillLibraryEntry $e) => $e->slug === 'financial-compliance-check'));
    }

    public function test_browse_filters_by_category(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $result = $library->browse(['category' => 'compliance']);

        $this->assertCount(1, $result);
        $this->assertSame('financial-compliance-check', $result->first()->slug);
    }

    public function test_browse_filters_by_industry(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $result = $library->browse(['industry' => 'financial-services']);

        $this->assertTrue($result->count() >= 1);
        $this->assertTrue($result->contains(fn (SkillLibraryEntry $e) => $e->slug === 'financial-compliance-check'));
    }

    public function test_browse_filters_by_risk_level(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $result = $library->browse(['risk_level' => 'elevated']);

        $this->assertCount(1, $result);
        $this->assertSame('financial-compliance-check', $result->first()->slug);
    }

    public function test_find_returns_single_entry(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $entry = $library->find('pii-detector-redactor');

        $this->assertNotNull($entry);
        $this->assertInstanceOf(SkillLibraryEntry::class, $entry);
        $this->assertSame('pii-detector-redactor', $entry->slug);
        $this->assertSame('PII Detector & Redactor', $entry->name);
    }

    public function test_find_returns_null_for_unknown_slug(): void
    {
        $this->writeManifest($this->sampleManifest(3));
        $library = $this->makeLibrary();

        $entry = $library->find('nonexistent-skill');

        $this->assertNull($entry);
    }

    public function test_handles_missing_manifest_gracefully(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Skill library manifest not found'));

        $library = $this->makeLibrary('/nonexistent/path/manifest.json');

        $result = $library->browse();

        $this->assertCount(0, $result);
    }

    public function test_handles_malformed_manifest_gracefully(): void
    {
        file_put_contents($this->manifestPath, '{invalid json!!!');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Failed to parse skill library manifest'));

        $library = $this->makeLibrary();

        $result = $library->browse();

        $this->assertCount(0, $result);
    }

    public function test_caches_manifest_with_mtime_invalidation(): void
    {
        $this->writeManifest($this->sampleManifest(2));
        $library = $this->makeLibrary();

        $first = $library->browse();
        $this->assertCount(2, $first);

        // Write new manifest with more skills - but same mtime initially
        $this->writeManifest($this->sampleManifest(3));

        // Touch the file to update mtime (ensure different timestamp)
        touch($this->manifestPath, time() + 10);
        clearstatcache(true, $this->manifestPath);

        $second = $library->browse();
        $this->assertCount(3, $second);
    }

    // ---------------------------------------------------------------
    // Real manifest validation (requires manifest.json to exist)
    // ---------------------------------------------------------------

    public function test_manifest_has_expected_skills(): void
    {
        $realManifestPath = base_path('skill-library/manifest.json');
        if (! file_exists($realManifestPath)) {
            $this->markTestSkipped('Real manifest not yet generated');
        }

        $library = $this->makeLibrary($realManifestPath);
        $result = $library->browse();

        $this->assertCount(1, $result);
    }

    public function test_all_manifest_entries_have_required_fields(): void
    {
        $realManifestPath = base_path('skill-library/manifest.json');
        if (! file_exists($realManifestPath)) {
            $this->markTestSkipped('Real manifest not yet generated');
        }

        $library = $this->makeLibrary($realManifestPath);
        $entries = $library->browse();

        foreach ($entries as $entry) {
            $this->assertNotEmpty($entry->slug, 'Entry missing slug');
            $this->assertNotEmpty($entry->name, "Entry {$entry->slug} missing name");
            $this->assertNotEmpty($entry->description, "Entry {$entry->slug} missing description");
            $this->assertNotEmpty($entry->version, "Entry {$entry->slug} missing version");
            $this->assertNotEmpty($entry->author, "Entry {$entry->slug} missing author");
            $this->assertNotEmpty($entry->category, "Entry {$entry->slug} missing category");
            $this->assertNotEmpty($entry->riskLevel, "Entry {$entry->slug} missing risk_level");
            $this->assertNotEmpty($entry->filePath, "Entry {$entry->slug} missing file");
            $this->assertNotEmpty($entry->checksum, "Entry {$entry->slug} missing checksum");
        }
    }
}
