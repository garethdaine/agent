<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Jobs\ExecuteInterrogationPlanJob;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Services\Interrogation\InterrogationPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InterrogationPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private InterrogationPlanService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new InterrogationPlanService;
        $this->user = User::factory()->create();
    }

    public function test_generate_plan_dispatches_job_and_marks_queued(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $this->service->generatePlan($session);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class, function ($job) {
            return true;
        });

        $session->refresh();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $this->assertSame('queued', $metadata['plan']['generation_status'] ?? null);
    }

    public function test_regenerate_plan_resets_session_state_and_dispatches_job(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create([
                'error_code' => 'SOME_ERROR',
                'error_summary' => 'Some error occurred',
            ]);

        $this->service->regeneratePlan($session, $this->user->id);

        $session->refresh();
        $this->assertSame(InterrogationSession::STATUS_PLANNING, $session->status);
        $this->assertNull($session->error_code);
        $this->assertNull($session->error_summary);
        $this->assertNull($session->finished_at);

        $metadata = $session->metadata_json;
        $this->assertSame('queued', $metadata['plan']['revision_status']);
        $this->assertSame($this->user->id, $metadata['plan']['regenerated_by_user_id']);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class);
    }

    public function test_request_revision_builds_prompt_and_dispatches_job(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $this->service->requestRevision($session, [
            'action' => 'refine',
            'sections' => ['Architecture', 'Testing'],
            'notes' => 'Add more detail about caching strategy',
        ]);

        $session->refresh();
        $this->assertSame(InterrogationSession::STATUS_PLANNING, $session->status);

        $metadata = $session->metadata_json;
        $this->assertSame('queued', $metadata['plan']['revision_status']);

        Queue::assertPushed(ExecuteInterrogationPlanJob::class);
    }

    public function test_mark_plan_generation_state_queued(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $this->service->markPlanGenerationState($session, 'queued');

        $session->refresh();
        $metadata = $session->metadata_json;
        $this->assertSame('queued', $metadata['plan']['generation_status']);
        $this->assertNotNull($metadata['plan']['generation_queued_at']);
        $this->assertNull($metadata['plan']['generation_error']);
    }

    public function test_mark_plan_generation_state_failed(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $this->service->markPlanGenerationState($session, 'failed', 'LLM timeout');

        $session->refresh();
        $metadata = $session->metadata_json;
        $this->assertSame('failed', $metadata['plan']['generation_status']);
        $this->assertSame('LLM timeout', $metadata['plan']['generation_error']);
    }

    public function test_has_meaningful_plan_returns_false_for_empty(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['plan_json' => []]);

        $this->assertFalse($this->service->hasMeaningfulPlan($session));
    }

    public function test_has_meaningful_plan_returns_false_for_null(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['plan_json' => null]);

        $this->assertFalse($this->service->hasMeaningfulPlan($session));
    }

    public function test_invalidate_derived_artifacts_clears_plan_summary_build(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create([
                'summary_json' => ['overview' => 'Test summary'],
                'plan_json' => ['phases' => [['title' => 'Phase 1']]],
                'approved_at' => now(),
                'metadata_json' => [
                    'summary_ready' => true,
                    'build' => ['status' => 'running', 'task_count' => 5],
                ],
            ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Old task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $this->service->invalidateDerivedArtifactsForReinterrogation($session);

        $session->refresh();
        $this->assertSame([], $session->summary_json);
        $this->assertSame([], $session->plan_json);
        $this->assertNull($session->approved_at);

        $metadata = $session->metadata_json;
        $this->assertFalse($metadata['summary_ready']);
        $this->assertNull($metadata['summary_generated_at']);
        $this->assertSame('idle', $metadata['build']['status']);
        $this->assertSame(0, $metadata['build']['task_count']);

        $this->assertSame(0, InterrogationBuildTask::where('interrogation_session_id', $session->id)->count());
    }

    public function test_normalized_summary_json_returns_empty_for_empty_summary(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['summary_json' => []]);

        $result = $this->service->normalizedSummaryJson($session, false);

        $this->assertSame([], $result);
    }

    public function test_normalized_plan_json_returns_empty_for_empty_plan(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['plan_json' => []]);

        $result = $this->service->normalizedPlanJson($session, false);

        $this->assertSame([], $result);
    }
}
