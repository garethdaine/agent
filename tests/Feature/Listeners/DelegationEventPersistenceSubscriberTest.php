<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationGraphStarted;
use App\Events\DelegationTaskVerified;
use App\Listeners\DelegationEventPersistenceSubscriber;
use App\Models\DelegationAttempt;
use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationEventPersistenceSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    private FeatureFlagManager $featureFlags;

    private DelegationEventPersistenceSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create([
            'user_id' => $this->user->id,
            'status' => DelegationGraph::STATUS_RUNNING,
        ]);

        $this->featureFlags = $this->mock(FeatureFlagManager::class);
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::DELEGATION_ENABLED)
            ->andReturn(true)
            ->byDefault();

        $this->subscriber = new DelegationEventPersistenceSubscriber($this->featureFlags);
    }

    public function test_persists_graph_started_event(): void
    {
        $event = new DelegationGraphStarted($this->graph);

        $this->subscriber->handleGraphStarted($event);

        $this->assertDatabaseHas('delegation_events', [
            'delegation_graph_id' => $this->graph->id,
            'event_type' => 'graph.started',
            'sequence' => 1,
        ]);
    }

    public function test_persists_attempt_completed_event(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
            'status' => DelegationTask::STATUS_RUNNING,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
            'status' => DelegationAttempt::STATUS_SUCCEEDED,
            'attempt_number' => 1,
        ]);

        $event = new DelegationAttemptCompleted($attempt);

        $this->subscriber->handleAttemptCompleted($event);

        $this->assertDatabaseHas('delegation_events', [
            'delegation_graph_id' => $this->graph->id,
            'delegation_task_id' => $task->id,
            'event_type' => 'attempt.completed',
            'sequence' => 1,
        ]);
    }

    public function test_persists_task_verified_passed_event(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, true);

        $this->subscriber->handleTaskVerified($event);

        $dbEvent = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->where('event_type', 'task.verified')
            ->first();

        $this->assertNotNull($dbEvent);
        $this->assertTrue($dbEvent->payload_json['passed']);
        $this->assertSame($task->id, $dbEvent->delegation_task_id);
    }

    public function test_persists_task_verified_failed_event_with_step(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
        ]);

        $event = new DelegationTaskVerified($task, $attempt, false, 2);

        $this->subscriber->handleTaskVerified($event);

        $dbEvent = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->where('event_type', 'task.verified')
            ->first();

        $this->assertNotNull($dbEvent);
        $this->assertFalse($dbEvent->payload_json['passed']);
        $this->assertSame(2, $dbEvent->payload_json['failed_step_order']);
    }

    public function test_persists_graph_completed_event(): void
    {
        $event = new DelegationGraphCompleted($this->graph, DelegationGraph::STATUS_SUCCEEDED);

        $this->subscriber->handleGraphCompleted($event);

        $dbEvent = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->where('event_type', 'graph.completed')
            ->first();

        $this->assertNotNull($dbEvent);
        $this->assertSame(DelegationGraph::STATUS_SUCCEEDED, $dbEvent->payload_json['final_status']);
    }

    public function test_skips_when_delegation_disabled(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::DELEGATION_ENABLED)
            ->andReturn(false);

        $event = new DelegationGraphStarted($this->graph);
        $this->subscriber->handleGraphStarted($event);

        $this->assertDatabaseCount('delegation_events', 0);
    }

    public function test_sequences_auto_increment_across_events(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $attempt = DelegationAttempt::factory()->create([
            'delegation_task_id' => $task->id,
        ]);

        // Fire multiple events (each creates a new writer, so sequences should continue)
        $this->subscriber->handleGraphStarted(new DelegationGraphStarted($this->graph));

        $subscriber2 = new DelegationEventPersistenceSubscriber($this->featureFlags);
        $subscriber2->handleTaskVerified(new DelegationTaskVerified($task, $attempt, true));

        $subscriber3 = new DelegationEventPersistenceSubscriber($this->featureFlags);
        $subscriber3->handleGraphCompleted(new DelegationGraphCompleted($this->graph, DelegationGraph::STATUS_SUCCEEDED));

        $events = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(3, $events);
        $this->assertSame(1, $events[0]->sequence);
        $this->assertSame(2, $events[1]->sequence);
        $this->assertSame(3, $events[2]->sequence);
    }
}
