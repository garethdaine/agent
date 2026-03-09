<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Services\Interrogation\InterrogationApprovalService;
use App\Services\Interrogation\InterrogationBuildService;
use App\Services\Interrogation\InterrogationPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InterrogationApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private InterrogationApprovalService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new InterrogationApprovalService(
            new InterrogationPlanService,
            new InterrogationBuildService,
        );
        $this->user = User::factory()->create();
    }

    public function test_is_plan_already_approved_returns_true_when_past_planning(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_BUILD_RULES,
                'status' => InterrogationSession::STATUS_BUILD_RULES,
                'approved_at' => now(),
            ]);

        $this->assertTrue($this->service->isPlanAlreadyApproved($session));
    }

    public function test_is_plan_already_approved_returns_false_during_planning(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create(['approved_at' => null]);

        $this->assertFalse($this->service->isPlanAlreadyApproved($session));
    }

    public function test_validate_plan_approval_eligibility_returns_null_for_valid_state(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create([
                'plan_json' => [
                    'plan_markdown' => "# Implementation Plan\n\n## Phase 1: Setup\n- Install dependencies\n- Configure environment\n- Set up database\n\n## Phase 2: Core Features\n- Implement authentication\n- Build API endpoints\n- Add validation layer\n\n## Phase 3: Testing\n- Write unit tests\n- Write integration tests\n- Performance benchmarks",
                    'sections' => ['Setup', 'Core Features', 'Testing'],
                    'risks' => ['Tight timeline'],
                    'assumptions' => ['Team has Laravel experience'],
                ],
            ]);

        $result = $this->service->validatePlanApprovalEligibility($session);

        $this->assertNull($result);
    }

    public function test_validate_plan_approval_eligibility_rejects_wrong_phase(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_INTERROGATION,
                'status' => InterrogationSession::STATUS_INTERROGATING,
            ]);

        $result = $this->service->validatePlanApprovalEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('planning phase', $result);
    }

    public function test_validate_plan_approval_eligibility_rejects_failed_session(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_PLANNING,
                'status' => InterrogationSession::STATUS_FAILED,
            ]);

        $result = $this->service->validatePlanApprovalEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('retried', $result);
    }

    public function test_validate_plan_approval_eligibility_rejects_empty_plan(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create(['plan_json' => []]);

        $result = $this->service->validatePlanApprovalEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('not available', $result);
    }

    public function test_validate_build_task_approval_eligibility_returns_null_for_valid(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'metadata_json' => ['build' => ['status' => 'ready']],
            ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'Task 1',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        $result = $this->service->validateBuildTaskApprovalEligibility($session);

        $this->assertNull($result);
    }

    public function test_validate_build_task_approval_eligibility_rejects_wrong_phase(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $result = $this->service->validateBuildTaskApprovalEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('build tasks phase', $result);
    }

    public function test_validate_build_task_approval_eligibility_rejects_no_tasks(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
            ]);

        $result = $this->service->validateBuildTaskApprovalEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('No build tasks', $result);
    }

    public function test_validate_build_start_eligibility_rejects_unapproved_plan(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'phase' => InterrogationSession::PHASE_BUILD_TASKS,
                'status' => InterrogationSession::STATUS_BUILD_TASKS,
                'approved_at' => null,
            ]);

        $result = $this->service->validateBuildStartEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Plan must be approved', $result);
    }

    public function test_validate_build_start_eligibility_rejects_wrong_phase(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create();

        $result = $this->service->validateBuildStartEligibility($session);

        $this->assertNotNull($result);
        $this->assertStringContainsString('build phases', $result);
    }
}
