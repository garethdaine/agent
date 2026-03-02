<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use App\Models\DocumentationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $repoDocsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoDocsRoot = storage_path('framework/testing/docs-sync-command');
        File::deleteDirectory($this->repoDocsRoot);
        File::ensureDirectoryExists($this->repoDocsRoot);

        config()->set('documentation.paths.product', $this->repoDocsRoot.'/product');
        config()->set('documentation.paths.api', $this->repoDocsRoot.'/api');
        config()->set('documentation.paths.tooltips', $this->repoDocsRoot.'/tooltips');

        File::ensureDirectoryExists(config('documentation.paths.product'));
        File::ensureDirectoryExists(config('documentation.paths.api'));
        File::ensureDirectoryExists(config('documentation.paths.tooltips'));

        File::delete(storage_path('app/docs-sync/manifest.json'));
    }

    public function test_repo_wins_over_existing_slug_and_ui_key_and_updates_checksums(): void
    {
        $existingEntry = DocumentationEntry::query()->create([
            'domain' => 'product_doc',
            'slug' => 'dashboard-overview',
            'locale' => 'en',
            'title' => 'Old Dashboard Overview',
            'summary' => 'Old stale summary',
            'section' => 'dashboard',
            'audience' => 'operator',
            'status' => 'published',
            'version' => '0.9.0',
            'tags' => ['dashboard'],
            'owner' => 'old-owner',
            'body_markdown' => '# Old',
            'body_html' => '<h1>Old</h1>',
            'source_path' => 'legacy/dashboard.md',
            'source_checksum' => str_repeat('a', 64),
        ]);

        DocumentationFragment::query()->create([
            'ui_key' => 'dashboard.load_time',
            'locale' => 'en',
            'short_text' => 'Old tooltip',
            'long_text' => 'Old tooltip details',
            'learn_more_entry_id' => null,
            'severity' => 'info',
            'feature_flag' => null,
            'status' => 'published',
            'route_names' => ['legacy.route'],
            'setting_keys' => ['legacy.setting'],
            'source_path' => 'legacy/tooltips.yaml',
            'source_checksum' => str_repeat('b', 64),
        ]);

        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Fresh summary from repo',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard Overview\n\nUpdated body from repository."
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Shows recent dashboard load-time trend.',
                'long_text' => 'Expanded helper copy.',
                'severity' => 'warning',
                'learn_more_slug' => 'dashboard-overview',
                'links' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs/12.x',
                    ],
                ],
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertSuccessful();

        $existingEntry->refresh();

        $this->assertSame('Fresh summary from repo', $existingEntry->summary);
        $this->assertSame('product/dashboard/overview.md', $existingEntry->source_path);
        $this->assertNotSame(str_repeat('a', 64), $existingEntry->source_checksum);

        $fragment = DocumentationFragment::query()
            ->where('ui_key', 'dashboard.load_time')
            ->firstOrFail();

        $this->assertSame('Shows recent dashboard load-time trend.', $fragment->short_text);
        $this->assertSame($existingEntry->id, $fragment->learn_more_entry_id);
        $this->assertNotSame(str_repeat('b', 64), (string) $fragment->source_checksum);
    }

    public function test_sync_upserts_links_and_replaces_stale_links(): void
    {
        $entry = DocumentationEntry::query()->create([
            'domain' => 'product_doc',
            'slug' => 'dashboard-overview',
            'locale' => 'en',
            'title' => 'Dashboard Overview',
            'summary' => 'Old summary',
            'section' => 'dashboard',
            'audience' => 'operator',
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['dashboard'],
            'owner' => 'docs-team',
            'body_markdown' => '# Old',
            'body_html' => '<h1>Old</h1>',
            'source_path' => 'legacy/dashboard.md',
            'source_checksum' => str_repeat('c', 64),
        ]);

        $fragment = DocumentationFragment::query()->create([
            'ui_key' => 'dashboard.load_time',
            'locale' => 'en',
            'short_text' => 'Old tooltip',
            'long_text' => null,
            'learn_more_entry_id' => $entry->id,
            'severity' => 'info',
            'feature_flag' => null,
            'status' => 'published',
            'route_names' => null,
            'setting_keys' => null,
            'source_path' => 'legacy/tooltips.yaml',
            'source_checksum' => str_repeat('d', 64),
        ]);

        DocumentationLink::query()->create([
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => null,
            'route_name' => 'legacy.route',
            'setting_key' => null,
            'feature_flag' => null,
        ]);

        DocumentationLink::query()->create([
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => $fragment->id,
            'route_name' => null,
            'setting_key' => 'legacy.setting',
            'feature_flag' => null,
        ]);

        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Current summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard', 'agent.monitor.index'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard\n\nCurrent docs."
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Latency helper',
                'severity' => 'warning',
                'learn_more_slug' => 'dashboard-overview',
                'links' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs/12.x',
                    ],
                ],
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertSuccessful();

        $entry = DocumentationEntry::query()
            ->where('domain', 'product_doc')
            ->where('slug', 'dashboard-overview')
            ->firstOrFail();
        $fragment = DocumentationFragment::query()
            ->where('ui_key', 'dashboard.load_time')
            ->firstOrFail();

        $this->assertDatabaseMissing('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => null,
            'route_name' => 'legacy.route',
        ]);
        $this->assertDatabaseMissing('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => $fragment->id,
            'setting_key' => 'legacy.setting',
        ]);

        $this->assertDatabaseHas('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => null,
            'route_name' => 'dashboard',
            'setting_key' => null,
            'feature_flag' => null,
        ]);
        $this->assertDatabaseHas('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => null,
            'route_name' => null,
            'setting_key' => 'dashboard.default_range',
            'feature_flag' => null,
        ]);
        $this->assertDatabaseHas('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => null,
            'route_name' => null,
            'setting_key' => null,
            'feature_flag' => 'docs_center_enabled',
        ]);
        $this->assertDatabaseHas('documentation_links', [
            'documentation_entry_id' => $entry->id,
            'documentation_fragment_id' => $fragment->id,
            'route_name' => 'dashboard',
            'setting_key' => null,
            'feature_flag' => null,
        ]);
    }

    public function test_sync_writes_deterministic_manifest_and_is_idempotent_on_rerun(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard\n\nBody"
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Latency helper',
                'severity' => 'info',
                'learn_more_slug' => 'dashboard-overview',
                'links' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs/12.x',
                    ],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertSuccessful();

        $manifestPath = storage_path('app/docs-sync/manifest.json');
        $this->assertFileExists($manifestPath);
        $firstManifest = File::get($manifestPath);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertSuccessful();

        $secondManifest = File::get($manifestPath);
        $this->assertJsonStringEqualsJsonString($firstManifest, $secondManifest);
        $this->assertSame($firstManifest, $secondManifest);
    }

    public function test_sync_fails_when_tooltip_references_unresolved_learn_more_slug(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard\n\nBody"
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Latency helper',
                'severity' => 'info',
                'learn_more_slug' => 'missing-entry',
                'links' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs/12.x',
                    ],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertFailed();

        $this->assertDatabaseCount('documentation_entries', 0);
        $this->assertDatabaseCount('documentation_fragments', 0);
    }

    public function test_sync_fails_when_duplicate_slug_or_ui_key_exists_in_repo_files(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Dashboard\n\nBody"
        );

        $this->writeMarkdownDoc(
            'product/monitor/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Monitor Overview',
                'summary' => 'Summary',
                'section' => 'monitor',
                'route_names' => ['agent.monitor.index'],
                'setting_keys' => ['monitor.refresh_interval'],
                'feature_flags' => ['docs_center_enabled'],
            ]),
            "# Monitor\n\nBody"
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Latency helper',
                'severity' => 'info',
                'links' => [
                    [
                        'label' => 'Laravel Docs',
                        'url' => 'https://laravel.com/docs/12.x',
                    ],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->writeTooltipFile('tooltips/duplicates.yaml', [
            [
                'ui_key' => 'dashboard.load_time',
                'short_text' => 'Another helper',
                'severity' => 'warning',
                'links' => [
                    [
                        'label' => 'Typesense Docs',
                        'url' => 'https://typesense.org/docs/',
                    ],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:sync', ['--mode' => 'commit', '--source' => 'repo'])
            ->assertFailed();

        $this->assertDatabaseCount('documentation_entries', 0);
        $this->assertDatabaseCount('documentation_fragments', 0);
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function writeMarkdownDoc(string $relativePath, array $frontMatter, string $body): void
    {
        $absolutePath = $this->repoDocsRoot.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($absolutePath));

        $yaml = Yaml::dump($frontMatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        File::put($absolutePath, "---\n{$yaml}---\n{$body}\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $fragments
     */
    private function writeTooltipFile(string $relativePath, array $fragments): void
    {
        $absolutePath = $this->repoDocsRoot.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, Yaml::dump($fragments, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function frontMatter(array $overrides): array
    {
        return array_merge([
            'slug' => 'doc-slug',
            'title' => 'Doc Title',
            'summary' => 'Doc summary',
            'section' => 'general',
            'audience' => 'operator',
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['docs'],
            'owner' => 'docs-team',
            'route_names' => ['dashboard'],
            'setting_keys' => ['dashboard.default_range'],
            'feature_flags' => ['docs_center_enabled'],
            'locale' => 'en',
            'reviewed_at' => '2026-03-01',
        ], $overrides);
    }
}
