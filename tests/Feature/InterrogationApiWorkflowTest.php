<?php

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Jobs\ExecuteInterrogationDiscoveryJob;
use App\Jobs\ExecuteInterrogationPlanJob;
use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Jobs\GenerateInterrogationBuildTasksJob;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($sessionId) {
            return (int) $job->sessionId === (int) $sessionId;
        });

        $this->getJson('/agent/api/v1/interrogation/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId);

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

    public function test_restart_from_beginning_clears_history_and_requeues_discovery(): void
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
            ->assertJsonPath('data.queued', true)
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

        Queue::assertPushed(ExecuteInterrogationDiscoveryJob::class, function (ExecuteInterrogationDiscoveryJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id;
        });
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

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-summary', [
            'notes' => 'Please remove ambiguities and tighten acceptance criteria.',
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        Queue::assertPushed(ExecuteInterrogationSummaryJob::class, function (ExecuteInterrogationSummaryJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionNotes)
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

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/revise-plan', [
            'action' => 'expand',
            'section' => 'data model',
            'notes' => 'Add migration details',
        ])->assertStatus(202)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.revision_status', 'queued');

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function (ExecuteInterrogationPlanJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && is_string($job->revisionPrompt)
                && str_contains($job->revisionPrompt, 'Action: expand')
                && str_contains($job->revisionPrompt, 'Section: data model');
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
                'plan_markdown' => 'Plan body',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-plan')
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_BUILD_TASKS)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_BUILD_TASKS);

        $session->refresh();
        $this->assertNotNull($session->approved_at);
        $this->assertSame(InterrogationSession::PHASE_BUILD_TASKS, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_BUILD_TASKS, $session->status);

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
        $session->save();

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

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/resume-build')
            ->assertStatus(202)
            ->assertJsonPath('data.build_status', 'running');

        Queue::assertPushed(ExecuteInterrogationBuildJob::class, 2);
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
                'plan_markdown' => 'Plan body',
            ],
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/approve-plan')
            ->assertOk()
            ->assertJsonPath('data.phase', InterrogationSession::PHASE_BUILD_TASKS)
            ->assertJsonPath('data.status', InterrogationSession::STATUS_BUILD_TASKS);

        $session->refresh();
        $this->assertNotNull($session->approved_at);
        $this->assertSame(InterrogationSession::PHASE_BUILD_TASKS, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_BUILD_TASKS, $session->status);
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
}
