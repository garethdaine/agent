<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserChatPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserChatPreferenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $pref = UserChatPreference::create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $pref->user);
    }

    #[Test]
    public function it_requires_confirmation_for_delete_by_default(): void
    {
        $user = User::factory()->create();
        $pref = UserChatPreference::create(['user_id' => $user->id]);

        $this->assertTrue($pref->requiresConfirmationFor('delete'));
    }

    #[Test]
    public function it_requires_confirmation_for_stop_by_default(): void
    {
        $user = User::factory()->create();
        $pref = UserChatPreference::create(['user_id' => $user->id]);

        $this->assertTrue($pref->requiresConfirmationFor('stop'));
    }

    #[Test]
    public function it_does_not_require_confirmation_for_steer_by_default(): void
    {
        $user = User::factory()->create();
        $pref = UserChatPreference::create(['user_id' => $user->id]);

        $this->assertFalse($pref->requiresConfirmationFor('steer'));
    }

    #[Test]
    public function it_returns_false_for_unknown_action(): void
    {
        $user = User::factory()->create();
        $pref = UserChatPreference::create(['user_id' => $user->id]);

        $this->assertFalse($pref->requiresConfirmationFor('unknown'));
    }
}
