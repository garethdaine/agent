<?php

declare(strict_types=1);

namespace Tests\Feature\WorkflowInterrogator;

use App\Events\WorkflowInterrogationSessionUpdated;
use App\Http\Middleware\EnsureLicenseValid;
use App\Models\User;
use App\Models\WorkflowInterrogationBatch;
use App\Models\WorkflowInterrogationSession;
use App\Support\WorkflowInterrogator\Contracts\WorkflowInterrogatorClient;
use App\Support\WorkflowInterrogator\WorkflowInterrogationExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class WorkflowInterrogationExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureLicenseValid::class);
        config()->set('agent.allowed_working_directory_bases', [base_path()]);
        Event::fake([WorkflowInterrogationSessionUpdated::class]);
    }

    public function test_execute_round_persists_question_batch_and_clears_processing_state(): void
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
            'metadata_json' => [
                'processing' => ['kind' => 'round', 'state' => 'queued'],
            ],
        ]);

        $mock = $this->mock(WorkflowInterrogatorClient::class);
        $mock->shouldReceive('generateRound')
            ->once()
            ->andReturn([
                'questions' => [
                    [
                        'question_id' => 'q-actors',
                        'prompt' => 'Who owns the workflow today?',
                        'answer_type' => 'choice',
                        'options' => ['Operations', 'Finance'],
                        'required' => true,
                        'rationale' => 'Ownership affects approvals and exception handling.',
                        'category' => 'ownership',
                    ],
                ],
                'ambiguity_report' => [
                    'needs_another_round' => true,
                    'resolved_areas' => [],
                    'open_ambiguities' => ['Workflow ownership is still unclear.'],
                    'contradictions' => [],
                    'coverage_gaps' => ['ownership'],
                    'closure_reason' => 'Need one more answer before summary.',
                ],
                'summary' => [],
                'cli_session_id' => 'runner-session-1',
            ]);

        app(WorkflowInterrogationExecutionService::class)->executeRound((int) $session->id);

        $session->refresh();
        $activeBatch = $session->activeBatch()->with('questions')->first();

        $this->assertSame(WorkflowInterrogationSession::STATUS_INTERROGATING, $session->status);
        $this->assertSame(1, $session->current_round);
        $this->assertSame('idle', data_get($session->metadata_json, 'processing.state'));
        $this->assertInstanceOf(WorkflowInterrogationBatch::class, $activeBatch);
        $this->assertSame(1, $activeBatch->round);
        $this->assertSame('q-actors', $activeBatch->questions->first()?->question_key);
        Event::assertDispatched(WorkflowInterrogationSessionUpdated::class);
    }

    public function test_execute_round_can_close_into_summary_ready(): void
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
            'metadata_json' => [
                'processing' => ['kind' => 'round', 'state' => 'queued'],
            ],
        ]);

        $mock = $this->mock(WorkflowInterrogatorClient::class);
        $mock->shouldReceive('generateRound')
            ->once()
            ->andReturn([
                'questions' => [],
                'ambiguity_report' => [
                    'needs_another_round' => false,
                    'resolved_areas' => ['ownership'],
                    'open_ambiguities' => [],
                    'contradictions' => [],
                    'coverage_gaps' => [],
                    'closure_reason' => 'Material ambiguity exhausted.',
                ],
                'summary' => [
                    'summary_markdown' => "# Findings\n\nOperations owns the workflow.",
                ],
                'cli_session_id' => 'runner-session-1',
            ]);

        app(WorkflowInterrogationExecutionService::class)->executeRound((int) $session->id);

        $session->refresh();

        $this->assertSame(WorkflowInterrogationSession::STATUS_SUMMARY_READY, $session->status);
        $this->assertSame('idle', data_get($session->metadata_json, 'processing.state'));
        $this->assertSame("# Findings\n\nOperations owns the workflow.", data_get($session->summary_json, 'summary_markdown'));
        Event::assertDispatched(WorkflowInterrogationSessionUpdated::class);
    }

    public function test_execute_plan_completes_session(): void
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
            'status' => WorkflowInterrogationSession::STATUS_PLANNING,
            'phase' => WorkflowInterrogationSession::PHASE_ACTION_PLAN,
            'summary_json' => [
                'summary_markdown' => "# Findings\n\nOperations owns the workflow.",
            ],
            'metadata_json' => [
                'processing' => ['kind' => 'plan', 'state' => 'queued'],
            ],
        ]);

        $mock = $this->mock(WorkflowInterrogatorClient::class);
        $mock->shouldReceive('generateActionPlan')
            ->once()
            ->andReturn([
                'action_plan_markdown' => "# Action Plan\n\nStart with an ownership and exception-routing pilot.",
                'recommended_approach' => 'Pilot first',
            ]);

        app(WorkflowInterrogationExecutionService::class)->executePlan((int) $session->id);

        $session->refresh();

        $this->assertSame(WorkflowInterrogationSession::STATUS_COMPLETED, $session->status);
        $this->assertSame('idle', data_get($session->metadata_json, 'processing.state'));
        $this->assertSame('Pilot first', data_get($session->action_plan_json, 'recommended_approach'));
        Event::assertDispatched(WorkflowInterrogationSessionUpdated::class);
    }

    public function test_execute_round_persists_failed_state_when_runner_output_is_rejected(): void
    {
        $user = User::factory()->create();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'codex',
            'model' => 'gpt-5.4',
            'project_directory' => base_path(),
            'interrogation_mode' => 'workflow',
            'company_name' => 'Acme Ops',
            'workflow_title' => 'Invoice exceptions',
            'workflow_brief' => 'Interrogate the invoice exception workflow.',
            'status' => WorkflowInterrogationSession::STATUS_INTERROGATING,
            'phase' => WorkflowInterrogationSession::PHASE_INTERROGATION,
            'metadata_json' => [
                'processing' => ['kind' => 'round', 'state' => 'queued'],
            ],
        ]);

        $this->mock(WorkflowInterrogatorClient::class)
            ->shouldReceive('generateRound')
            ->once()
            ->andThrow(new RuntimeException('Workflow Interrogator returned invalid round output. Output excerpt: {"type":"assistant"}'));

        app(WorkflowInterrogationExecutionService::class)->executeRound((int) $session->id);

        $session->refresh();

        $this->assertSame(WorkflowInterrogationSession::STATUS_FAILED, $session->status);
        $this->assertSame('ROUND_GENERATION_FAILED', $session->error_code);
        $this->assertStringContainsString('invalid round output', (string) $session->error_summary);
        $this->assertSame('failed', data_get($session->metadata_json, 'processing.state'));
        $this->assertSame(
            'ROUND_GENERATION_FAILED',
            data_get($session->events()->latest('sequence')->first()?->payload, 'code')
        );
        Event::assertDispatched(WorkflowInterrogationSessionUpdated::class);
    }
}
