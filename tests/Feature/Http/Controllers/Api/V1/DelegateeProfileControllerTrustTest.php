<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DelegateeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeProfileControllerTrustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('delegation.enabled', true);
    }

    public function test_trust_endpoint_returns_score_with_components(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'runner_type' => 'claude',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/trust");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['score', 'confidence', 'components', 'sample_size', 'source'],
            ]);
    }

    public function test_trust_endpoint_returns_default_for_new_profile(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'runner_type' => 'new_runner',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/trust");

        $response->assertStatus(200)
            ->assertJson(['data' => ['score' => 0.5, 'source' => 'default']]);
    }

    public function test_trust_endpoint_requires_auth(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'runner_type' => 'claude',
        ]);

        $response = $this->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/trust");

        $response->assertStatus(401);
    }

    public function test_trust_endpoint_returns_403_for_other_users_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($otherUser)->create([
            'runner_type' => 'claude',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/trust");

        $response->assertStatus(403);
    }
}
