<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DocsCoverageGateTest extends TestCase
{
    use RefreshDatabase;

    private string $repoDocsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoDocsRoot = storage_path('framework/testing/docs-coverage-gate');
        File::deleteDirectory($this->repoDocsRoot);
        File::ensureDirectoryExists($this->repoDocsRoot);

        config()->set('documentation.paths.product', $this->repoDocsRoot.'/product');
        config()->set('documentation.paths.api', $this->repoDocsRoot.'/api');
        config()->set('documentation.paths.tooltips', $this->repoDocsRoot.'/tooltips');

        File::ensureDirectoryExists(config('documentation.paths.product'));
        File::ensureDirectoryExists(config('documentation.paths.api'));
        File::ensureDirectoryExists(config('documentation.paths.tooltips'));

        config()->set('docs_coverage', [
            'required_locale' => 'en',
            'required_entry_status' => 'published',
            'required_content_markers' => [
                'settings' => ['## Settings', '### Settings'],
                'example' => ['## Example', '### Example'],
                'troubleshooting' => ['## Troubleshooting', '### Troubleshooting'],
            ],
            'surfaces' => [
                [
                    'id' => 'dashboard',
                    'label' => 'Dashboard',
                    'required_routes' => ['dashboard'],
                    'required_settings' => ['dashboard.default_range'],
                    'required_tooltip_ui_keys' => ['dashboard.overview'],
                ],
                [
                    'id' => 'jobs',
                    'label' => 'Jobs',
                    'required_routes' => ['agent.jobs.index'],
                    'required_settings' => ['jobs.default_page_size'],
                    'required_tooltip_ui_keys' => ['jobs.overview'],
                ],
            ],
            'critical_routes' => [
                'dashboard',
                'agent.jobs.index',
            ],
        ]);
    }

    public function test_docs_coverage_fail_on_missing_blocks_when_surface_lacks_strict_sections_or_tooltip_links(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Dashboard summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
            ]),
            <<<'MD'
# Dashboard Overview

## Settings

This section explains dashboard settings.
MD
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.latency',
                'short_text' => 'Latency summary.',
                'severity' => 'info',
                'links' => [
                    ['label' => 'Laravel', 'url' => 'https://laravel.com/docs/12.x'],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:coverage', ['--fail-on-missing' => true])
            ->expectsOutputToContain('Dashboard')
            ->expectsOutputToContain('example')
            ->expectsOutputToContain('troubleshooting')
            ->expectsOutputToContain('tooltip ui_key')
            ->assertExitCode(1);
    }

    public function test_docs_coverage_passes_when_surface_meets_overview_settings_example_troubleshooting_and_tooltip_requirements(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Dashboard summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
            ]),
            <<<'MD'
# Dashboard Overview

## Settings

Configure dashboard time windows and filters.

## Example

Use the 24h preset to compare recent run volume.

## Troubleshooting

If metrics appear stale, refresh and verify queue workers are online.
MD
        );

        $this->writeMarkdownDoc(
            'api/jobs/list-jobs.md',
            $this->frontMatter([
                'slug' => 'jobs-list-api',
                'title' => 'Jobs API Overview',
                'summary' => 'Jobs listing details',
                'section' => 'api-jobs',
                'route_names' => ['agent.jobs.index'],
                'setting_keys' => ['jobs.default_page_size'],
            ]),
            <<<'MD'
# Jobs API Overview

## Settings

Configure page size defaults for listings.

## Example

Call `/agent/api/v1/jobs` with pagination query params.

## Troubleshooting

If no jobs are returned, verify authentication and filters.
MD
        );

        $this->writeTooltipFile('tooltips/docs.yaml', [
            [
                'ui_key' => 'dashboard.overview',
                'short_text' => 'Dashboard helper.',
                'severity' => 'info',
                'learn_more_slug' => 'dashboard-overview',
                'links' => [
                    ['label' => 'Laravel', 'url' => 'https://laravel.com/docs/12.x'],
                ],
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
            [
                'ui_key' => 'jobs.overview',
                'short_text' => 'Jobs helper.',
                'severity' => 'info',
                'learn_more_slug' => 'jobs-list-api',
                'links' => [
                    ['label' => 'Laravel', 'url' => 'https://laravel.com/docs/12.x'],
                ],
                'route_names' => ['agent.jobs.index'],
                'setting_keys' => ['jobs.default_page_size'],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:coverage', ['--fail-on-missing' => true])
            ->expectsOutputToContain('Coverage: 100.00%')
            ->assertSuccessful();
    }

    public function test_docs_validate_reports_orphaned_links_and_missing_critical_route_docs(): void
    {
        $this->writeMarkdownDoc(
            'product/dashboard/overview.md',
            $this->frontMatter([
                'slug' => 'dashboard-overview',
                'title' => 'Dashboard Overview',
                'summary' => 'Dashboard summary',
                'section' => 'dashboard',
                'route_names' => ['dashboard'],
                'setting_keys' => ['dashboard.default_range'],
            ]),
            <<<'MD'
# Dashboard Overview

## Settings

This section exists.

## Example

This section exists.

## Troubleshooting

This section exists.
MD
        );

        $this->writeTooltipFile('tooltips/dashboard.yaml', [
            [
                'ui_key' => 'dashboard.latency',
                'short_text' => 'Dashboard latency helper.',
                'severity' => 'warning',
                'learn_more_slug' => 'missing-entry',
                'links' => [
                    ['label' => 'Laravel', 'url' => 'https://laravel.com/docs/12.x'],
                ],
                'metadata' => [
                    'owner' => 'docs-team',
                    'locale' => 'en',
                ],
            ],
        ]);

        $this->artisan('docs:validate')
            ->expectsOutputToContain('Docs validation failed.')
            ->expectsOutputToContain('learn_more_slug')
            ->expectsOutputToContain('dashboard.overview')
            ->expectsOutputToContain('agent.jobs.index')
            ->assertExitCode(1);
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
