<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.show', ['slug' => 'overview']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/Show')
                ->where('entry.slug', 'overview')
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

