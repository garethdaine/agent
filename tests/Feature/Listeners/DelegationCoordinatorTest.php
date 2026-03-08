<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Listeners\DelegationCoordinator;
use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationCapability;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DelegationCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationCapability $capability;

    private DelegateeProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable delegation feature for tests
        config(['delegation.enabled' => true]);

        $this->user = User::factory()->create();
        $this->capability = DelegationCapability::factory()->create([
            'slug' => 'code_generation',
            'is_active' => true,
        ]);
        $this->profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $this->profile->capabilities()->attach($this->capability);

        DelegateeMetric::factory()->for($this->profile)->withMetrics()->create();
    }

    public function test_graph_started_assigns_and_spawns_root_tasks(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
            'max_parallel_tasks' => 5,
        ]);

        $rootTask1 = DelegationTask::factory()->ready()->create([
            'delegation_graph_id' => $graph->id,
            'sequence_order' => 0,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $rootTask2 = DelegationTask::factory()->ready()->create([
            'delegation_graph_id' => $graph->id,
            'sequence_order' => 0,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleGraphStarted(new DelegationGraphStarted($graph));

        // Both root tasks should have attempts spawned
        $this->assertDatabaseHas('delegation_attempts', [
            'delegation_task_id' => $rootTask1->id,
            'status' => DelegationAttempt::STATUS_RUNNING,
        ]);
        $this->assertDatabaseHas('delegation_attempts', [
            'delegation_task_id' => $rootTask2->id,
            'status' => DelegationAttempt::STATUS_RUNNING,
        ]);

        // Tasks should be transitioned to running
        $this->assertEquals(DelegationTask::STATUS_RUNNING, $rootTask1->fresh()->status);
        $this->assertEquals(DelegationTask::STATUS_RUNNING, $rootTask2->fresh()->status);
    }

    public function test_respects_max_parallel_tasks(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
            'max_parallel_tasks' => 1, // Only allow 1 parallel task
        ]);

        $rootTask1 = DelegationTask::factory()->ready()->create([
            'delegation_graph_id' => $graph->id,
            'sequence_order' => 0,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $rootTask2 = DelegationTask::factory()->ready()->create([
            'delegation_graph_id' => $graph->id,
            'sequence_order' => 0,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleGraphStarted(new DelegationGraphStarted($graph));

        // Only one task should have been spawned
        $runningCount = DelegationAttempt::whereIn('delegation_task_id', [$rootTask1->id, $rootTask2->id])
            ->where('status', DelegationAttempt::STATUS_RUNNING)
            ->count();
        $this->assertEquals(1, $runningCount);
    }

    public function test_attempt_success_triggers_verification(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'required_capability' => 'code_generation',
                'verification_strategy' => null, // No verification steps
            ],
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        Event::fake([DelegationTaskVerified::class]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleAttemptCompleted(new DelegationAttemptCompleted($attempt));

        // Task should transition to verifying
        $this->assertEquals(DelegationTask::STATUS_VERIFYING, $task->fresh()->status);

        // Verification pipeline should have been called (no steps = immediate pass)
        Event::assertDispatched(DelegationTaskVerified::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->passed === true;
        });
    }

    public function test_task_verified_passed_fires_ready_dependents(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
            'max_parallel_tasks' => 5,
        ]);

        $parentTask = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'sequence_order' => 0,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $childTask = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_PENDING,
            'sequence_order' => 1,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // Create dependency: child depends on parent
        $childTask->dependencies()->attach($parentTask);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $parentTask->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleTaskVerified(new DelegationTaskVerified($parentTask, $attempt, passed: true));

        // Parent task should be succeeded
        $this->assertEquals(DelegationTask::STATUS_SUCCEEDED, $parentTask->fresh()->status);

        // Child task should now be ready and spawned (running)
        $this->assertEquals(DelegationTask::STATUS_RUNNING, $childTask->fresh()->status);
    }

    public function test_all_tasks_succeeded_completes_graph(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'sequence_order' => 0,
        ]);

        $attempt = DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleTaskVerified(new DelegationTaskVerified($task, $attempt, passed: true));

        // Task should be succeeded
        $this->assertEquals(DelegationTask::STATUS_SUCCEEDED, $task->fresh()->status);

        // Graph should be completed (succeeded)
        $this->assertEquals(DelegationGraph::STATUS_SUCCEEDED, $graph->fresh()->status);
        $this->assertNotNull($graph->fresh()->finished_at);
    }

    public function test_any_task_failed_marks_graph_partial_or_failed(): void
    {
        $graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $succeededTask = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_SUCCEEDED,
            'sequence_order' => 0,
        ]);

        $failedTask = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
            'sequence_order' => 1,
        ]);

        $attempt = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $failedTask->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $coordinator = app(DelegationCoordinator::class);
        $coordinator->handleTaskVerified(new DelegationTaskVerified($failedTask, $attempt, passed: false));

        // Task should be failed
        $this->assertEquals(DelegationTask::STATUS_FAILED, $failedTask->fresh()->status);

        // Graph should be partial (one succeeded, one failed)
        $this->assertEquals(DelegationGraph::STATUS_PARTIAL, $graph->fresh()->status);
        $this->assertNotNull($graph->fresh()->finished_at);
    }
}
