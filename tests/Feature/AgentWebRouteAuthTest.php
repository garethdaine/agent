<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentWebRouteAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_agent_and_dashboard_pages(): void
    {
        $routes = [
            '/dashboard',
            '/agent/jobs',
            '/agent/jobs/create',
            '/agent/jobs/1/edit',
            '/agent/monitor',
            '/agent/system-overview',
        ];

        foreach ($routes as $route) {
            $this->get($route)
                ->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_access_agent_and_dashboard_pages(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user);

        $routes = [
            '/dashboard',
            '/agent/jobs',
            '/agent/jobs/create',
            '/agent/jobs/1/edit',
            '/agent/monitor',
            '/agent/system-overview',
        ];

        foreach ($routes as $route) {
            $this->get($route)
                ->assertOk();
        }
    }
}
