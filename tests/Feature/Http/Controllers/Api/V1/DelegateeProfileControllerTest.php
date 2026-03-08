<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('delegation.enabled', true);
    }

    public function test_capabilities_returns_active_capabilities(): void
    {
        DelegationCapability::query()->delete();

        $user = User::factory()->create();
        $active = DelegationCapability::factory()->create(['slug' => 'code_execution', 'name' => 'Code Execution']);
        DelegationCapability::factory()->inactive()->create(['slug' => 'inactive_cap', 'name' => 'Inactive']);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/capabilities');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.slug', 'code_execution')
            ->assertJsonPath('data.0.name', $active->name);
    }

    public function test_index_returns_user_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userProfile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $otherProfile = DelegateeProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $userProfile->id)
            ->assertJsonMissing(['id' => $otherProfile->id]);
    }

    public function test_index_excludes_soft_deleted_by_default(): void
    {
        $user = User::factory()->create();
        $activeProfile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $deletedProfile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $deletedProfile->delete();

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProfile->id);
    }

    public function test_index_can_include_soft_deleted(): void
    {
        $user = User::factory()->create();
        $activeProfile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $deletedProfile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $deletedProfile->delete();

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles?deleted=all');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/delegation/delegatee-profiles', [
            'name' => 'Test Profile',
            'runner_type' => 'claude',
            'command_template' => '/usr/bin/claude --task {{task}}',
            'working_directory' => '/tmp/test',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Profile')
            ->assertJsonPath('data.runner_type', 'claude')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('delegatee_profiles', [
            'user_id' => $user->id,
            'name' => 'Test Profile',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/delegation/delegatee-profiles', [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_show_returns_profile_with_metrics(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $profile->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'runner_type',
                    'command_template',
                    'working_directory',
                    'is_active',
                ],
            ]);
    }

    public function test_show_denies_other_user_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}");

        $response->assertNotFound();
    }

    public function test_update_updates_profile(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->putJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}", [
            'name' => 'Updated Name',
            'runner_type' => 'codex',
            'command_template' => '/usr/bin/codex --task {{task}}',
            'working_directory' => '/tmp/updated',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.runner_type', 'codex')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_denies_other_user_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->putJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_soft_deletes_profile(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('delegatee_profiles', ['id' => $profile->id]);
    }

    public function test_destroy_denies_other_user_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}");

        $response->assertNotFound();
    }

    public function test_restore_restores_soft_deleted_profile(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id]);
        $profile->delete();

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/restore");

        $response->assertOk();
        $this->assertNull($profile->fresh()->deleted_at);
    }

    public function test_restore_denies_other_user_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $otherUser->id]);
        $profile->delete();

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}/restore");

        $response->assertNotFound();
    }

    public function test_feature_gate_blocks_when_disabled(): void
    {
        config()->set('delegation.enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
