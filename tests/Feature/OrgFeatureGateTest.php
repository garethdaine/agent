<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class OrgFeatureGateTest extends TestCase
{
    public function test_org_endpoints_return_404_when_disabled(): void
    {
        config(['agent.org.enabled' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/agents');

        $response->assertStatus(404)
            ->assertJson(['error' => ['code' => 'FEATURE_DISABLED']]);
    }

    public function test_org_endpoints_accessible_when_enabled(): void
    {
        config(['agent.org.enabled' => true]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/agents');

        $response->assertStatus(200);
    }
}
