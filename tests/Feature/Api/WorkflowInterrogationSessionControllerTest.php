<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureLicenseValid;
use App\Jobs\GenerateWorkflowInterrogationPlanJob;
use App\Jobs\GenerateWorkflowInterrogationRoundJob;
use App\Models\User;
use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationBatch;
use App\Models\WorkflowInterrogationBatchQuestion;
use App\Models\WorkflowInterrogationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowInterrogationSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureLicenseValid::class);
        config()->set('agent.allowed_working_directory_bases', [base_path()]);
    }

    public function test_store_allows_claude_runner_sessions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/agent/api/v1/workflow-interrogator/sessions', [
            'name' => 'Claude workflow discovery',
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'company_description' => 'Operations-heavy business',
            'workflow_title' => 'Order exception handling',
            'workflow_brief' => 'Investigate how order exceptions are triaged across teams.',
            'target_teams' => ['Operations', 'Support'],
            'systems' => ['ERP', 'Email'],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.runner_type', 'claude')
            ->assertJsonPath('data.model', 'claude-opus-4-6');

        $this->assertDatabaseHas('workflow_interrogation_sessions', [
            'runner_type' => 'claude',
            'workflow_title' => 'Order exception handling',
        ]);
    }

    public function test_store_allows_custom_runner_sessions_without_model(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/agent/api/v1/workflow-interrogator/sessions', [
            'name' => 'Custom workflow discovery',
            'runner_type' => 'custom',
            'project_directory' => base_path(),
            'interrogation_mode' => 'general',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice approvals',
            'workflow_brief' => 'Clarify the current-state invoice approval workflow.',
            'target_teams' => ['Finance'],
            'systems' => ['Spreadsheet'],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.runner_type', 'custom')
            ->assertJsonPath('data.model', null);

        $this->assertDatabaseHas('workflow_interrogation_sessions', [
            'runner_type' => 'custom',
            'workflow_title' => 'Invoice approvals',
        ]);
    }

    public function test_store_accepts_uploaded_session_attachments(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/agent/api/v1/workflow-interrogator/sessions', [
            'name' => 'Attachment-backed discovery',
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'company_description' => 'Operations-heavy business',
            'workflow_title' => 'Returns workflow',
            'workflow_brief' => 'Clarify how returns are processed.',
            'target_teams' => ['Operations'],
            'systems' => ['ERP'],
            'attachments' => [
                UploadedFile::fake()->createWithContent('notes.md', "# Context\n\nReturns require manager approval.\n"),
                UploadedFile::fake()->image('workflow.png'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(202)
            ->assertJsonCount(2, 'data.attachments')
            ->assertJsonPath('data.attachments.0.filename', 'notes.md');

        $sessionId = (int) $response->json('data.id');
        $session = WorkflowInterrogationSession::query()->with('attachments')->findOrFail($sessionId);

        $this->assertCount(2, $session->attachments);
        $this->assertNotNull($session->attachments->firstWhere('filename', 'notes.md')?->extracted_text);

        foreach ($session->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->storage_path);
        }
    }

    public function test_download_attachment_returns_the_uploaded_file_for_the_session_owner(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Returns workflow',
            'workflow_brief' => 'Clarify how returns are processed.',
        ]);

        Storage::disk('local')->put('workflow-interrogator/test/context.txt', 'returns context');

        $attachment = WorkflowInterrogationAttachment::query()->create([
            'workflow_interrogation_session_id' => (int) $session->id,
            'filename' => 'context.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 15,
            'storage_disk' => 'local',
            'storage_path' => 'workflow-interrogator/test/context.txt',
        ]);

        $this->actingAs($user)
            ->get(route('api.workflow-interrogator.attachments.download', [
                'id' => $session->id,
                'attachmentId' => $attachment->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

    public function test_store_rejects_custom_runner_sessions_with_model_selection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/agent/api/v1/workflow-interrogator/sessions', [
            'name' => 'Invalid custom session',
            'runner_type' => 'custom',
            'model' => 'should-not-exist',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Returns workflow',
            'workflow_brief' => 'Clarify how returns are processed.',
        ]);

        $response->assertStatus(422);

        $payload = $response->json();
        $errors = (array) ($payload['errors'] ?? []);
        $envelopeDetails = (array) data_get($payload, 'error.details', []);

        $this->assertTrue(
            array_key_exists('model', $errors) || array_key_exists('model', $envelopeDetails),
            'Expected custom-runner model validation error to be present in either errors or error.details.'
        );

        $this->assertSame(0, WorkflowInterrogationSession::query()->count());
    }

    public function test_start_dispatches_round_generation_job(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $create = $this->actingAs($user)->postJson('/agent/api/v1/workflow-interrogator/sessions', [
            'name' => 'Lifecycle test',
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice exceptions',
            'workflow_brief' => 'Interrogate the invoice exception workflow.',
            'target_teams' => ['Operations'],
            'systems' => ['ERP'],
        ])->assertStatus(202);

        $sessionId = (int) $create->json('data.id');

        $this->actingAs($user)
            ->postJson("/agent/api/v1/workflow-interrogator/sessions/{$sessionId}/start")
            ->assertStatus(202)
            ->assertJsonPath('data.status', WorkflowInterrogationSession::STATUS_INTERROGATING)
            ->assertJsonPath('data.processing.kind', 'round')
            ->assertJsonPath('data.processing.state', 'queued');

        Queue::assertPushed(GenerateWorkflowInterrogationRoundJob::class, function (GenerateWorkflowInterrogationRoundJob $job) use ($sessionId): bool {
            return $job->sessionId === $sessionId && $job->latestAnswers === [];
        });
    }

    public function test_show_returns_active_batch_questions_from_persisted_records(): void
    {
        $user = User::factory()->create();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice exceptions',
            'workflow_brief' => 'Interrogate the invoice exception workflow.',
            'status' => WorkflowInterrogationSession::STATUS_INTERROGATING,
            'phase' => WorkflowInterrogationSession::PHASE_INTERROGATION,
            'current_round' => 1,
        ]);

        $batch = WorkflowInterrogationBatch::query()->create([
            'workflow_interrogation_session_id' => (int) $session->id,
            'round' => 1,
            'is_active' => true,
        ]);

        WorkflowInterrogationBatchQuestion::query()->create([
            'workflow_interrogation_batch_id' => (int) $batch->id,
            'position' => 0,
            'question_key' => 'q-entry',
            'prompt' => 'What should trigger the workflow?',
            'answer_type' => 'choice',
            'options_json' => ['New brief', 'Manual request'],
            'is_required' => true,
            'rationale' => 'Entry conditions control scope.',
            'category' => 'scope',
        ]);

        $this->actingAs($user)
            ->getJson("/agent/api/v1/workflow-interrogator/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.active_batch.round', 1)
            ->assertJsonPath('data.active_batch.question_count', 1)
            ->assertJsonPath('data.active_batch.questions.0.question_id', 'q-entry')
            ->assertJsonPath('data.active_batch.questions.0.options.0', 'New brief');
    }

    public function test_submit_batch_dispatches_next_round_generation_job(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice exceptions',
            'workflow_brief' => 'Interrogate the invoice exception workflow.',
            'status' => WorkflowInterrogationSession::STATUS_INTERROGATING,
            'phase' => WorkflowInterrogationSession::PHASE_INTERROGATION,
            'current_round' => 1,
        ]);
        $batch = WorkflowInterrogationBatch::query()->create([
            'workflow_interrogation_session_id' => (int) $session->id,
            'round' => 1,
            'is_active' => true,
        ]);
        WorkflowInterrogationBatchQuestion::query()->create([
            'workflow_interrogation_batch_id' => (int) $batch->id,
            'position' => 0,
            'question_key' => 'q-actors',
            'prompt' => 'Who owns the workflow today?',
            'answer_type' => 'choice',
            'options_json' => ['Operations', 'Finance'],
            'is_required' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/agent/api/v1/workflow-interrogator/sessions/{$session->id}/submit-batch", [
                'answers' => [
                    [
                        'question_id' => 'q-actors',
                        'answer_type' => 'choice',
                        'selected_option' => 'Operations',
                    ],
                ],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', WorkflowInterrogationSession::STATUS_INTERROGATING)
            ->assertJsonPath('data.processing.kind', 'round')
            ->assertJsonPath('data.processing.state', 'queued')
            ->assertJsonPath('data.active_batch', null);

        $this->assertDatabaseHas('workflow_interrogation_batch_answers', [
            'workflow_interrogation_batch_question_id' => WorkflowInterrogationBatchQuestion::query()->value('id'),
            'answer_type' => 'choice',
            'selected_option' => 'Operations',
        ]);
        $this->assertDatabaseHas('workflow_interrogation_batches', [
            'id' => $batch->id,
            'is_active' => false,
        ]);

        Queue::assertPushed(GenerateWorkflowInterrogationRoundJob::class, function (GenerateWorkflowInterrogationRoundJob $job) use ($session): bool {
            return $job->sessionId === $session->id
                && data_get($job->latestAnswers, '0.question_id') === 'q-actors'
                && data_get($job->latestAnswers, '0.selected_option') === 'Operations';
        });
    }

    public function test_generate_plan_dispatches_plan_job(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice exceptions',
            'workflow_brief' => 'Interrogate the invoice exception workflow.',
            'status' => WorkflowInterrogationSession::STATUS_SUMMARY_READY,
            'phase' => WorkflowInterrogationSession::PHASE_SUMMARY,
            'summary_json' => [
                'summary_markdown' => "# Findings\n\nOperations owns the workflow.",
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/agent/api/v1/workflow-interrogator/sessions/{$session->id}/generate-plan")
            ->assertStatus(202)
            ->assertJsonPath('data.status', WorkflowInterrogationSession::STATUS_PLANNING)
            ->assertJsonPath('data.processing.kind', 'plan')
            ->assertJsonPath('data.processing.state', 'queued');

        Queue::assertPushed(GenerateWorkflowInterrogationPlanJob::class, function (GenerateWorkflowInterrogationPlanJob $job) use ($session): bool {
            return $job->sessionId === $session->id;
        });
    }
}
