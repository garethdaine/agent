<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RuntimeSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_sessions_returns_user_sessions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        RuntimeSession::factory()->count(3)->create(['user_id' => $user->id]);
        RuntimeSession::factory()->create(); // Another user's session

        $response = $this->getJson('/agent/api/v1/chat/runtime/sessions');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_list_sessions_requires_authentication(): void
    {
        $response = $this->getJson('/agent/api/v1/chat/runtime/sessions');

        $response->assertUnauthorized();
    }

    public function test_show_session_returns_session_with_turns(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        RuntimeTurn::factory()->count(2)->create(['runtime_session_id' => $session->id]);

        $response = $this->getJson("/agent/api/v1/chat/runtime/sessions/{$session->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $session->id);
        $response->assertJsonCount(2, 'data.turns');
    }

    public function test_show_session_returns_404_for_other_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/agent/api/v1/chat/runtime/sessions/{$session->id}");

        $response->assertNotFound();
    }

    public function test_stop_session_terminates_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/sessions/{$session->id}/stop");

        $response->assertOk();
        $session->refresh();
        $this->assertEquals(RuntimeSessionStatus::Stopped, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_stop_session_returns_404_for_other_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/sessions/{$session->id}/stop");

        $response->assertNotFound();
    }

    public function test_approve_tool_call_approves_pending_call(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $user->id,
            'state' => RuntimeApprovalState::Pending,
        ]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/tool-calls/{$toolCall->id}/approve", [
            'reason' => 'Looks good',
        ]);

        $response->assertOk();
        $approval->refresh();
        $this->assertEquals(RuntimeApprovalState::Approved, $approval->state);
        $this->assertEquals($user->id, $approval->decision_by);
        $this->assertEquals('Looks good', $approval->decision_reason);
    }

    public function test_approve_tool_call_returns_404_for_other_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $otherUser->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $otherUser->id,
            'state' => RuntimeApprovalState::Pending,
        ]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/tool-calls/{$toolCall->id}/approve");

        $response->assertNotFound();
    }

    public function test_approve_tool_call_returns_404_when_no_approval_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => false,
        ]);
        // No approval record created

        $response = $this->postJson("/agent/api/v1/chat/runtime/tool-calls/{$toolCall->id}/approve");

        $response->assertNotFound();
    }

    public function test_deny_tool_call_denies_pending_call(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $user->id,
            'state' => RuntimeApprovalState::Pending,
        ]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/tool-calls/{$toolCall->id}/deny", [
            'reason' => 'Not safe to proceed',
        ]);

        $response->assertOk();
        $approval->refresh();
        $this->assertEquals(RuntimeApprovalState::Denied, $approval->state);
        $this->assertEquals($user->id, $approval->decision_by);
        $this->assertEquals('Not safe to proceed', $approval->decision_reason);
    }

    public function test_deny_tool_call_returns_400_when_approval_not_pending(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::Approved,
        ]);
        RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $user->id,
            'state' => RuntimeApprovalState::Approved,
            'decision_by' => $user->id,
        ]);

        $response = $this->postJson("/agent/api/v1/chat/runtime/tool-calls/{$toolCall->id}/deny");

        $response->assertBadRequest();
    }
}
