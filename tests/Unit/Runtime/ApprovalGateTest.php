<?php

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;
use App\Services\Runtime\ApprovalGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = app(ApprovalGate::class);
    }

    public function test_requires_approval_for_mutation_in_standard_mode(): void
    {
        $this->assertTrue($this->gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.write',
            ['path' => '/tmp/test.txt']
        ));
    }

    public function test_does_not_require_approval_for_read_in_standard_mode(): void
    {
        $this->assertFalse($this->gate->requiresApproval(
            RuntimeMode::Standard,
            'fs.read',
            ['path' => '/tmp/test.txt']
        ));
    }

    public function test_requires_approval_for_external_in_full_mode(): void
    {
        $this->assertTrue($this->gate->requiresApproval(
            RuntimeMode::Full,
            'web.fetch',
            ['url' => 'https://example.com']
        ));
    }

    public function test_create_approval_request_returns_approval_record(): void
    {
        $toolCall = RuntimeToolCall::factory()->create(['requires_approval' => true]);
        $user = User::factory()->create();

        $approval = $this->gate->createApprovalRequest($toolCall, $user);

        $this->assertInstanceOf(RuntimeApproval::class, $approval);
        $this->assertEquals(RuntimeApprovalState::Pending, $approval->state);
        $this->assertEquals($user->id, $approval->requested_by);
    }

    public function test_approve_updates_approval_and_tool_call(): void
    {
        $toolCall = RuntimeToolCall::factory()->create([
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'state' => RuntimeApprovalState::Pending,
        ]);
        $decider = User::factory()->create();

        $result = $this->gate->approve($approval, $decider, 'Looks safe');

        $this->assertTrue($result);
        $approval->refresh();
        $toolCall->refresh();

        $this->assertEquals(RuntimeApprovalState::Approved, $approval->state);
        $this->assertEquals($decider->id, $approval->decision_by);
        $this->assertEquals(RuntimeToolCallStatus::Approved, $toolCall->status);
        $this->assertNotNull($toolCall->approved_at);
    }

    public function test_deny_updates_approval_and_tool_call(): void
    {
        $toolCall = RuntimeToolCall::factory()->create([
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'state' => RuntimeApprovalState::Pending,
        ]);
        $decider = User::factory()->create();

        $result = $this->gate->deny($approval, $decider, 'Too risky');

        $this->assertTrue($result);
        $approval->refresh();
        $toolCall->refresh();

        $this->assertEquals(RuntimeApprovalState::Denied, $approval->state);
        $this->assertEquals(RuntimeToolCallStatus::Denied, $toolCall->status);
    }

    public function test_cannot_approve_non_pending_approval(): void
    {
        $toolCall = RuntimeToolCall::factory()->create();
        $approval = RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'state' => RuntimeApprovalState::Denied,
        ]);
        $decider = User::factory()->create();

        $result = $this->gate->approve($approval, $decider);

        $this->assertFalse($result);
    }
}
