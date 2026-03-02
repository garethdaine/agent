<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\DocumentationEntry;
use App\Models\User;
use App\Support\Documentation\DocsCatalog;
use App\Support\Documentation\DocsRuntimeBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

class DocsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_reach_docs_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Index')
                ->has('entries')
            );
    }

    public function test_docs_navigation_entry_exists_in_primary_layout_and_points_to_docs_route(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertIsString($layout);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($layout, "route('docs.index')"),
            'Docs link should exist in both desktop and responsive navigation.'
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_open_docs_slug_page(): void
    {
        $indexTemplate = file_get_contents(resource_path('js/Pages/Docs/Index.vue'));

        $this->assertIsString($indexTemplate);
        $this->assertStringContainsString(
            'docsShowHref(entry.slug)',
            $indexTemplate,
            'Docs index must link to the docs.show route using the entry slug.'
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.show', ['slug' => 'overview']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Show')
                ->where('entry.slug', 'overview')
            );
    }

    public function test_docs_pages_prefer_runtime_documentation_entries_over_static_catalog(): void
    {
        $entry = DocumentationEntry::query()->create([
            'domain' => 'product_doc',
            'slug' => 'runtime-doc',
            'locale' => 'en',
            'title' => 'Runtime Documentation',
            'summary' => 'Loaded from runtime docs table.',
            'section' => 'runtime',
            'status' => 'published',
            'body_markdown' => '# Runtime',
            'body_html' => '<h1>Runtime</h1>',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Index')
                ->where('entries', fn ($entries): bool => collect($entries)
                    ->contains(fn (array $item): bool => (string) ($item['slug'] ?? '') === (string) $entry->slug))
            );

        $this->actingAs($user)
            ->get(route('docs.show', ['slug' => 'runtime-doc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Show')
                ->where('entry.slug', 'runtime-doc')
                ->where('entry.body_html', '<h1>Runtime</h1>')
            );
    }

    public function test_docs_index_handles_empty_dataset_without_error(): void
    {
        $bootstrap = Mockery::mock(DocsRuntimeBootstrapService::class);
        $bootstrap->shouldReceive('ensureRuntimeDocsAvailable')->once();
        $this->app->instance(DocsRuntimeBootstrapService::class, $bootstrap);

        $catalog = Mockery::mock(DocsCatalog::class);
        $catalog->shouldReceive('search')
            ->once()
            ->with('', '', '', 200)
            ->andReturn([]);
        $catalog->shouldReceive('findEntry')
            ->once()
            ->with('')
            ->andReturn(null);
        $this->app->instance(DocsCatalog::class, $catalog);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Index')
                ->where('entries', [])
            );
    }

    public function test_unknown_docs_slug_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.show', ['slug' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_docs_routes(): void
    {
        $this->get(route('docs.index'))->assertRedirect('/login');
        $this->get(route('docs.show', ['slug' => 'overview']))->assertRedirect('/login');
    }
}
