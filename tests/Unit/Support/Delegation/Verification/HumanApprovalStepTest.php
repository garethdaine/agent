<?php

namespace Tests\Unit\Support\Delegation\Verification;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\Verification\HumanApprovalStep;
use App\Support\Delegation\Verification\VerificationStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HumanApprovalStepTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private DelegationTask $task;

    private DelegationAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        config(['delegation.human_approval_timeout_hours' => 4]);

        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
        $this->task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
            ],
        ]);
        $profile = DelegateeProfile::factory()->create(['user_id' => $this->user->id]);
        $this->attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $this->task->id,
            'delegatee_profile_id' => $profile->id,
        ]);
    }

    public function test_creates_pending_verification_result(): void
    {
        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $this->task->id,
            'delegation_attempt_id' => $this->attempt->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'step_order' => 2,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);
    }

    public function test_sets_expires_at_to_4_hours(): void
    {
        Carbon::setTestNow('2026-02-25 10:00:00');

        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $result = DelegationVerificationResult::where([
            'delegation_task_id' => $this->task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ])->first();

        $this->assertNotNull($result);
        $this->assertNotNull($result->expires_at);
        $this->assertEquals('2026-02-25 14:00:00', $result->expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_returns_pending_immediately(): void
    {
        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
        ];

        $result = $step->execute($this->task, $this->attempt, $stepConfig);

        $this->assertInstanceOf(VerificationStepResult::class, $result);
        $this->assertTrue($result->isPending());
    }

    public function test_respects_custom_timeout_from_step_config(): void
    {
        Carbon::setTestNow('2026-02-25 10:00:00');

        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
            'timeout_hours' => 2,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $result = DelegationVerificationResult::where([
            'delegation_task_id' => $this->task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ])->first();

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-25 12:00:00', $result->expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_stores_context_for_reviewer(): void
    {
        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
            'instructions' => 'Please verify the database migration is safe.',
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $result = DelegationVerificationResult::where([
            'delegation_task_id' => $this->task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ])->first();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('instructions', $result->evidence_json ?? []);
        $this->assertEquals(
            'Please verify the database migration is safe.',
            $result->evidence_json['instructions']
        );
    }

    public function test_records_task_and_attempt_context(): void
    {
        $step = new HumanApprovalStep();
        $stepConfig = [
            'type' => 'human_approval',
            'step_order' => 2,
        ];

        $step->execute($this->task, $this->attempt, $stepConfig);

        $result = DelegationVerificationResult::where([
            'delegation_task_id' => $this->task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
        ])->first();

        $this->assertNotNull($result);
        $this->assertEquals($this->task->id, $result->delegation_task_id);
        $this->assertEquals($this->attempt->id, $result->delegation_attempt_id);
    }
}
