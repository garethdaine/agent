<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrgMigrationTest extends TestCase
{
    public function test_org_agent_profiles_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('org_agent_profiles'));
        $this->assertTrue(Schema::hasColumns('org_agent_profiles', [
            'id', 'user_id', 'name', 'role_slug', 'role_description',
            'delegatee_profile_id', 'capability_bindings', 'authority_overrides',
            'default_output_schema', 'parent_agent_id', 'archived_at',
            'created_at', 'updated_at',
        ]));
    }

    public function test_org_reporting_edges_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('org_reporting_edges'));
        $this->assertTrue(Schema::hasColumns('org_reporting_edges', [
            'id', 'subordinate_agent_id', 'manager_agent_id', 'user_id',
            'created_at', 'updated_at',
        ]));
    }

    public function test_unique_name_constraint_within_user_scope(): void
    {
        $user = \App\Models\User::factory()->create();
        $delegatee = \App\Models\DelegateeProfile::factory()->for($user)->create();

        \DB::table('org_agent_profiles')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('org_agent_profiles')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test2',
            'role_description' => 'Test2',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_archived_profiles_allow_duplicate_names(): void
    {
        $user = \App\Models\User::factory()->create();
        $delegatee = \App\Models\DelegateeProfile::factory()->for($user)->create();

        // Create an archived profile with a name
        \DB::table('org_agent_profiles')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Creating an active profile with the same name should succeed
        \DB::table('org_agent_profiles')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test2',
            'role_description' => 'Test2',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = \DB::table('org_agent_profiles')
            ->where('user_id', $user->id)
            ->where('name', 'test-agent')
            ->count();

        $this->assertEquals(2, $count);
    }

    public function test_subordinate_agent_can_only_have_one_manager(): void
    {
        $user = \App\Models\User::factory()->create();
        $delegatee = \App\Models\DelegateeProfile::factory()->for($user)->create();

        // Create three org agent profiles
        $subordinateId = \Str::uuid()->toString();
        $manager1Id = \Str::uuid()->toString();
        $manager2Id = \Str::uuid()->toString();

        foreach ([
            ['id' => $subordinateId, 'name' => 'subordinate'],
            ['id' => $manager1Id, 'name' => 'manager1'],
            ['id' => $manager2Id, 'name' => 'manager2'],
        ] as $profile) {
            \DB::table('org_agent_profiles')->insert([
                'id' => $profile['id'],
                'user_id' => $user->id,
                'name' => $profile['name'],
                'role_slug' => 'role',
                'role_description' => 'Description',
                'delegatee_profile_id' => $delegatee->id,
                'capability_bindings' => '[]',
                'authority_overrides' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // First reporting edge should succeed
        \DB::table('org_reporting_edges')->insert([
            'id' => \Str::uuid(),
            'subordinate_agent_id' => $subordinateId,
            'manager_agent_id' => $manager1Id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Second reporting edge for same subordinate should fail due to unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('org_reporting_edges')->insert([
            'id' => \Str::uuid(),
            'subordinate_agent_id' => $subordinateId,
            'manager_agent_id' => $manager2Id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleting_delegatee_profile_restricted_when_bound_to_org_agent(): void
    {
        $user = \App\Models\User::factory()->create();
        $delegatee = \App\Models\DelegateeProfile::factory()->for($user)->create();

        \DB::table('org_agent_profiles')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to delete the delegatee profile should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('delegatee_profiles')->where('id', $delegatee->id)->delete();
    }

    public function test_deleting_user_cascades_to_org_agent_profiles(): void
    {
        $user = \App\Models\User::factory()->create();
        $delegatee = \App\Models\DelegateeProfile::factory()->for($user)->create();

        $profileId = \Str::uuid()->toString();

        \DB::table('org_agent_profiles')->insert([
            'id' => $profileId,
            'user_id' => $user->id,
            'name' => 'test-agent',
            'role_slug' => 'test',
            'role_description' => 'Test',
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => '[]',
            'authority_overrides' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('org_agent_profiles', ['id' => $profileId]);

        // Delete the user - should cascade to org_agent_profiles
        $user->forceDelete();

        $this->assertDatabaseMissing('org_agent_profiles', ['id' => $profileId]);
    }
}
