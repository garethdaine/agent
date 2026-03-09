<?php

declare(strict_types=1);

namespace Tests\Feature\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Services\RepoAnalysis\RepoAnalysisWorkflowService;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepoAnalysisWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private RepoAnalysisWorkflowService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new RepoAnalysisWorkflowService;
        $this->user = User::factory()->create();
    }

    public function test_start_snapshot_succeeds_in_setup_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 0,
            'status' => SessionStateTransitionService::STATUS_SETUP,
        ]);

        $result = $this->service->startSnapshot($session);
        $this->assertNull($result);
    }

    public function test_start_snapshot_rejects_wrong_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 2,
            'status' => 'planning',
        ]);

        $result = $this->service->startSnapshot($session);
        $this->assertNotNull($result);
        $this->assertSame(409, $result->getStatusCode());
    }

    public function test_plan_succeeds_in_phase_2(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 2]);

        $result = $this->service->plan($session);
        $this->assertNull($result);
    }

    public function test_plan_rejects_wrong_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 1]);

        $result = $this->service->plan($session);
        $this->assertNotNull($result);
        $this->assertSame(409, $result->getStatusCode());
    }

    public function test_execute_succeeds_in_phase_3(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 3]);

        $result = $this->service->execute($session);
        $this->assertNull($result);
    }

    public function test_execute_rejects_wrong_phase(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create(['phase' => 2]);

        $result = $this->service->execute($session);
        $this->assertNotNull($result);
    }

    public function test_restart_from_beginning_resets_session(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_COMPLETED,
        ]);

        $result = $this->service->restartFromBeginning($session);
        $this->assertNull($result);

        $session->refresh();
        $this->assertSame(0, (int) $session->phase);
        $this->assertSame(SessionStateTransitionService::STATUS_SETUP, (string) $session->status);
    }

    public function test_restart_from_beginning_rejects_running_session(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
        ]);

        $result = $this->service->restartFromBeginning($session);
        $this->assertNotNull($result);
        $this->assertSame(409, $result->getStatusCode());
    }

    public function test_restart_increments_restart_count(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'phase' => 3,
            'status' => SessionStateTransitionService::STATUS_COMPLETED,
            'metadata_json' => ['restart_count' => 1],
        ]);

        $this->service->restartFromBeginning($session);
        $session->refresh();

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $this->assertSame(2, $metadata['restart_count']);
    }

    public function test_dispatch_by_phase_returns_true_for_valid_phases(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create();

        foreach ([1, 2, 3, 4, 5] as $phase) {
            $this->assertTrue($this->service->dispatchByPhase($phase, (int) $session->id));
        }
    }

    public function test_dispatch_by_phase_returns_false_for_invalid(): void
    {
        $this->assertFalse($this->service->dispatchByPhase(0, 1));
        $this->assertFalse($this->service->dispatchByPhase(6, 1));
    }

    public function test_active_session_limit_envelope_allows_within_limit(): void
    {
        $session = RepoAnalysisSession::factory()->for($this->user)->create([
            'status' => SessionStateTransitionService::STATUS_SETUP,
        ]);

        $result = $this->service->activeSessionLimitEnvelope((int) $this->user->id, (int) $session->id);
        $this->assertNull($result);
    }

    public function test_active_session_limit_envelope_blocks_over_limit(): void
    {
        config()->set('repo_analysis.user.max_active_sessions_per_user', 1);

        $session1 = RepoAnalysisSession::factory()->for($this->user)->create([
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
        ]);
        $session2 = RepoAnalysisSession::factory()->for($this->user)->create([
            'status' => SessionStateTransitionService::STATUS_SETUP,
        ]);

        $result = $this->service->activeSessionLimitEnvelope((int) $this->user->id, (int) $session2->id);
        $this->assertNotNull($result);
        $this->assertSame(409, $result->getStatusCode());
    }
}
