<?php

declare(strict_types=1);

namespace Tests\Feature\AgentUi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OperatorRouteReachabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_operator_routes(): void
    {
        $routes = [
            '/agent/deployments',
            '/agent/system-overview',
            '/agent/escalations',
            '/agent/budgets',
            '/agent/replay-builds',
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_reach_operator_routes_with_expected_components(): void
    {
        $user = User::factory()->create();

        $expectedComponentsByRoute = [
            '/agent/deployments' => 'Agent/Deployments/Index',
            '/agent/system-overview' => 'Agent/SystemOverview/Show',
            '/agent/escalations' => 'Agent/Escalations/Index',
            '/agent/budgets' => 'Agent/Budgets/Index',
            '/agent/replay-builds' => 'Agent/ReplayBuilds/Index',
        ];

        foreach ($expectedComponentsByRoute as $route => $component) {
            $this->actingAs($user)
                ->get($route)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
        }
    }
}
