<?php

namespace Tests\Unit;

use App\Models\NlParseAttempt;
use App\Models\User;
use App\Policies\NlParseAttemptPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlParseAttemptPolicyTest extends TestCase
{
    use RefreshDatabase;

    private NlParseAttemptPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NlParseAttemptPolicy;
    }

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

    public function test_user_can_view_own_attempt(): void
    {
        $user = User::factory()->create();
        $attempt = NlParseAttempt::create([
            'user_id' => $user->id,
            'input_text' => 'test',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
        ]);

        $this->assertTrue($this->policy->view($user, $attempt));
    }

    public function test_user_cannot_view_other_users_attempt(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $attempt = NlParseAttempt::create([
            'user_id' => $otherUser->id,
            'input_text' => 'test',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
        ]);

        $this->assertFalse($this->policy->view($user, $attempt));
    }

    public function test_admin_can_view_any_users_attempt(): void
    {
        $admin = User::factory()->create();
        config(['agent.roles.admin_user_ids' => [$admin->id]]);

        $otherUser = User::factory()->create();
        $attempt = NlParseAttempt::create([
            'user_id' => $otherUser->id,
            'input_text' => 'test',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
        ]);

        $this->assertTrue($this->policy->view($admin, $attempt));
    }

    public function test_analytics_user_can_view_any_users_attempt(): void
    {
        $analyticsUser = User::factory()->create();
        config(['agent.roles.analytics_user_ids' => [$analyticsUser->id]]);

        $otherUser = User::factory()->create();
        $attempt = NlParseAttempt::create([
            'user_id' => $otherUser->id,
            'input_text' => 'test',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
        ]);

        $this->assertTrue($this->policy->view($analyticsUser, $attempt));
    }
}
