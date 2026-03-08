<?php

namespace Tests\Unit\Policies;

use App\Models\NlOrgParseAttempt;
use App\Models\User;
use App\Policies\NlOrgParseAttemptPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlOrgParseAttemptPolicyTest extends TestCase
{
    use RefreshDatabase;

    private NlOrgParseAttemptPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NlOrgParseAttemptPolicy;
    }

    // viewAny tests

    public function test_regular_user_cannot_view_any_attempts(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_admin_can_view_any_attempts(): void
    {
        $user = User::factory()->create();
        config(['agent.roles.admin_user_ids' => [$user->id]]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_analytics_user_can_view_any_attempts(): void
    {
        $user = User::factory()->create();
        config(['agent.roles.analytics_user_ids' => [$user->id]]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    // view tests

    public function test_owner_can_view_own_attempt(): void
    {
        $user = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test input',
        ]);

        $this->assertTrue($this->policy->view($user, $attempt));
    }

    public function test_non_owner_cannot_view_attempt(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $owner->id,
            'raw_input' => 'test input',
        ]);

        $this->assertFalse($this->policy->view($otherUser, $attempt));
    }

    public function test_admin_can_view_any_users_attempt(): void
    {
        $admin = User::factory()->create();
        config(['agent.roles.admin_user_ids' => [$admin->id]]);

        $owner = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $owner->id,
            'raw_input' => 'test input',
        ]);

        $this->assertTrue($this->policy->view($admin, $attempt));
    }

    // apply tests

    public function test_owner_can_apply_own_attempt(): void
    {
        $user = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test input',
        ]);

        $this->assertTrue($this->policy->apply($user, $attempt));
    }

    public function test_non_owner_cannot_apply_attempt(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $owner->id,
            'raw_input' => 'test input',
        ]);

        $this->assertFalse($this->policy->apply($otherUser, $attempt));
    }

    public function test_admin_cannot_apply_other_users_attempt(): void
    {
        $admin = User::factory()->create();
        config(['agent.roles.admin_user_ids' => [$admin->id]]);

        $owner = User::factory()->create();
        $attempt = NlOrgParseAttempt::create([
            'user_id' => $owner->id,
            'raw_input' => 'test input',
        ]);

        $this->assertFalse($this->policy->apply($admin, $attempt));
    }
}
