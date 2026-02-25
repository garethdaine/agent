<?php

namespace Tests\Feature\Listeners;

use App\Events\DelegationAttemptCompleted;
use App\Listeners\DelegationRecoveryHandler;
use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationCapability;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Notifications\DelegationEscalationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DelegationRecoveryHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationCapability $capability;

    private DelegateeProfile $profile;

    private DelegateeProfile $alternateProfile;

    private DelegationGraph $graph;

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

        $this->alternateProfile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $this->alternateProfile->capabilities()->attach($this->capability);
        DelegateeMetric::factory()->for($this->alternateProfile)->withMetrics()->create();

        $this->graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_classifies_timeout_as_transient(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $attempt = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'error_code' => 'TIMEOUT',
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt));

        // Timeout is transient, should retry on same delegatee
        $this->assertDatabaseHas('delegation_attempts', [
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 2,
            'status' => DelegationAttempt::STATUS_RUNNING,
        ]);
    }

    public function test_classifies_skipped_as_non_transient(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        $attempt = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'error_code' => 'SKIPPED',
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt));

        // Skipped is non-transient, should re-delegate immediately instead of retry
        // The new attempt should be on a different profile
        $newAttempt = DelegationAttempt::where('delegation_task_id', $task->id)
            ->where('id', '!=', $attempt->id)
            ->first();

        $this->assertNotNull($newAttempt);
        $this->assertNotEquals($this->profile->id, $newAttempt->delegatee_profile_id);
    }

    public function test_retries_on_same_delegatee_up_to_limit(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // First attempt
        $attempt1 = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
            'error_code' => 'TIMEOUT',
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt1));

        // Should retry on same delegatee (attempt 2)
        $attempt2 = DelegationAttempt::where('delegation_task_id', $task->id)
            ->where('attempt_number', 2)
            ->first();
        $this->assertNotNull($attempt2);
        $this->assertEquals($this->profile->id, $attempt2->delegatee_profile_id);

        // Mark attempt 2 as failed
        $attempt2->update(['status' => DelegationAttempt::STATUS_FAILED, 'error_code' => 'TIMEOUT']);
        $handler->handle(new DelegationAttemptCompleted($attempt2->fresh()));

        // Should retry on same delegatee (attempt 3 - last retry)
        $attempt3 = DelegationAttempt::where('delegation_task_id', $task->id)
            ->where('attempt_number', 3)
            ->first();
        $this->assertNotNull($attempt3);
        $this->assertEquals($this->profile->id, $attempt3->delegatee_profile_id);
    }

    public function test_re_delegates_after_retry_limit(): void
    {
        Event::fake([DelegationAttemptCompleted::class]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // Create 2 failed attempts on same profile (retry limit = 2)
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
            'error_code' => 'TIMEOUT',
        ]);

        $attempt2 = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 2,
            'error_code' => 'TIMEOUT',
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt2));

        // Should re-delegate to different profile
        $newAttempt = DelegationAttempt::where('delegation_task_id', $task->id)
            ->where('attempt_number', 3)
            ->first();

        $this->assertNotNull($newAttempt);
        $this->assertNotEquals($this->profile->id, $newAttempt->delegatee_profile_id);
    }

    public function test_escalates_after_redelegate_limit(): void
    {
        Notification::fake();

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // Create 2 failed attempts on first profile (retry limit exhausted)
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
            'error_code' => 'TIMEOUT',
        ]);
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 2,
            'error_code' => 'TIMEOUT',
        ]);

        // Create 1 failed attempt on second profile (re-delegate limit exhausted)
        $attempt3 = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->alternateProfile->id,
            'attempt_number' => 3,
            'error_code' => 'TIMEOUT',
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt3));

        // Task should be failed
        $this->assertEquals(DelegationTask::STATUS_FAILED, $task->fresh()->status);

        // No new attempts should be created
        $this->assertEquals(3, DelegationAttempt::where('delegation_task_id', $task->id)->count());

        // Escalation notification should be sent to graph owner
        Notification::assertSentTo($this->user, DelegationEscalationNotification::class);
    }

    public function test_escalation_notifies_graph_owner(): void
    {
        Notification::fake();

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // Exhaust all recovery options
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
        ]);
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 2,
        ]);
        $attempt3 = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->alternateProfile->id,
            'attempt_number' => 3,
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt3));

        Notification::assertSentTo($this->user, DelegationEscalationNotification::class);
    }

    public function test_escalation_records_metadata(): void
    {
        Notification::fake();

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'contract_json' => ['required_capability' => 'code_generation'],
        ]);

        // Exhaust all recovery options
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
        ]);
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 2,
        ]);
        $attempt3 = DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->alternateProfile->id,
            'attempt_number' => 3,
        ]);

        $handler = app(DelegationRecoveryHandler::class);
        $handler->handle(new DelegationAttemptCompleted($attempt3));

        $task->refresh();
        $this->assertArrayHasKey('escalation_notified_at', $task->metadata_json);
        $this->assertArrayHasKey('escalation_reason', $task->metadata_json);
        $this->assertEquals('Recovery chain exhausted', $task->metadata_json['escalation_reason']);
    }
}
