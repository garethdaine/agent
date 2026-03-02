<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $repoDocsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoDocsRoot = storage_path('framework/testing/docs-generate-command');
        File::deleteDirectory($this->repoDocsRoot);
        File::ensureDirectoryExists($this->repoDocsRoot);

        config()->set('documentation.paths.product', $this->repoDocsRoot.'/product');
        config()->set('documentation.paths.api', $this->repoDocsRoot.'/api');
        config()->set('documentation.paths.tooltips', $this->repoDocsRoot.'/tooltips');
        config()->set('documentation.generation.output_paths.api_route_inventory', $this->repoDocsRoot.'/generated/agent-v1-route-inventory.md');
        config()->set('documentation.generation.output_paths.api_surface_reference', $this->repoDocsRoot.'/generated/agent-v1-surface-reference.md');
        config()->set('documentation.generation.output_paths.interface_surface_coverage', $this->repoDocsRoot.'/generated/surface-coverage.md');

        File::ensureDirectoryExists(config('documentation.paths.product'));
        File::ensureDirectoryExists(config('documentation.paths.api'));
        File::ensureDirectoryExists(config('documentation.paths.tooltips'));
    }

    public function test_generate_updates_runtime_snapshot_block_and_writes_reference_artifacts(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Dashboard docs summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
            ]),
            <<<'MD'
# Dashboard Overview

Existing docs body.

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

Outdated snapshot data.
<!-- AUTO-GENERATED:END -->
MD
        );

        $this->artisan('docs:generate', ['--source' => 'repo'])
            ->expectsOutputToContain('Docs generation completed.')
            ->assertSuccessful();

        $dashboardDoc = (string) File::get($this->repoDocsRoot.'/product/dashboard/overview.md');
        $this->assertStringContainsString('<!-- AUTO-GENERATED:START -->', $dashboardDoc);
        $this->assertStringContainsString('## Runtime Contract Snapshot', $dashboardDoc);
        $this->assertStringContainsString('`dashboard` | ok | `dashboard` | `GET`', $dashboardDoc);
        $this->assertStringNotContainsString('Outdated snapshot data.', $dashboardDoc);

        $this->assertFileExists($this->repoDocsRoot.'/generated/agent-v1-route-inventory.md');
        $this->assertFileExists($this->repoDocsRoot.'/generated/agent-v1-surface-reference.md');
        $this->assertFileExists($this->repoDocsRoot.'/generated/surface-coverage.md');
    }

    public function test_generate_rejects_unsupported_source(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Dashboard docs summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
            ]),
            "# Dashboard Overview\n"
        );

        $this->artisan('docs:generate', ['--source' => 'database'])
            ->expectsOutputToContain('Docs generation failed.')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function writeMarkdownDoc(string $relativePath, array $frontMatter, string $body): void
    {
        $absolutePath = $this->repoDocsRoot.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($absolutePath));

        $yaml = Yaml::dump($frontMatter, 4, 2);
        File::put($absolutePath, "---\n{$yaml}---\n\n{$body}\n");
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function frontMatter(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'dashboard-overview',
            'title' => 'Dashboard Overview',
            'summary' => 'Dashboard docs summary.',
            'section' => 'dashboard',
            'audience' => 'operator',
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['dashboard'],
            'owner' => 'docs-team',
            'route_names' => ['dashboard'],
            'setting_keys' => ['dashboard.default_range'],
            'feature_flags' => ['docs_center_enabled'],
            'locale' => 'en',
            'reviewed_at' => '2026-03-02',
        ], $overrides);
    }
}
