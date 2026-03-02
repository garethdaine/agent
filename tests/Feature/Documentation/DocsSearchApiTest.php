<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\User;
use App\Support\Documentation\DocsSearchService;
use App\Support\Documentation\DocsSearchUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DocsSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_api_returns_required_payload_fields(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(DocsSearchService::class);
        $service->shouldReceive('search')
            ->once()
            ->with('agent', 'product_doc', 'general', 'agent.jobs.index', 10)
            ->andReturn([
                [
                    'title' => 'Agent Jobs Overview',
                    'snippet' => 'Create and manage scheduled agent jobs.',
                    'domain' => 'product_doc',
                    'section' => 'general',
                    'route_affinity' => true,
                    'updated_at' => '2026-03-02T12:00:00Z',
                ],
            ]);

        $this->app->instance(DocsSearchService::class, $service);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?q=agent&domain=product_doc&section=general&route=agent.jobs.index&limit=10')
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.title', 'Agent Jobs Overview')
            ->assertJsonPath('data.0.snippet', 'Create and manage scheduled agent jobs.')
            ->assertJsonPath('data.0.domain', 'product_doc')
            ->assertJsonPath('data.0.section', 'general')
            ->assertJsonPath('data.0.route_affinity', true)
            ->assertJsonPath('data.0.updated_at', '2026-03-02T12:00:00Z');
    }

    public function test_search_api_returns_unavailable_contract_when_typesense_is_down(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(DocsSearchService::class);
        $service->shouldReceive('search')
            ->once()
            ->andThrow(new DocsSearchUnavailableException('Typesense unavailable'));

        $this->app->instance(DocsSearchService::class, $service);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?q=agent')
            ->assertStatus(503)
            ->assertJsonPath('error.message', 'search temporarily unavailable')
            ->assertJsonMissingPath('data');
    }

    public function test_search_api_rejects_empty_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?q=%20%20%20')
            ->assertStatus(422);
    }

    public function test_search_api_rejects_invalid_domain_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/search?q=agent&domain=unknown')
            ->assertStatus(422);
    }
}
