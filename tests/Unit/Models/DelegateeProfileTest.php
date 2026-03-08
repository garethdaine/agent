<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegateeCapabilityPivot;
use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $profile->user);
        $this->assertEquals($user->id, $profile->user->id);
    }

    public function test_has_one_metric(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $metric = DelegateeMetric::factory()->create(['delegatee_profile_id' => $profile->id]);

        $this->assertInstanceOf(DelegateeMetric::class, $profile->metric);
        $this->assertEquals($metric->id, $profile->metric->id);
    }

    public function test_belongs_to_many_capabilities(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $capability1 = DelegationCapability::factory()->create();
        $capability2 = DelegationCapability::factory()->create();

        $profile->capabilities()->attach([$capability1->id, $capability2->id]);

        $profile->refresh();
        $this->assertCount(2, $profile->capabilities);
        $this->assertTrue($profile->capabilities->contains($capability1));
        $this->assertTrue($profile->capabilities->contains($capability2));
    }

    public function test_scope_active_filters_soft_deleted(): void
    {
        $activeProfile = DelegateeProfile::factory()->create(['is_active' => true]);
        $deletedProfile = DelegateeProfile::factory()->create(['is_active' => true]);
        $deletedProfile->delete();

        $activeProfiles = DelegateeProfile::active()->get();

        $this->assertCount(1, $activeProfiles);
        $this->assertTrue($activeProfiles->contains($activeProfile));
        $this->assertFalse($activeProfiles->contains($deletedProfile));
    }

    public function test_scope_active_filters_inactive(): void
    {
        $activeProfile = DelegateeProfile::factory()->create(['is_active' => true]);
        $inactiveProfile = DelegateeProfile::factory()->create(['is_active' => false]);

        $activeProfiles = DelegateeProfile::active()->get();

        $this->assertCount(1, $activeProfiles);
        $this->assertTrue($activeProfiles->contains($activeProfile));
        $this->assertFalse($activeProfiles->contains($inactiveProfile));
    }

    public function test_casts_json_fields(): void
    {
        $profile = DelegateeProfile::factory()->create([
            'env_json' => ['PATH' => '/usr/bin', 'NODE_ENV' => 'production'],
            'config_json' => ['timeout' => 30, 'retries' => 3],
        ]);

        $profile->refresh();

        $this->assertIsArray($profile->env_json);
        $this->assertIsArray($profile->config_json);
        $this->assertEquals('/usr/bin', $profile->env_json['PATH']);
        $this->assertEquals(30, $profile->config_json['timeout']);
    }

    public function test_casts_is_active_as_boolean(): void
    {
        $profile = DelegateeProfile::factory()->create(['is_active' => true]);

        $this->assertIsBool($profile->is_active);
        $this->assertTrue($profile->is_active);
    }

    public function test_uses_soft_deletes(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $profile->delete();

        $this->assertSoftDeleted($profile);
        $this->assertNotNull(DelegateeProfile::withTrashed()->find($profile->id));
        $this->assertNull(DelegateeProfile::find($profile->id));
    }

    public function test_capabilities_relationship_uses_pivot_model(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $capability = DelegationCapability::factory()->create();

        $profile->capabilities()->attach($capability->id);

        $profile->refresh();
        $pivot = $profile->capabilities->first()->pivot;

        $this->assertInstanceOf(DelegateeCapabilityPivot::class, $pivot);
    }
}
