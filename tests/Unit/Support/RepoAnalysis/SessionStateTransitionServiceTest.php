<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionStateTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_valid_phase_progression_to_terminal_completion(): void
    {
        $session = $this->createSession();
        $service = new SessionStateTransitionService;

        $this->assertTrue($service->transitionTo($session->id, 1, 'snapshotting'));
        $this->assertTrue($service->transitionTo($session->id, 2, 'planning'));
        $this->assertTrue($service->transitionTo($session->id, 3, 'executing'));
        $this->assertTrue($service->transitionTo($session->id, 4, 'validating'));
        $this->assertTrue($service->transitionTo($session->id, 5, 'reporting'));
        $this->assertTrue($service->transitionTo($session->id, 6, 'completed'));

        $fresh = $session->fresh();
        $this->assertSame(6, (int) $fresh->phase);
        $this->assertSame('completed', (string) $fresh->status);
    }

    public function test_rejects_duplicate_start_transition(): void
    {
        $session = $this->createSession();
        $service = new SessionStateTransitionService;

        $this->assertTrue($service->transitionTo($session->id, 1, 'snapshotting'));
        $this->assertFalse($service->transitionTo($session->id, 1, 'snapshotting'));

        $fresh = $session->fresh();
        $this->assertSame(1, (int) $fresh->phase);
        $this->assertSame('snapshotting', (string) $fresh->status);
    }

    public function test_rejects_invalid_phase_jump(): void
    {
        $session = $this->createSession();
        $service = new SessionStateTransitionService;

        $this->assertFalse($service->transitionTo($session->id, 3, 'executing'));

        $fresh = $session->fresh();
        $this->assertSame(0, (int) $fresh->phase);
        $this->assertSame('setup', (string) $fresh->status);
    }

    public function test_terminal_state_blocks_further_transitions(): void
    {
        $session = $this->createSession(6, 'completed');
        $service = new SessionStateTransitionService;

        $this->assertFalse($service->transitionTo($session->id, 3, 'executing'));

        $fresh = $session->fresh();
        $this->assertSame(6, (int) $fresh->phase);
        $this->assertSame('completed', (string) $fresh->status);
    }

    public function test_resume_requires_paused_status(): void
    {
        $session = $this->createSession(3, 'executing');
        $service = new SessionStateTransitionService;

        $this->assertFalse($service->resume($session->id));

        $fresh = $session->fresh();
        $this->assertSame(3, (int) $fresh->phase);
        $this->assertSame('executing', (string) $fresh->status);
    }

    public function test_retry_requires_failed_status(): void
    {
        $session = $this->createSession(3, 'executing');
        $service = new SessionStateTransitionService;

        $this->assertFalse($service->retry($session->id));

        $fresh = $session->fresh();
        $this->assertSame(3, (int) $fresh->phase);
        $this->assertSame('executing', (string) $fresh->status);
    }

    public function test_resume_from_paused_restores_phase_active_status(): void
    {
        $session = $this->createSession(4, 'paused');
        $service = new SessionStateTransitionService;

        $this->assertTrue($service->resume($session->id));

        $fresh = $session->fresh();
        $this->assertSame(4, (int) $fresh->phase);
        $this->assertSame('validating', (string) $fresh->status);
    }

    public function test_retry_from_failed_restores_phase_active_status(): void
    {
        $session = $this->createSession(2, 'failed');
        $service = new SessionStateTransitionService;

        $this->assertTrue($service->retry($session->id));

        $fresh = $session->fresh();
        $this->assertSame(2, (int) $fresh->phase);
        $this->assertSame('planning', (string) $fresh->status);
    }

    public function test_atomic_transition_allows_only_one_competing_mutation(): void
    {
        $session = $this->createSession(2, 'planning');
        $service = new SessionStateTransitionService;

        $firstAttempt = $service->transitionTo($session->id, 3, 'executing');
        $secondAttempt = $service->transitionTo($session->id, 3, 'executing');

        $this->assertTrue($firstAttempt);
        $this->assertFalse($secondAttempt);

        $fresh = $session->fresh();
        $this->assertSame(3, (int) $fresh->phase);
        $this->assertSame('executing', (string) $fresh->status);
    }

    private function createSession(int $phase = 0, string $status = 'setup'): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Transition Test Session',
            'project_directory' => base_path(),
            'analyzer_profile' => 'default',
            'phase' => $phase,
            'status' => $status,
        ]);
    }
}
