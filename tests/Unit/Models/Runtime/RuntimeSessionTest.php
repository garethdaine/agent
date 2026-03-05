<?php

namespace Tests\Unit\Models\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimePolicySnapshot;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_session_has_uuid_primary_key(): void
    {
        $session = RuntimeSession::factory()->create();
        $this->assertIsString($session->id);
        $this->assertEquals(36, strlen($session->id));
    }

    public function test_runtime_session_casts_status_to_enum(): void
    {
        $session = RuntimeSession::factory()->create(['status' => 'active']);
        $this->assertInstanceOf(RuntimeSessionStatus::class, $session->status);
        $this->assertEquals(RuntimeSessionStatus::Active, $session->status);
    }

    public function test_runtime_session_casts_mode_to_enum(): void
    {
        $session = RuntimeSession::factory()->create(['mode' => 'safe']);
        $this->assertInstanceOf(RuntimeMode::class, $session->mode);
        $this->assertEquals(RuntimeMode::Safe, $session->mode);
    }

    public function test_runtime_session_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $session->user);
        $this->assertEquals($user->id, $session->user->id);
    }

    public function test_runtime_session_has_many_turns(): void
    {
        $session = RuntimeSession::factory()->create();
        RuntimeTurn::factory()->count(3)->create(['runtime_session_id' => $session->id]);

        $this->assertCount(3, $session->turns);
    }

    public function test_runtime_session_has_many_policy_snapshots(): void
    {
        $session = RuntimeSession::factory()->create();
        RuntimePolicySnapshot::factory()->count(2)->create(['runtime_session_id' => $session->id]);

        $this->assertCount(2, $session->policySnapshots);
    }
}
