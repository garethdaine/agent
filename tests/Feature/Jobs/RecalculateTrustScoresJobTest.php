<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RecalculateTrustScoresJob;
use App\Models\DelegateeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateTrustScoresJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_all_active_profiles(): void
    {
        $user = User::factory()->create();
        $profile1 = DelegateeProfile::factory()->for($user)->create(['is_active' => true]);
        $profile2 = DelegateeProfile::factory()->for($user)->create(['is_active' => true]);

        (new RecalculateTrustScoresJob)->handle();

        $profile1->refresh();
        $profile2->refresh();

        $this->assertNotNull($profile1->trust_updated_at);
        $this->assertNotNull($profile2->trust_updated_at);
    }

    public function test_job_skips_inactive_profiles(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create(['is_active' => false]);

        (new RecalculateTrustScoresJob)->handle();

        $profile->refresh();
        $this->assertNull($profile->trust_updated_at);
    }

    public function test_job_handles_empty_profile_set(): void
    {
        // No profiles exist - should not throw
        (new RecalculateTrustScoresJob)->handle();

        $this->assertTrue(true); // If we got here, test passed
    }
}
