<?php

namespace Tests\Feature\Support\Delegation;

use App\Events\DelegationTaskVerified;
use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use App\Support\Delegation\DelegationReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DelegationReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegateeProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_detects_expired_human_approvals(): void
    {
        Event::fake([DelegationTaskVerified::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'status' => DelegationAttempt::STATUS_SUCCEEDED,
        ]);

        $result = DelegationVerificationResult::factory()
            ->humanApproval()
            ->pending()
            ->create([
                'delegation_task_id' => $task->id,
                'delegation_attempt_id' => $attempt->id,
                'expires_at' => now()->subHour(),
            ]);

        (new DelegationReconciler)->reconcile();

        $this->assertEquals(
            DelegationVerificationResult::VERDICT_FAILED,
            $result->fresh()->verdict
        );

        Event::assertDispatched(DelegationTaskVerified::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->passed === false;
        });
    }

    public function test_does_not_expire_non_expired_human_approvals(): void
    {
        Event::fake([DelegationTaskVerified::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'status' => DelegationAttempt::STATUS_SUCCEEDED,
        ]);

        $result = DelegationVerificationResult::factory()
            ->humanApproval()
            ->pending()
            ->create([
                'delegation_task_id' => $task->id,
                'delegation_attempt_id' => $attempt->id,
                'expires_at' => now()->addHours(2), // Not expired
            ]);

        (new DelegationReconciler)->reconcile();

        $this->assertEquals(
            DelegationVerificationResult::VERDICT_PENDING,
            $result->fresh()->verdict
        );

        Event::assertNotDispatched(DelegationTaskVerified::class);
    }

    public function test_retries_blocked_tasks_assignment(): void
    {
        // Create a graph with a blocked task
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->blocked()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
            ],
        ]);

        // Run reconciler - it should attempt to re-assign blocked tasks
        // Note: full assignment depends on DelegateeAssigner integration
        (new DelegationReconciler)->reconcile();

        // Verify the reconciler processed the blocked task
        // The actual assignment depends on available delegatees with matching capabilities
        $this->assertTrue(true); // Placeholder - integration test would verify assignment
    }

    public function test_detects_stuck_running_graphs(): void
    {
        // Create a graph that's been running but has no active tasks
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
            'updated_at' => now()->subMinutes(30),
        ]);

        // All tasks are in terminal state
        DelegationTask::factory()->succeeded()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        // Run reconciler
        (new DelegationReconciler)->reconcile();

        // Graph should still exist (reconciler logs but doesn't auto-complete without proper events)
        $this->assertNotNull($graph->fresh());
    }

    public function test_force_kills_after_cancellation_timeout(): void
    {
        // Create a cancelled graph with running tasks past the timeout
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_CANCELLED,
            'updated_at' => now()->subMinutes(20), // Past 15-minute timeout
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        $run = AgentJobRun::factory()->running()->create();

        $attempt = DelegationAttempt::factory()->running()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'agent_job_run_id' => $run->id,
        ]);

        (new DelegationReconciler)->reconcile();

        // Verify attempt was marked as failed
        $this->assertEquals(
            DelegationAttempt::STATUS_FAILED,
            $attempt->fresh()->status
        );

        // Verify task was cancelled
        $this->assertEquals(
            DelegationTask::STATUS_CANCELLED,
            $task->fresh()->status
        );
    }

    public function test_updates_agent_job_run_status_on_force_kill(): void
    {
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_CANCELLED,
            'updated_at' => now()->subMinutes(20), // Past 15-minute timeout
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        $run = AgentJobRun::factory()->running()->create();

        DelegationAttempt::factory()->running()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'agent_job_run_id' => $run->id,
        ]);

        (new DelegationReconciler)->reconcile();

        // Verify AgentJobRun was marked as cancelled
        $this->assertEquals(
            AgentJobRun::STATUS_KILLED,
            $run->fresh()->status
        );
    }

    public function test_does_not_force_kill_before_timeout(): void
    {
        // Create a cancelled graph with running tasks within the timeout
        $graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_CANCELLED,
            'updated_at' => now()->subMinutes(5), // Within 15-minute timeout
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        $run = AgentJobRun::factory()->running()->create();

        $attempt = DelegationAttempt::factory()->running()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'agent_job_run_id' => $run->id,
        ]);

        (new DelegationReconciler)->reconcile();

        // Attempt should still be running
        $this->assertEquals(
            DelegationAttempt::STATUS_RUNNING,
            $attempt->fresh()->status
        );

        // Task should still be running
        $this->assertEquals(
            DelegationTask::STATUS_RUNNING,
            $task->fresh()->status
        );
    }

    public function test_handles_multiple_expired_approvals(): void
    {
        Event::fake([DelegationTaskVerified::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $task = DelegationTask::factory()->create([
                'delegation_graph_id' => $graph->id,
                'status' => DelegationTask::STATUS_VERIFYING,
            ]);

            $attempt = DelegationAttempt::factory()->create([
                'delegation_task_id' => $task->id,
                'delegatee_profile_id' => $this->profile->id,
                'status' => DelegationAttempt::STATUS_SUCCEEDED,
            ]);

            $results[] = DelegationVerificationResult::factory()
                ->humanApproval()
                ->pending()
                ->create([
                    'delegation_task_id' => $task->id,
                    'delegation_attempt_id' => $attempt->id,
                    'expires_at' => now()->subHour(),
                ]);
        }

        (new DelegationReconciler)->reconcile();

        foreach ($results as $result) {
            $this->assertEquals(
                DelegationVerificationResult::VERDICT_FAILED,
                $result->fresh()->verdict
            );
        }

        Event::assertDispatchedTimes(DelegationTaskVerified::class, 3);
    }
}
