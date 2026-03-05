<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class OrgUiRoutesTest extends TestCase
{
    public function test_org_routes_return_404_when_disabled(): void
    {
        config(['agent.org.enabled' => false]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org');

        $response->assertStatus(404);
    }

    public function test_org_index_accessible_when_enabled(): void
    {
        config(['agent.org.enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Agent/Org/Index'));
    }

    public function test_org_agents_list_accessible(): void
    {
        config(['agent.org.enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org/agents');

        $response->assertStatus(200);
    }

    public function test_org_rituals_list_accessible(): void
    {
        config(['agent.org.enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org/rituals');

        $response->assertStatus(200);
    }

    public function test_navigation_includes_org_link_when_enabled(): void
    {
        config(['agent.org.enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page->has('orgLayerEnabled')
            ->where('orgLayerEnabled', true)
        );
    }

    public function test_org_layer_builder_accessible_when_enabled(): void
    {
        config(['agent.org.enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org/builder');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Agent/Org/OrgLayerBuilder'));
    }

    public function test_org_layer_builder_returns_404_when_disabled(): void
    {
        config(['agent.org.enabled' => false]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/org/builder');

        $response->assertNotFound();
    }
}
