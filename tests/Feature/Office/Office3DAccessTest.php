<?php

declare(strict_types=1);

namespace Tests\Feature\Office;

use App\Models\User;
use Tests\TestCase;

class Office3DAccessTest extends TestCase
{
    public function test_office_route_returns_200_when_enabled(): void
    {
        config(['agent.office_3d_enabled' => true]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/office');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Agent/Office/AgentOffice'));
    }

    public function test_office_route_returns_404_when_disabled(): void
    {
        config(['agent.office_3d_enabled' => false]);
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($user)->get('/agent/office');

        $response->assertNotFound();
    }
}
