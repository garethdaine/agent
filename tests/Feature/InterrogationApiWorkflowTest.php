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
            'payload' => ['question_id' => 'q-pending', 'question_text' => 'Pending question'],
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

    public function test_continue_interrogation_from_summary_moves_phase_and_queues_round(): void
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
        ]);

        $this->postJson('/agent/api/v1/interrogation/sessions/'.$session->id.'/continue-interrogation', [
            'focus' => 'Resolve taxonomy and concurrency caps.',
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session) {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && str_contains($job->userMessage, 'Resolve taxonomy and concurrency caps');
        });
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
