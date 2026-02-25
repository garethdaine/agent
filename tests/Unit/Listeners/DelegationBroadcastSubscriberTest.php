<?php

namespace Tests\Unit\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphBroadcast;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Events\DelegationUserSummaryBroadcast;
use App\Listeners\DelegationBroadcastSubscriber;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DelegationBroadcastSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_subscriber_implements_should_queue(): void
    {
        $subscriber = new DelegationBroadcastSubscriber();
        $this->assertInstanceOf(ShouldQueue::class, $subscriber);
    }

    public function test_subscriber_uses_delegation_queue(): void
    {
        $subscriber = new DelegationBroadcastSubscriber();
        $this->assertSame('delegation', $subscriber->queue);
    }

    public function test_graph_started_broadcasts_to_graph_channel(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationGraphStarted($this->graph);

        $subscriber->handleGraphStarted($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) {
            return $e->graphId === $this->graph->id
                && $e->eventType === 'graph.started';
        });
    }

    public function test_graph_started_broadcasts_to_user_channel(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationGraphStarted($this->graph);

        $subscriber->handleGraphStarted($event);

        Event::assertDispatched(DelegationUserSummaryBroadcast::class, function ($e) {
            return $e->userId === $this->user->id
                && $e->eventType === 'graph.started';
        });
    }

    public function test_graph_completed_broadcasts_with_final_status(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationGraphCompleted($this->graph, DelegationGraph::STATUS_SUCCEEDED);

        $subscriber->handleGraphCompleted($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) {
            return $e->graphId === $this->graph->id
                && $e->eventType === 'graph.completed'
                && $e->payload['final_status'] === DelegationGraph::STATUS_SUCCEEDED;
        });
    }

    public function test_attempt_completed_broadcasts_task_update(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'status' => DelegationAttempt::STATUS_SUCCEEDED,
        ]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationAttemptCompleted($attempt);

        $subscriber->handleAttemptCompleted($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) use ($task, $attempt) {
            return $e->graphId === $this->graph->id
                && $e->eventType === 'attempt.completed'
                && $e->payload['task_id'] === $task->id
                && $e->payload['attempt_id'] === $attempt->id
                && $e->payload['status'] === DelegationAttempt::STATUS_SUCCEEDED;
        });
    }

    public function test_task_verified_broadcasts_verification_result(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
        ]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationTaskVerified($task, $attempt, true);

        $subscriber->handleTaskVerified($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) use ($task) {
            return $e->graphId === $this->graph->id
                && $e->eventType === 'task.verified'
                && $e->payload['task_id'] === $task->id
                && $e->payload['passed'] === true;
        });
    }

    public function test_task_verified_includes_failed_step_order_when_failed(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);
        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
        ]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationTaskVerified($task, $attempt, false, 2);

        $subscriber->handleTaskVerified($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) {
            return $e->payload['passed'] === false
                && $e->payload['failed_step_order'] === 2;
        });
    }

    public function test_graph_broadcast_event_payload_includes_graph_status(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        $this->graph->update(['status' => DelegationGraph::STATUS_RUNNING]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationGraphStarted($this->graph->fresh());

        $subscriber->handleGraphStarted($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) {
            return $e->payload['graph_status'] === DelegationGraph::STATUS_RUNNING;
        });
    }

    public function test_graph_broadcast_event_includes_task_counts(): void
    {
        Event::fake([DelegationGraphBroadcast::class, DelegationUserSummaryBroadcast::class]);

        DelegationTask::factory()->count(2)->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_SUCCEEDED,
        ]);
        DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_RUNNING,
        ]);

        $subscriber = new DelegationBroadcastSubscriber();
        $event = new DelegationGraphStarted($this->graph->fresh());

        $subscriber->handleGraphStarted($event);

        Event::assertDispatched(DelegationGraphBroadcast::class, function ($e) {
            return $e->payload['task_counts']['total'] === 3
                && $e->payload['task_counts']['succeeded'] === 2
                && $e->payload['task_counts']['running'] === 1;
        });
    }
}
