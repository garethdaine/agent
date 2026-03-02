<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_docs_web_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/docs')
            ->assertOk();

        $this->actingAs($user)
            ->get('/docs/overview')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_docs_web_pages(): void
    {
        $this->get('/docs')->assertRedirect('/login');
        $this->get('/docs/overview')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_docs_api_read_endpoints_with_proper_role_for_coverage(): void
    {
        $user = User::factory()->create();
        config()->set('agent.roles.admin_user_ids', [$user->id]);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?q=agent')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/fragments/docs.overview')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/coverage')
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_docs_api_read_endpoints(): void
    {
        $this->getJson('/agent/api/v1/docs/search?q=agent')->assertUnauthorized();
        $this->getJson('/agent/api/v1/docs/fragments/docs.overview')->assertUnauthorized();
        $this->getJson('/agent/api/v1/docs/coverage')->assertUnauthorized();
    }

    public function test_unauthorized_role_is_forbidden_from_docs_coverage_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/coverage')
            ->assertForbidden();
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/docs/slug-that-does-not-exist')
            ->assertNotFound();
    }

    public function test_unknown_ui_key_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/fragments/unknown.ui.key')
            ->assertNotFound();
    }

    public function test_malformed_search_query_params_return_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?limit=not-a-number')
            ->assertStatus(422);
    }

    public function test_docs_routes_expose_read_only_http_methods(): void
    {
        $docsRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $uri = ltrim($route->uri(), '/');

                return str_starts_with($uri, 'docs')
                    || str_starts_with($uri, 'agent/api/v1/docs');
            })
            ->values();

        $this->assertNotEmpty($docsRoutes, 'Expected docs routes to be registered.');

        $mutatingMethods = $docsRoutes
            ->flatMap(fn ($route) => $route->methods())
            ->reject(fn (string $method) => in_array($method, ['GET', 'HEAD'], true))
            ->unique()
            ->values()
            ->all();

        $this->assertSame([], $mutatingMethods, 'Docs routes must be read-only.');
    }
}
