<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Org;

use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\OrgAgentProfile;
use App\Models\User;
use Tests\TestCase;

class OrgAgentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.org.enabled' => true]);
    }

    public function test_index_returns_user_profiles(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->count(3)->forUser($user)->create();
        OrgAgentProfile::factory()->create(); // Another user's profile

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/agents');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_excludes_archived_by_default(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create();
        OrgAgentProfile::factory()->forUser($user)->create(['archived_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/agents');

        $response->assertJsonCount(1, 'data');
    }

    public function test_index_includes_archived_when_requested(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create();
        OrgAgentProfile::factory()->forUser($user)->create(['archived_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/agents?include_archived=true');

        $response->assertJsonCount(2, 'data');
    }

    public function test_store_creates_profile(): void
    {
        $user = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($user)->create();

        // Attach capabilities to the delegatee profile - use firstOrCreate to avoid unique constraint issues
        $capability = DelegationCapability::firstOrCreate(
            ['slug' => 'test_read_cap'],
            ['name' => 'Test Read Capability', 'description' => 'Test capability', 'is_active' => true]
        );
        $delegatee->capabilities()->attach($capability->id);

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/agents', [
                'name' => 'test-agent',
                'role_slug' => 'developer',
                'role_description' => 'Handles development tasks',
                'delegatee_profile_id' => $delegatee->id,
                'capability_bindings' => ['test_read_cap'],
                'authority_overrides' => [],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'test-agent');
    }

    public function test_store_rejects_invalid_delegatee(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $delegatee = DelegateeProfile::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/agents', [
                'name' => 'test-agent',
                'role_slug' => 'developer',
                'role_description' => 'Test',
                'delegatee_profile_id' => $delegatee->id,
                'capability_bindings' => [],
                'authority_overrides' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_destroy_archives_profile(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/agent/api/v1/org/agents/{$profile->id}");

        $response->assertStatus(200);
        $this->assertNotNull($profile->fresh()->archived_at);
    }

    public function test_restore_unarchives_profile(): void
    {
        $user = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($user)->create([
            'archived_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/org/agents/{$profile->id}/restore");

        $response->assertStatus(200);
        $this->assertNull($profile->fresh()->archived_at);
    }

    public function test_policy_denies_access_to_other_user_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = OrgAgentProfile::factory()->forUser($otherUser)->create();

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/org/agents/{$profile->id}");

        $response->assertStatus(403);
    }
}
