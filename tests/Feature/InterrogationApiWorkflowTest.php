<?php

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Jobs\RegenerateInterrogationBuildTaskJob;
use App\Jobs\SyncInterrogationTasksToTaskProviderJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use App\Support\TaskProviders\TaskManagementProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class InterrogationApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_interrogation_session_lifecycle_endpoints_work(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $create = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Discovery Session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Add requirements discovery wizard UI.',
        ])->assertStatus(202);

        $sessionId = $create->json('data.id');

        $this->assertNotNull($sessionId);
        $create
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_SETUP)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_SETUP);

        Queue::assertNotPushed(ExecuteInterrogationDiscoveryJob::class);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/advance-pre-discovery')
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_TECH_STACK_SETUP);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/start-discovery')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_DISCOVERY);

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/answer', [
            'question_id' => 'q-1',
            'answer_type' => 'freetext',
            'answer_text' => 'Initial answer text.',
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/confirm-summary')
            ->assertStatus(409);

        $session = InterrogationSession::query()->findOrFail($sessionId);
        $session->update([
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'summary_json' => [
                'summary_markdown' => 'Summary ready',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/confirm-summary')
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/generate-plan')
            ->assertStatus(202);

        $session = InterrogationSession::query()->findOrFail($sessionId);
        $this->assertSame('queued', (string) data_get($session->metadata_json, 'plan.generation_status'));

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/annotations', [
            'key' => 'priority',
            'value' => 'high',
        ])->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/pause')
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/resume')
            ->assertOk();

        $this->deleteJson('/agent/api/v1/interrogation/sessions/'.$sessionId)
            ->assertOk();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$sessionId.'/restore')
            ->assertOk();
    }

    public function test_interrogation_events_and_settings_endpoints_work(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => ['question_text' => 'What should this do?', 'question_id' => 'q-1'],
            'event_ts' => now('UTC'),
        ]);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/events?after_sequence=0&limit=10')
            ->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.sequence', 1);

        $this->putJson('/agent/api/v1/interrogation/settings/interrogation.system_prompt', [
            'value' => 'System prompt text',
        ])->assertOk();

        $this->getJson('/agent/api/v1/interrogation/settings/interrogation.system_prompt')
            ->assertOk()
            ->assertJsonPath('data.value', 'System prompt text');

        $this->getJson('/agent/api/v1/interrogation/settings')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'interrogation.system_prompt');
    }

    public function test_session_name_and_feature_brief_can_be_updated_and_cleared(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'feature_brief' => 'Rename behavior',
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$session->id, [
            'name' => '  Renamed Session  ',
            'feature_brief' => '  Updated initial brief content.  ',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed Session');

        $session->refresh();
        $this->assertSame('Renamed Session', $session->name);
        $this->assertSame('Updated initial brief content.', $session->feature_brief);

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$session->id, [
            'name' => '',
            'feature_brief' => '',
        ])->assertOk()
            ->assertJsonPath('data.name', null);

        $session->refresh();
        $this->assertNull($session->name);
        $this->assertNull($session->feature_brief);
    }

    public function test_retry_endpoint_requeues_failed_session_for_current_phase(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_DISCOVERY,
            'error_code' => 'DISCOVERY_COMMAND_FAILED',
            'error_summary' => 'failed once',
            'finished_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.session_id', $session->id);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_SETUP, $session->status);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
        $this->assertNull($session->finished_at);

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id;
        });
    }

    public function test_retry_interrogation_clears_cli_session_and_requeues_round_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry interrogation session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'cli_session_id' => 'stale-session-id',
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'stalled once',
            'finished_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.session_id', $session->id);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, $session->status);
        $this->assertNull($session->cli_session_id);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id && $job->isSystemMessage === true;
        });
    }

    public function test_submit_answer_accepts_multi_choice_payloads_and_rejects_empty_choice(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Multi choice answer session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/answer', [
            'question_id' => 'q-multi-1',
            'answer_type' => 'choice',
            'selected_options' => ['Option A', 'Option C'],
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id
                && is_array($job->answerPayload)
                && ($job->answerPayload['question_id'] ?? null) === 'q-multi-1'
                && ($job->answerPayload['answer_type'] ?? null) === 'choice'
                && ($job->answerPayload['selected_options'] ?? []) === ['Option A', 'Option C'];
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/answer', [
            'question_id' => 'q-multi-2',
            'answer_type' => 'choice',
        ])->assertStatus(422);
    }

    public function test_submit_answer_auto_recovers_failed_interrogation_interrupted_by_signal(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Interrupted interrogation session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'The process has been signaled with signal "2".',
            'finished_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/answer', [
            'question_id' => 'q-recover-1',
            'answer_type' => 'choice',
            'selected_option' => 'Option 1',
        ])->assertStatus(202);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
        $this->assertNull($session->finished_at);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && is_array($job->answerPayload)
                && ($job->answerPayload['question_id'] ?? null) === 'q-recover-1';
        });
    }

    public function test_retry_interrogation_with_unanswered_latest_question_does_not_queue_next_round(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry with pending question',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'failed once',
            'finished_at' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-pending',
                'question_text' => 'Which authentication mode should the MVP support first?',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 20,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', false)
            ->assertJsonPath('data.pending_question_id', 'q-pending');

        Queue::assertNotPushed(ExecuteInterrogationRoundJob::class);
    }

    public function test_retry_interrogation_ignores_completion_marker_questions_when_finding_pending_question(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry with completion marker',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'failed once',
            'finished_at' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-complete',
                'question_text' => 'Requirements interrogation is now complete.',
                'is_complete' => true,
                'progress_estimate' => 100,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.pending_question_id', null);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id && $job->isSystemMessage === true;
        });
    }

    public function test_retry_interrogation_ignores_operational_status_text_question_when_finding_pending_question(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry with operational status question',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'failed once',
            'finished_at' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-operational',
                'question_text' => 'Resuming interrogation phase by locating the latest saved unanswered question in this workspace/session state.',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 5,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.pending_question_id', null);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id && $job->isSystemMessage === true;
        });
    }

    public function test_retry_interrogation_from_active_interrogating_state_requeues_round_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry active interrogation session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'cli_session_id' => 'stale-session-id',
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-operational',
                'question_text' => 'Resuming interrogation phase by locating the latest saved unanswered question in this workspace/session state.',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 5,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.pending_question_id', null)
            ->assertJsonPath('data.session_id', $session->id);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, $session->status);
        $this->assertNull($session->cli_session_id);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id && $job->isSystemMessage === true;
        });
    }

    public function test_retry_interrogation_with_pending_question_does_not_clear_cli_session_id(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Retry active session with pending question',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'cli_session_id' => 'existing-session-id',
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-pending-active',
                'question_text' => 'Which authentication mode should the MVP support first?',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 20,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/retry')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', false)
            ->assertJsonPath('data.pending_question_id', 'q-pending-active')
            ->assertJsonPath('data.session_id', $session->id);

        $session->refresh();

        $this->assertSame('existing-session-id', $session->cli_session_id);
        Queue::assertNotPushed(ExecuteInterrogationRoundJob::class);
    }

    public function test_restart_from_beginning_clears_history_and_resets_to_setup_without_requeueing_discovery(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Restart flow session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'cli_session_id' => 'resume-thread-123',
            'summary_json' => ['summary_markdown' => 'Stale summary'],
            'plan_json' => ['plan_markdown' => 'Stale plan'],
            'annotations_json' => ['priority' => 'high'],
            'metadata_json' => ['source' => 'ui', 'summary_open_question_queue' => ['active' => true]],
            'approved_at' => now('UTC'),
            'error_code' => 'ROUND_RUNTIME_EXCEPTION',
            'error_summary' => 'stalled once',
            'started_at' => now('UTC')->subMinutes(5),
            'finished_at' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-restart-1',
                'question_text' => 'Which authentication mode should the MVP support first?',
                'answer_type' => 'freetext',
                'progress_estimate' => 10,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-restart-1',
                'answer_type' => 'freetext',
                'answer_text' => 'JWT only for v1.',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Legacy build task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/restart-from-beginning')
            ->assertStatus(202)
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.queued', false)
            ->assertJsonPath('data.session_id', $session->id)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_SETUP)
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_SETUP);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_SETUP, (string) $session->status);
        $this->assertSame(InterrogationSession::PHASE_SETUP, (int) $session->phase);
        $this->assertNull($session->cli_session_id);
        $this->assertSame([], (array) $session->summary_json);
        $this->assertSame([], (array) $session->plan_json);
        $this->assertSame([], (array) $session->annotations_json);
        $this->assertSame(['source' => 'ui'], (array) $session->metadata_json);
        $this->assertNull($session->approved_at);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
        $this->assertNull($session->started_at);
        $this->assertNull($session->finished_at);

        $this->assertSame(0, InterrogationEvent::query()->where('interrogation_session_id', $session->id)->count());
        $this->assertSame(0, InterrogationBuildTask::query()->where('interrogation_session_id', $session->id)->count());

        Queue::assertNotPushed(ExecuteInterrogationDiscoveryJob::class);
    }

    public function test_tech_stack_endpoints_add_and_remove_entries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Tech stack setup session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $createResponse = $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/tech-stacks', [
            'name' => 'Laravel 12',
            'documentation_url' => 'https://laravel.com/docs/12.x',
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'Laravel 12')
            ->assertJsonPath('data.documentation_url', 'https://laravel.com/docs/12.x');

        $stackId = (int) $createResponse->json('data.id');

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_TECH_STACK_SETUP, (int) $session->phase);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.tech_stacks.0.id', $stackId)
            ->assertJsonPath('data.tech_stacks.0.name', 'Laravel 12');

        $this->deleteJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/tech-stacks/'.$stackId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.id', $stackId);

        $this->assertDatabaseMissing('interrogation_tech_stacks', [
            'id' => $stackId,
        ]);
    }

    public function test_start_discovery_requires_tech_stack_phase_to_be_reached(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Start discovery guard session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/start-discovery')
            ->assertStatus(409);

        Queue::assertNotPushed(ExecuteInterrogationDiscoveryJob::class);
    }

    public function test_cleanup_invalid_questions_removes_bad_history_and_sanitizes_open_question_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Cleanup invalid questions session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'metadata_json' => [
                'summary_open_question_queue' => [
                    'active' => true,
                    'total' => 4,
                    'pending_questions' => [
                        'Please paste the exact full text (verbatim) of the latest resumed interrogation question that is still unanswered.',
                        'Which authentication mode should the MVP support first?',
                    ],
                    'asked_questions' => [
                        ['question_text' => "Please provide the user's exact answer text for that question (verbatim, including punctuation/emojis if any)."],
                        ['question_text' => 'What expected monthly active users should we design for?'],
                    ],
                    'active_open_question' => [
                        'question_text' => "What is the user's actual answer content for that question?",
                        'ordinal' => 2,
                        'total' => 4,
                    ],
                ],
            ],
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-bad',
                'question_text' => 'Resuming interrogation phase by locating the latest saved unanswered question in this workspace/session state.',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 5,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-bad',
                'answer_type' => 'skip',
                'skip_reason' => 'not_applicable',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 3,
            'payload' => [
                'question_id' => 'q-good',
                'question_text' => 'Which authentication mode should the MVP support first?',
                'answer_type' => 'freetext',
                'options' => [],
                'progress_estimate' => 25,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/cleanup-invalid-questions')
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.removed_question_events', 1)
            ->assertJsonPath('data.removed_answer_events', 1)
            ->assertJsonPath('data.removed_asked_open_questions', 1)
            ->assertJsonPath('data.removed_active_open_question', true)
            ->assertJsonPath('data.queued_next_open_question', true);

        $session->refresh();

        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-bad')
                ->exists()
        );
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_ANSWER)
                ->where('payload->question_id', 'q-bad')
                ->exists()
        );
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-good')
                ->exists()
        );

        $this->assertSame(
            'Which authentication mode should the MVP support first?',
            (string) data_get($session->metadata_json, 'summary_open_question_queue.active_open_question.question_text')
        );

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'Which authentication mode should the MVP support first?')
                && ! str_contains($job->userMessage, 'latest resumed interrogation question');
        });
    }

    public function test_revise_summary_queues_summary_job_with_notes(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary revise session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Initial summary',
                'open_questions' => ['Unresolved point'],
            ],
        ]);

        $longNotes = str_repeat('A', 7000).' Please remove ambiguities and tighten acceptance criteria.';

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-summary', [
            'notes' => $longNotes,
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        Queue::assertPushed(ExecuteInterrogationSummaryJob::class, function (ExecuteInterrogationSummaryJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionNotes)
                && mb_strlen($job->revisionNotes) > 6000
                && str_contains($job->revisionNotes, 'tighten acceptance criteria');
        });
    }

    public function test_continue_interrogation_from_summary_moves_phase_and_queues_first_open_question_prompt(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary reopen session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary content',
                'open_questions' => ['Open question one'],
            ],
            'plan_json' => [
                'plan_markdown' => 'Old plan content',
                'sections' => ['Legacy section'],
            ],
            'approved_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/continue-interrogation', [
            'focus' => 'Resolve taxonomy and concurrency caps.',
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertSame([], (array) $session->summary_json);
        $this->assertSame([], (array) $session->plan_json);
        $this->assertNull($session->approved_at);
        $this->assertTrue((bool) data_get($session->metadata_json, 'summary_open_question_queue.active', false));
        $this->assertSame('Open question one', (string) data_get($session->metadata_json, 'summary_open_question_queue.active_open_question.question_text'));

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'Open question one')
                && str_contains($job->userMessage, 'answer_type="choice"');
        });
    }

    public function test_continue_interrogation_skips_non_actionable_operational_open_questions(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary reopen sanitizes open question queue',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary content',
                'open_questions' => [
                    'Please paste the exact full text (verbatim) of the latest resumed interrogation question that is still unanswered.',
                    'Which authentication mode should the MVP support first?',
                ],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/continue-interrogation')
            ->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertSame(
            'Which authentication mode should the MVP support first?',
            (string) data_get($session->metadata_json, 'summary_open_question_queue.active_open_question.question_text')
        );

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'Which authentication mode should the MVP support first?')
                && ! str_contains($job->userMessage, 'latest resumed interrogation question');
        });
    }

    public function test_confirm_summary_queues_plan_regeneration_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary confirm queues plan',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary content',
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [],
                'private_notes' => '',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/confirm-summary')
            ->assertOk()
            ->assertJsonPath('data.confirmed', true);

        $session->refresh();
        $this->assertSame('queued', (string) data_get($session->metadata_json, 'plan.generation_status'));

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });
    }

    public function test_request_plan_revision_marks_session_and_queues_revision_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan revision request flow',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => 'Current plan content',
            ],
        ]);

        $longNotes = str_repeat('B', 6200).' Add migration details';

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-plan', [
            'action' => 'expand',
            'section' => 'data model',
            'notes' => $longNotes,
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.revision_status', 'queued');

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionPrompt)
                && str_contains($job->revisionPrompt, 'Action: expand')
                && str_contains($job->revisionPrompt, 'Section: data model')
                && str_contains($job->revisionPrompt, 'Add migration details')
                && mb_strlen($job->revisionPrompt) > 5000;
        });

        $session->refresh();
        $this->assertSame('queued', (string) data_get($session->metadata_json, 'plan.revision_status'));
        $this->assertNotNull(data_get($session->metadata_json, 'plan.revision_requested_at'));

        $queuedEvent = InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
            ->latest('sequence')
            ->first();

        $this->assertNotNull($queuedEvent);
        $this->assertSame('plan_revision_queued', (string) data_get($queuedEvent?->payload, 'notice'));
    }

    public function test_request_plan_revision_reopens_failed_planning_session(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan revision reopen failed session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_FAILED,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'error_code' => 'PLAN_RUNTIME_EXCEPTION',
            'error_summary' => 'Previous run failed.',
            'plan_json' => [
                'plan_markdown' => 'Current plan content',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-plan', [
            'action' => 'rewrite',
            'notes' => 'Retry revision',
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });

        $session->refresh();
        $this->assertSame(InterrogationSession::STATUS_PLANNING, (string) $session->status);
        $this->assertSame(InterrogationSession::PHASE_PLANNING, (int) $session->phase);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
    }

    public function test_request_plan_revision_accepts_multi_section_payload(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan revision multi section flow',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => 'Current plan content',
                'sections' => ['Architecture Changes', 'API and Tool Contracts'],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-plan', [
            'action' => 'rewrite',
            'sections' => ['Architecture Changes', 'API and Tool Contracts'],
            'notes' => "## Tighten contracts\n- Ensure canonical naming and schema lock.",
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionPrompt)
                && str_contains($job->revisionPrompt, 'Section: Architecture Changes, API and Tool Contracts')
                && str_contains($job->revisionPrompt, 'Sections: ["Architecture Changes","API and Tool Contracts"]')
                && str_contains($job->revisionPrompt, 'Notes (Markdown):');
        });
    }

    public function test_regenerate_plan_queues_full_plan_rebuild_from_summary_context(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan regeneration flow',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'summary_json' => [
                'summary_markdown' => 'Canonical summary.',
                'goals' => ['Deliver MCP v1'],
                'constraints' => ['No timeline estimates'],
                'acceptance_criteria' => ['Stable tool schemas'],
                'open_questions' => [],
            ],
            'plan_json' => [
                'plan_markdown' => 'Stale plan content',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/regenerate-plan')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.revision_status', 'queued');

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionPrompt)
                && str_contains($job->revisionPrompt, 'Regenerate the implementation plan from the confirmed summary');
        });

        $session->refresh();
        $this->assertSame('queued', (string) data_get($session->metadata_json, 'plan.revision_status'));
        $this->assertNotNull(data_get($session->metadata_json, 'plan.regenerated_at'));
    }

    public function test_show_marks_operational_status_plan_as_not_meaningful(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Invalid plan payload',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => 'Reviewing the locked discovery baseline and existing docs first, then I\'ll rewrite the full implementation plan.',
                'sections' => [],
                'risks' => [],
                'assumptions' => [],
            ],
        ]);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.has_meaningful_plan', false);
    }

    public function test_show_keeps_planning_session_with_missing_plan_in_generation_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Planning in progress',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => '',
                'sections' => [],
                'risks' => [],
                'assumptions' => [],
            ],
            'metadata_json' => [
                'plan' => [
                    'generation_status' => 'queued',
                    'generation_updated_at' => now('UTC')->toIso8601String(),
                ],
            ],
        ]);

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_PLANNING)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_PLANNING)
            ->assertJsonPath('data.has_meaningful_plan', false)
            ->assertJsonPath('data.metadata_json.plan.generation_status', 'queued')
            ->assertJsonMissingPath('data.operator_signal');
    }

    public function test_show_includes_operator_signal_when_discovery_queue_appears_stalled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Stalled discovery queue',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_DISCOVERY,
        ]);

        $session->forceFill([
            'updated_at' => now('UTC')->subSeconds(45),
        ])->save();

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.operator_signal.code', 'QUEUE_WORKER_UNAVAILABLE')
            ->assertJsonPath('data.operator_signal.title', 'Interrogation queue worker may be unavailable');
    }

    public function test_show_includes_operator_signal_when_plan_generation_appears_stalled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Stalled planning queue',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => '',
                'sections' => [],
                'risks' => [],
                'assumptions' => [],
            ],
            'metadata_json' => [
                'plan' => [
                    'generation_status' => 'queued',
                    'generation_updated_at' => now('UTC')->subSeconds(65)->toIso8601String(),
                ],
            ],
        ]);

        $session->forceFill([
            'updated_at' => now('UTC')->subSeconds(65),
        ])->save();

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.has_meaningful_plan', false)
            ->assertJsonPath('data.operator_signal.code', 'QUEUE_WORKER_UNAVAILABLE')
            ->assertJsonPath('data.operator_signal.title', 'Interrogation queue worker may be unavailable');
    }

    public function test_summary_open_question_queue_advances_until_summary_regeneration(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary queue flow',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary content',
                'open_questions' => ['First unresolved', 'Second unresolved'],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/continue-interrogation')
            ->assertStatus(202);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'First unresolved');
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/answer', [
            'question_id' => 'q-open-1',
            'answer_type' => 'freetext',
            'answer_text' => 'Answer one',
        ])->assertStatus(202);

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'Second unresolved');
        });
        Queue::assertPushed(ExecuteInterrogationRoundJob::class, 2);
        Queue::assertNotPushed(ExecuteInterrogationSummaryJob::class);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/answer', [
            'question_id' => 'q-open-2',
            'answer_type' => 'freetext',
            'answer_text' => 'Answer two',
        ])->assertStatus(202);

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_SUMMARY, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_SUMMARIZING, (string) $session->status);
        $this->assertNull(data_get($session->metadata_json, 'summary_open_question_queue'));

        Queue::assertPushed(ExecuteInterrogationSummaryJob::class, function (ExecuteInterrogationSummaryJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });
    }

    public function test_continue_interrogation_with_revisit_question_reopens_without_queueing_round_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Summary revisit flow',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary content',
                'open_questions' => ['First unresolved'],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/continue-interrogation', [
            'revisit_question_id' => 'q-legacy-123',
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertNull(data_get($session->metadata_json, 'summary_open_question_queue'));

        Queue::assertNotPushed(ExecuteInterrogationRoundJob::class);
    }

    public function test_show_endpoint_normalizes_legacy_embedded_summary_parameters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy summary payload',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => "## Summary\n\n<parameter name=\"goals\">[\"Goal One\",\"Goal Two\"]",
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [],
            ],
        ]);

        $response = $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk();

        $this->assertSame(['Goal One', 'Goal Two'], $response->json('data.summary_json.goals'));
        $this->assertStringNotContainsString('<parameter name="goals">', (string) $response->json('data.summary_json.summary_markdown'));
    }

    public function test_confirm_summary_blocks_when_legacy_payload_contains_embedded_open_questions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy open questions',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => "## Summary\n\n<parameter name=\"open_questions\">[\"Need one more decision\"]",
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/confirm-summary')
            ->assertStatus(409);
    }

    public function test_show_endpoint_reconciles_answered_open_questions_from_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Reconciled open questions',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary body',
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [
                    'What capabilities taxonomy should the MVP seed?',
                    'What should remain unresolved?',
                ],
                'private_notes' => '',
            ],
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'oq-capabilities-taxonomy',
                'question_text' => '**Open Question 1: What capabilities taxonomy should the MVP seed?**',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'oq-capabilities-taxonomy',
                'answer_type' => 'choice',
                'selected_option' => 'Option B',
            ],
            'event_ts' => now('UTC'),
        ]);

        $response = $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk();

        $this->assertSame(
            ['What should remain unresolved?'],
            $response->json('data.summary_json.open_questions')
        );
    }

    public function test_show_endpoint_reconciles_open_questions_by_open_question_ordinal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Ordinal reconciliation',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary body',
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [
                    'What specific capabilities taxonomy should the MVP seed?',
                    'What should remain unresolved?',
                ],
                'private_notes' => '',
            ],
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'oq-capabilities-taxonomy',
                'question_text' => 'Open Question 1: What capabilities taxonomy should the MVP seed?',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'oq-capabilities-taxonomy',
                'answer_type' => 'choice',
                'selected_option' => 'Option B',
            ],
            'event_ts' => now('UTC'),
        ]);

        $response = $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk();

        $this->assertSame(
            ['What should remain unresolved?'],
            $response->json('data.summary_json.open_questions')
        );
    }

    public function test_show_endpoint_reconciles_open_questions_by_oq_marker_question_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'OQ marker reconciliation',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => 'Summary body',
                'goals' => [],
                'constraints' => [],
                'acceptance_criteria' => [],
                'open_questions' => [
                    'OQ2: Neo4j edition — Community, Enterprise, or Aura?',
                    'OQ11: Neo4j temporal queries — current-state only or point-in-time?',
                ],
                'private_notes' => '',
            ],
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'oq2-neo4j-edition',
                'question_text' => 'Which Neo4j edition and deployment model will be used for the temporal knowledge graph?',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'oq2-neo4j-edition',
                'answer_type' => 'choice',
                'selected_option' => 'Community',
            ],
            'event_ts' => now('UTC'),
        ]);

        $response = $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk();

        $this->assertSame(
            ['OQ11: Neo4j temporal queries — current-state only or point-in-time?'],
            $response->json('data.summary_json.open_questions')
        );
    }

    public function test_show_endpoint_normalizes_plan_and_removes_estimate_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan normalization session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => "## Delivery Plan\n- Build core API.\n\n## Total Estimated Effort\n- 10-15 days for 1 developer.\n- Critical path: A -> B",
                'sections' => ['Delivery Plan', 'Total Estimated Effort'],
                'risks' => ['Timeline may slip'],
                'assumptions' => ['DB exists'],
            ],
        ]);

        $response = $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk();

        $plan = $response->json('data.plan_json');
        $this->assertIsArray($plan);
        $this->assertStringContainsString('## Delivery Plan', (string) ($plan['plan_markdown'] ?? ''));
        $this->assertStringNotContainsString('Total Estimated Effort', (string) ($plan['plan_markdown'] ?? ''));
        $this->assertStringNotContainsString('Critical path', (string) ($plan['plan_markdown'] ?? ''));
        $this->assertSame(['Delivery Plan'], $plan['sections'] ?? []);
        $this->assertSame([], $plan['risks'] ?? []);

        $session->refresh();
        $this->assertSame(['Delivery Plan'], $session->plan_json['sections'] ?? []);
    }

    public function test_build_endpoints_queue_jobs_and_update_build_state(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build workflow session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => "## Scope & Acceptance Criteria\n- Define v1 MCP workflow scope.\n- Lock acceptance criteria by lifecycle state.\n\n## Technical Design\n- Document canonical request/response contracts.\n- Define failure and retry semantics.\n\n## Verification\n- Add regression tests for plan regeneration and payload guard paths.",
                'sections' => ['Scope & Acceptance Criteria', 'Technical Design', 'Verification'],
                'risks' => ['Schema drift across clients'],
                'assumptions' => ['Summary is locked before planning'],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-plan')
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_BUILD_RULES)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_BUILD_RULES);

        $session->refresh();
        $this->assertNotNull($session->approved_at);
        $this->assertSame(InterrogationSession::PHASE_BUILD_RULES, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_BUILD_RULES, $session->status);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/generate-build-tasks')
            ->assertStatus(202)
            ->assertJsonPath('data.build_status', 'generating_tasks');

        Queue::assertPushed(GenerateInterrogationBuildTasksJob::class, function (GenerateInterrogationBuildTasksJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });

        $buildTask = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task 1',
            'description' => 'First task',
            'instructions_markdown' => 'Implement first task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $session->metadata_json = [
            ...((array) ($session->metadata_json ?? [])),
            'build' => [
                'status' => 'ready',
                'task_count' => 1,
            ],
        ];
        $session->phase = InterrogationSession::PHASE_BUILD_TASKS;
        $session->status = InterrogationSession::STATUS_BUILD_TASKS;
        $session->save();

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-build-tasks')
            ->assertStatus(202)
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.task_provider_sync_queued', false);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/start-build')
            ->assertStatus(202)
            ->assertJsonPath('data.build_status', 'running');

        $session->refresh();
        $this->assertSame(InterrogationSession::PHASE_BUILD_EXECUTION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_BUILD_EXECUTING, $session->status);

        Queue::assertPushed(ExecuteInterrogationBuildJob::class, function (ExecuteInterrogationBuildJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/pause-build')
            ->assertStatus(202)
            ->assertJsonPath('data.build_status', 'paused');

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build/clarify', [
            'message' => 'Use repository patterns for error envelopes.',
        ])->assertStatus(202)
            ->assertJsonPath('data.task_id', $buildTask->id);

        $session->refresh();
        $this->assertFalse((bool) data_get($session->metadata_json, 'build.clarification_required'));
        $this->assertNull(data_get($session->metadata_json, 'build.clarification_excerpt'));

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/resume-build')
            ->assertStatus(202)
            ->assertJsonPath('data.build_status', 'running');

        Queue::assertPushed(ExecuteInterrogationBuildJob::class, 2);
    }

    public function test_build_tasks_can_be_created_updated_and_deleted_during_task_phase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build task CRUD session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'ready',
                    'task_count' => 1,
                    'tasks_approved_at' => now('UTC')->subMinute()->toIso8601String(),
                    'task_provider_sync' => [
                        'status' => 'synced',
                        'driver' => 'linear',
                    ],
                ],
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Existing task',
            'description' => 'Keep this task',
            'instructions_markdown' => 'Run tests first.',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $create = $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build-tasks', [
            'title' => 'Add API validation',
            'description' => 'Add validation for new route.',
            'instructions_markdown' => "1. Add request validation.\n2. Add feature coverage.",
        ])->assertStatus(201)
            ->assertJsonPath('data.title', 'Add API validation')
            ->assertJsonPath('data.sequence', 2);

        $createdTaskId = (int) $create->json('data.id');

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build-tasks/'.$createdTaskId, [
            'title' => 'Add API validation and tests',
            'description' => 'Cover error and success response cases.',
            'instructions_markdown' => "1. Write failing tests.\n2. Implement endpoint updates.\n3. Re-run suite.",
        ])->assertOk()
            ->assertJsonPath('data.title', 'Add API validation and tests')
            ->assertJsonPath('data.status', InterrogationBuildTask::STATUS_PENDING);

        $this->deleteJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build-tasks/'.$createdTaskId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.task_id', $createdTaskId);

        $session->refresh();

        $this->assertNull(data_get($session->metadata_json, 'build.tasks_approved_at'));
        $this->assertSame('idle', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));
        $this->assertSame(1, InterrogationBuildTask::query()->where('interrogation_session_id', $session->id)->count());
        $this->assertDatabaseMissing('interrogation_build_tasks', [
            'id' => $createdTaskId,
        ]);
    }

    public function test_build_tasks_can_be_reordered_during_task_phase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build task reorder session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'ready',
                    'task_count' => 3,
                    'tasks_approved_at' => now('UTC')->subMinute()->toIso8601String(),
                    'task_provider_sync' => [
                        'status' => 'synced',
                        'driver' => 'linear',
                    ],
                ],
            ],
        ]);

        $taskA = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task A',
            'description' => 'First',
            'instructions_markdown' => 'First instructions',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);
        $taskB = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Task B',
            'description' => 'Second',
            'instructions_markdown' => 'Second instructions',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);
        $taskC = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'Task C',
            'description' => 'Third',
            'instructions_markdown' => 'Third instructions',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build-tasks/reorder', [
            'task_ids' => [$taskC->id, $taskA->id, $taskB->id],
        ])->assertOk()
            ->assertJsonPath('data.tasks.0.id', (int) $taskC->id)
            ->assertJsonPath('data.tasks.0.sequence', 1)
            ->assertJsonPath('data.tasks.1.id', (int) $taskA->id)
            ->assertJsonPath('data.tasks.1.sequence', 2)
            ->assertJsonPath('data.tasks.2.id', (int) $taskB->id)
            ->assertJsonPath('data.tasks.2.sequence', 3);

        $session->refresh();
        $orderedIds = InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->ordered()
            ->pluck('id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $this->assertSame([(int) $taskC->id, (int) $taskA->id, (int) $taskB->id], $orderedIds);
        $this->assertNull(data_get($session->metadata_json, 'build.tasks_approved_at'));
        $this->assertSame('idle', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));
    }

    public function test_show_build_state_normalizes_structured_clarification_excerpt_and_exposes_effective_run_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build clarification visibility session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'paused',
                    'pause_reason' => 'clarification',
                    'clarification_required' => true,
                    'task_count' => 1,
                ],
            ],
        ]);

        $job = AgentJob::factory()->for($user)->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [
                'clarification_required' => true,
                'clarification_excerpt' => json_encode([
                    'type' => 'item.completed',
                    'item' => [
                        'type' => 'agent_message',
                        'text' => 'I need clarification on whether we should keep legacy event names.',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Clarification task',
            'description' => 'Needs clarification before progressing.',
            'instructions_markdown' => 'Request clarification from the operator.',
            'status' => InterrogationBuildTask::STATUS_BLOCKED,
            'attempt_count' => 1,
            'agent_job_run_id' => $run->id,
        ]);

        $session->metadata_json = [
            ...((array) ($session->metadata_json ?? [])),
            'build' => [
                ...((array) data_get($session->metadata_json, 'build', [])),
                'active_task_id' => (int) $task->id,
                'active_run_id' => (int) $run->id,
            ],
        ];
        $session->save();

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.build.flags.clarification_required', true)
            ->assertJsonPath('data.build.flags.clarification_excerpt', 'I need clarification on whether we should keep legacy event names.')
            ->assertJsonPath('data.build.active_run.status', AgentJobRun::STATUS_SUCCEEDED)
            ->assertJsonPath('data.build.active_run.effective_status', 'blocked_clarification');
    }

    public function test_show_build_state_reports_failed_task_effective_run_status_when_run_succeeded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build failed status projection session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'failed',
                    'pause_reason' => null,
                    'task_count' => 1,
                ],
            ],
        ]);

        $job = AgentJob::factory()->for($user)->create();
        $run = AgentJobRun::factory()->for($job, 'job')->create([
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'metadata_json' => [],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Failed task',
            'description' => 'Run succeeded but task-level validation failed.',
            'instructions_markdown' => 'Execute and verify.',
            'status' => InterrogationBuildTask::STATUS_FAILED,
            'attempt_count' => 1,
            'last_error' => 'Runner exited successfully but did not execute concrete implementation or verification commands.',
            'agent_job_run_id' => $run->id,
        ]);

        $session->metadata_json = [
            ...((array) ($session->metadata_json ?? [])),
            'build' => [
                ...((array) data_get($session->metadata_json, 'build', [])),
                'active_task_id' => (int) $task->id,
                'active_run_id' => (int) $run->id,
            ],
        ];
        $session->save();

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id)
            ->assertOk()
            ->assertJsonPath('data.build.active_task.status', InterrogationBuildTask::STATUS_FAILED)
            ->assertJsonPath('data.build.active_run.status', AgentJobRun::STATUS_SUCCEEDED)
            ->assertJsonPath('data.build.active_run.effective_status', 'failed_task');
    }

    public function test_regenerate_single_build_task_queues_job_with_amend_notes(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Task regeneration session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'ready',
                    'task_count' => 1,
                    'tasks_approved_at' => now('UTC')->subMinute()->toIso8601String(),
                ],
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Current task',
            'description' => 'Existing description',
            'instructions_markdown' => 'Existing instructions',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/build-tasks/'.$task->id.'/regenerate', [
            'amend_notes' => 'Split this into API-first implementation with stricter acceptance checks.',
            'additional_context' => 'Keep compatibility with legacy API clients and preserve existing response keys.',
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.task_id', $task->id);

        Queue::assertPushed(RegenerateInterrogationBuildTaskJob::class, function (RegenerateInterrogationBuildTaskJob $job) use ($session, $task): bool {
            return (int) $job->sessionId === (int) $session->id
                && (int) $job->taskId === (int) $task->id
                && str_contains($job->amendNotes, 'API-first implementation')
                && str_contains((string) $job->additionalContext, 'legacy API clients');
        });

        $task->refresh();
        $session->refresh();

        $this->assertSame('queued', (string) data_get($task->metadata_json, 'regeneration.status'));
        $this->assertSame('Split this into API-first implementation with stricter acceptance checks.', (string) data_get($task->metadata_json, 'regeneration.amend_notes'));
        $this->assertSame('Keep compatibility with legacy API clients and preserve existing response keys.', (string) data_get($task->metadata_json, 'regeneration.additional_context'));
        $this->assertNull(data_get($session->metadata_json, 'build.tasks_approved_at'));
        $this->assertSame('idle', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));
    }

    public function test_approve_build_tasks_queues_task_provider_sync_when_provider_connected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build task provider sync session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'ready',
                    'task_count' => 1,
                ],
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Sync me',
            'description' => 'Task should sync to provider',
            'instructions_markdown' => 'Execute sync',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'team_id' => 'team_123',
                'project_sync' => [
                    'mode' => 'existing',
                    'selected_project_id' => 'project_abc',
                    'selected_project_name' => 'Existing Linear Project',
                    'selected_project_url' => 'https://linear.app/acme/project/existing',
                ],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-build-tasks')
            ->assertStatus(202)
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.task_provider_sync_queued', true);

        Queue::assertPushed(SyncInterrogationTasksToTaskProviderJob::class, function (SyncInterrogationTasksToTaskProviderJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });

        $session->refresh();

        $this->assertSame('queued', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));
        $this->assertSame('linear', (string) data_get($session->metadata_json, 'build.task_provider_sync.driver'));
        $this->assertSame('existing', (string) data_get($session->metadata_json, 'build.task_provider_sync.project_mode'));
        $this->assertSame('project_abc', (string) data_get($session->metadata_json, 'build.task_provider_sync.project_id'));
        $this->assertSame('Existing Linear Project', (string) data_get($session->metadata_json, 'build.task_provider_sync.project_name'));
        $this->assertNotNull(data_get($session->metadata_json, 'build.tasks_approved_at'));
    }

    public function test_provider_projects_endpoint_returns_projects_for_connected_provider(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Provider projects list session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'team_id' => 'team-2',
                'team_name' => 'Games',
                'project_sync' => [
                    'mode' => 'existing',
                    'selected_project_id' => 'proj-2',
                    'selected_project_name' => 'Beta',
                    'selected_project_url' => 'https://linear.app/acme/project/beta',
                ],
            ],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function fetchIdentity(string $accessToken): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [
                    ['id' => 'proj-1', 'name' => 'Alpha', 'url' => 'https://linear.app/acme/project/alpha', 'state' => 'started'],
                    ['id' => 'proj-2', 'name' => 'Beta', 'url' => 'https://linear.app/acme/project/beta', 'state' => 'planned'],
                ];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [
                    ['id' => 'team-1', 'name' => 'Acme', 'key' => 'ACME'],
                    ['id' => 'team-2', 'name' => 'Games', 'key' => 'GAME'],
                ];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {
                throw new RuntimeException('Not used in this test.');
            }
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/providers/linear/projects')
            ->assertOk()
            ->assertJsonPath('data.driver', 'linear')
            ->assertJsonPath('data.selected_team_id', 'team-2')
            ->assertJsonPath('data.selected_team_name', 'Games')
            ->assertJsonPath('data.teams.0.id', 'team-1')
            ->assertJsonPath('data.project_mode', 'existing')
            ->assertJsonPath('data.selected_project_id', 'proj-2')
            ->assertJsonPath('data.projects.0.id', 'proj-1')
            ->assertJsonPath('data.projects.1.id', 'proj-2');
    }

    public function test_provider_settings_endpoint_persists_existing_project_selection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Provider settings session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        $provider = ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'identity' => [
                    'team_id' => 'team_1',
                    'team_name' => 'Acme',
                ],
            ],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function fetchIdentity(string $accessToken): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [
                    ['id' => 'project-1', 'name' => 'Project One', 'url' => 'https://linear.app/acme/project/one', 'state' => 'started'],
                    ['id' => 'project-2', 'name' => 'Project Two', 'url' => 'https://linear.app/acme/project/two', 'state' => 'planned'],
                ];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [
                    ['id' => 'team-1', 'name' => 'Acme', 'key' => 'ACME'],
                    ['id' => 'team-2', 'name' => 'Games', 'key' => 'GAME'],
                ];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {
                throw new RuntimeException('Not used in this test.');
            }
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/providers/linear/settings', [
            'team_id' => 'team-2',
            'project_mode' => 'existing',
            'existing_project_id' => 'project-2',
        ])
            ->assertOk()
            ->assertJsonPath('data.selected_team_id', 'team-2')
            ->assertJsonPath('data.selected_team_name', 'Games')
            ->assertJsonPath('data.project_mode', 'existing')
            ->assertJsonPath('data.selected_project_id', 'project-2')
            ->assertJsonPath('data.selected_project_name', 'Project Two');

        $provider->refresh();

        $this->assertSame('team-2', (string) data_get($provider->metadata_json, 'team_id'));
        $this->assertSame('Games', (string) data_get($provider->metadata_json, 'team_name'));
        $this->assertSame('existing', (string) data_get($provider->metadata_json, 'project_sync.mode'));
        $this->assertSame('project-2', (string) data_get($provider->metadata_json, 'project_sync.selected_project_id'));
        $this->assertSame('Project Two', (string) data_get($provider->metadata_json, 'project_sync.selected_project_name'));
    }

    public function test_provider_settings_endpoint_rejects_invalid_team_selection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Provider invalid team selection session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'identity' => [
                    'team_id' => 'team-1',
                    'team_name' => 'Acme',
                ],
            ],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function fetchIdentity(string $accessToken): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                throw new RuntimeException('Not used in this test.');
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [
                    ['id' => 'team-1', 'name' => 'Acme', 'key' => 'ACME'],
                    ['id' => 'team-2', 'name' => 'Games', 'key' => 'GAME'],
                ];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                throw new RuntimeException('Not used in this test.');
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {
                throw new RuntimeException('Not used in this test.');
            }
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $this->patchJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/providers/linear/settings', [
            'team_id' => 'team-unknown',
            'project_mode' => 'create_new',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TASK_PROVIDER_TEAM_INVALID')
            ->assertJsonPath('errors.team_id.0', 'Selected Linear team is not available to this connection.');
    }

    public function test_generate_build_tasks_persists_project_rules_from_markdown_and_uploaded_files(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build rules context session',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_RULES,
            'phase' => InterrogationSession::PHASE_BUILD_RULES,
            'approved_at' => now('UTC'),
            'plan_json' => [
                'plan_markdown' => "## Scope\n- Add build rules context support.\n\n## Validation\n- Add tests.",
                'sections' => ['Scope', 'Validation'],
                'risks' => [],
                'assumptions' => ['Plan approved'],
            ],
            'metadata_json' => [
                'build' => [
                    'status' => 'ready',
                ],
            ],
        ]);

        $response = $this->post(
            '/agent/api/v1/interrogation/sessions/'.$session->id.'/generate-build-tasks',
            [
                'project_rules' => json_encode([
                    [
                        'title' => 'Manual Rule',
                        'markdown' => 'Keep controllers thin and push business logic to services.',
                    ],
                ], JSON_UNESCAPED_SLASHES),
                'project_rule_files' => [
                    UploadedFile::fake()->createWithContent('rules.md', "# Uploaded Rule\n\nAlways add regression tests for bug fixes.\n"),
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(202)
            ->assertJsonPath('data.build_status', 'generating_tasks');

        $session->refresh();

        $rules = data_get($session->metadata_json, 'build.project_rules', []);
        $this->assertIsArray($rules);
        $this->assertCount(2, $rules);
        $this->assertSame('Manual Rule', data_get($rules, '0.title'));
        $this->assertSame('manual', data_get($rules, '0.source'));
        $this->assertSame('uploaded', data_get($rules, '1.source'));
        $this->assertStringContainsString('Always add regression tests', (string) data_get($rules, '1.markdown'));

        Queue::assertPushed(GenerateInterrogationBuildTasksJob::class, function (GenerateInterrogationBuildTasksJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });
    }

    public function test_approve_plan_accepts_legacy_completed_status_in_planning_phase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy planning completion status',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_COMPLETED,
            'phase' => InterrogationSession::PHASE_PLANNING,
            'plan_json' => [
                'plan_markdown' => "## Scope\n- Preserve legacy completed status compatibility for planning approvals.\n\n## Implementation\n- Transition approved plan to build-tasks phase.\n- Persist approval timestamp.\n\n## Validation\n- Ensure API returns build-tasks state on success.",
                'sections' => ['Scope', 'Implementation', 'Validation'],
                'risks' => ['Legacy status behavior may regress'],
                'assumptions' => ['Session is in planning phase'],
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-plan')
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_BUILD_RULES)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_BUILD_RULES);

        $session->refresh();
        $this->assertNotNull($session->approved_at);
        $this->assertSame(InterrogationSession::PHASE_BUILD_RULES, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_BUILD_RULES, $session->status);
    }

    public function test_start_build_requires_generated_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build no tasks',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'plan_json' => [
                'plan_markdown' => 'Plan body',
            ],
            'approved_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/start-build')
            ->assertStatus(409);
    }

    public function test_start_build_restart_all_requeues_completed_and_failed_tasks(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Build rerun all',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'plan_json' => [
                'plan_markdown' => 'Plan body',
            ],
            'approved_at' => now('UTC'),
            'metadata_json' => [
                'build' => [
                    'status' => 'failed',
                    'task_count' => 2,
                    'tasks_approved_at' => now('UTC')->toIso8601String(),
                ],
            ],
        ]);

        $completedTask = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Completed task',
            'description' => 'done',
            'instructions_markdown' => 'done',
            'status' => InterrogationBuildTask::STATUS_COMPLETED,
            'attempt_count' => 1,
            'started_at' => now('UTC')->subMinutes(3),
            'finished_at' => now('UTC')->subMinutes(2),
        ]);

        $failedTask = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'Failed task',
            'description' => 'failed',
            'instructions_markdown' => 'failed',
            'status' => InterrogationBuildTask::STATUS_FAILED,
            'attempt_count' => 1,
            'last_error' => 'Build task execution failed.',
            'started_at' => now('UTC')->subMinute(),
            'finished_at' => now('UTC'),
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/start-build', [
            'restart_all' => true,
        ])->assertStatus(202)
            ->assertJsonPath('data.build_status', 'running');

        $completedTask->refresh();
        $failedTask->refresh();
        $session->refresh();

        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, (string) $completedTask->status);
        $this->assertNull($completedTask->last_error);
        $this->assertNull($completedTask->started_at);
        $this->assertNull($completedTask->finished_at);

        $this->assertSame(InterrogationBuildTask::STATUS_PENDING, (string) $failedTask->status);
        $this->assertNull($failedTask->last_error);
        $this->assertNull($failedTask->started_at);
        $this->assertNull($failedTask->finished_at);

        Queue::assertPushed(ExecuteInterrogationBuildJob::class, function (ExecuteInterrogationBuildJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });
    }
}
