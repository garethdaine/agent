<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Messenger\SlashCommands\ApproveCommandHandler;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the /approve slash command handler.
 */
final class ApproveCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approve_approves_pending_tool_call(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        $turn = RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
        ]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'status' => RuntimeToolCallStatus::PendingApproval,
            'tool_name' => 'fs.write',
        ]);

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, [$toolCall->id]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Approved tool call', $result->message);
        $this->assertStringContainsString('fs.write', $result->message);

        $toolCall->refresh();
        $this->assertEquals(RuntimeToolCallStatus::Approved, $toolCall->status);
        $this->assertNotNull($toolCall->approved_at);
    }

    #[Test]
    public function approve_works_with_short_id(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        $turn = RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
        ]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);

        $shortId = substr($toolCall->id, 0, 8);

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, [$shortId]);

        $this->assertTrue($result->success);

        $toolCall->refresh();
        $this->assertEquals(RuntimeToolCallStatus::Approved, $toolCall->status);
    }

    #[Test]
    public function approve_updates_approval_record(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        $turn = RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
        ]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'state' => RuntimeApprovalState::Pending,
            'requested_by' => $user->id,
        ]);

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, [$toolCall->id]);

        $this->assertTrue($result->success);

        $approval->refresh();
        $this->assertEquals(RuntimeApprovalState::Approved, $approval->state);
        $this->assertEquals($user->id, $approval->decision_by);
    }

    #[Test]
    public function approve_fails_without_tool_call_id(): void
    {
        $user = User::factory()->create();

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage:', $result->message);
    }

    #[Test]
    public function approve_fails_for_nonexistent_tool_call(): void
    {
        $user = User::factory()->create();

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, ['nonexistent-id']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Tool call not found', $result->message);
    }

    #[Test]
    public function approve_fails_for_already_approved_tool_call(): void
    {
        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $user->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        $turn = RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
        ]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'status' => RuntimeToolCallStatus::Approved,
        ]);

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, [$toolCall->id]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not pending approval', $result->message);
    }

    #[Test]
    public function approve_cannot_approve_other_users_tool_call(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $session = RuntimeSession::factory()->create([
            'user_id' => $otherUser->id,
            'status' => RuntimeSessionStatus::Active,
        ]);
        $turn = RuntimeTurn::factory()->create([
            'runtime_session_id' => $session->id,
        ]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);

        $handler = new ApproveCommandHandler;
        $result = $handler->handle($user, [$toolCall->id]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Tool call not found', $result->message);

        $toolCall->refresh();
        $this->assertEquals(RuntimeToolCallStatus::PendingApproval, $toolCall->status);
    }
}
