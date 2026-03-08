<?php

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\Verification\HumanApprovalStep;
use App\Support\Delegation\Verification\VerificationStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustGatedApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private function createTaskAndAttempt(float $trustScore, ?bool $reversibility = null): array
    {
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);

        $contractJson = [
            'required_capability' => 'code_execution',
            'prompt' => 'Test task',
        ];
        if ($reversibility !== null) {
            $contractJson['reversibility'] = $reversibility;
        }

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'contract_json' => $contractJson,
        ]);

        $profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'trust_score' => $trustScore,
        ]);

        $task->update(['assigned_delegatee_profile_id' => $profile->id]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile->id,
        ]);

        return [$task->fresh(), $attempt, $profile];
    }

    public function test_low_trust_delegatee_with_reversible_task_is_blocked(): void
    {
        [$task, $attempt] = $this->createTaskAndAttempt(0.5, true);

        $step = new HumanApprovalStep;
        $result = $step->execute($task, $attempt, ['step_order' => 1]);

        $this->assertTrue($result->isPending());
        $this->assertEquals(DelegationTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);
    }

    public function test_high_trust_delegatee_with_irreversible_task_is_blocked(): void
    {
        [$task, $attempt] = $this->createTaskAndAttempt(0.8, false);

        $step = new HumanApprovalStep;
        $result = $step->execute($task, $attempt, ['step_order' => 1]);

        $this->assertTrue($result->isPending());
        $this->assertEquals(DelegationTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
    }

    public function test_high_trust_delegatee_with_reversible_task_proceeds(): void
    {
        [$task, $attempt] = $this->createTaskAndAttempt(0.8, true);

        $step = new HumanApprovalStep;
        $result = $step->execute($task, $attempt, ['step_order' => 1]);

        $this->assertTrue($result->isPending());
        // Task status should NOT be changed to pending_approval
        $this->assertNotEquals(DelegationTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
        // Verification result should still be created (normal human approval flow)
        $this->assertDatabaseHas('delegation_verification_results', [
            'delegation_task_id' => $task->id,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);
    }

    public function test_delegatee_with_exact_threshold_trust_score_proceeds(): void
    {
        [$task, $attempt] = $this->createTaskAndAttempt(0.7, true);

        $step = new HumanApprovalStep;
        $result = $step->execute($task, $attempt, ['step_order' => 1]);

        $this->assertTrue($result->isPending());
        // Exactly 0.7 should NOT be blocked (threshold is <, not <=)
        $this->assertNotEquals(DelegationTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
    }

    public function test_custom_threshold_via_config_override(): void
    {
        config(['agent.delegation.approval_trust_threshold' => 0.9]);

        // Trust score 0.8 is below custom threshold 0.9, should be blocked
        [$task, $attempt] = $this->createTaskAndAttempt(0.8, true);

        $step = new HumanApprovalStep;
        $result = $step->execute($task, $attempt, ['step_order' => 1]);

        $this->assertTrue($result->isPending());
        $this->assertEquals(DelegationTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
    }
}
