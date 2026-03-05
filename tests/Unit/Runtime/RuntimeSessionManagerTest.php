<?php

namespace Tests\Unit\Runtime;

use App\Enums\Runtime\PolicySnapshotReason;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Exceptions\Runtime\ConcurrentSessionLimitExceededException;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\RuntimeSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeSessionManagerTest extends TestCase
{
    use RefreshDatabase;

    private RuntimeSessionManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(RuntimeSessionManager::class);
    }

    public function test_create_session_returns_runtime_session(): void
    {
        $user = User::factory()->create();

        $session = $this->manager->createSession($user, [
            'title' => 'Test Session',
            'workspace_root' => '/tmp',
        ]);

        $this->assertInstanceOf(RuntimeSession::class, $session);
        $this->assertEquals($user->id, $session->user_id);
        $this->assertEquals(RuntimeMode::Safe, $session->mode);
        $this->assertEquals(RuntimeSessionStatus::Active, $session->status);
    }

    public function test_create_session_captures_policy_snapshot(): void
    {
        $user = User::factory()->create();

        $session = $this->manager->createSession($user, ['workspace_root' => '/tmp']);

        $this->assertCount(1, $session->policySnapshots);
        $this->assertEquals(PolicySnapshotReason::SessionStart, $session->policySnapshots->first()->snapshot_reason);
    }

    public function test_create_session_enforces_concurrent_limit(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions (default limit)
        for ($i = 0; $i < 3; $i++) {
            $this->manager->createSession($user, ['workspace_root' => '/tmp']);
        }

        $this->expectException(ConcurrentSessionLimitExceededException::class);
        $this->manager->createSession($user, ['workspace_root' => '/tmp']);
    }

    public function test_stop_session_updates_status_and_ended_at(): void
    {
        $user = User::factory()->create();
        $session = $this->manager->createSession($user, ['workspace_root' => '/tmp']);

        $this->manager->stopSession($session);
        $session->refresh();

        $this->assertEquals(RuntimeSessionStatus::Stopped, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_change_mode_updates_session_and_captures_snapshot(): void
    {
        $user = User::factory()->create();
        $session = $this->manager->createSession($user, ['workspace_root' => '/tmp']);

        $this->manager->changeMode($session, RuntimeMode::Standard);
        $session->refresh();

        $this->assertEquals(RuntimeMode::Standard, $session->mode);
        $this->assertCount(2, $session->policySnapshots); // session_start + mode_change
    }

    public function test_get_active_sessions_returns_only_active(): void
    {
        $user = User::factory()->create();
        $active = $this->manager->createSession($user, ['workspace_root' => '/tmp']);
        $stopped = $this->manager->createSession($user, ['workspace_root' => '/tmp']);
        $this->manager->stopSession($stopped);

        $sessions = $this->manager->getActiveSessions($user);

        $this->assertCount(1, $sessions);
        $this->assertEquals($active->id, $sessions->first()->id);
    }

    public function test_create_session_uses_connector_concurrent_limit(): void
    {
        $user = User::factory()->create();

        // Create 3 sessions (default limit)
        for ($i = 0; $i < 3; $i++) {
            $this->manager->createSession($user, ['workspace_root' => '/tmp']);
        }

        // The 4th should fail with default limit
        $this->expectException(ConcurrentSessionLimitExceededException::class);
        $this->manager->createSession($user, ['workspace_root' => '/tmp']);
    }

    public function test_create_session_with_custom_mode(): void
    {
        $user = User::factory()->create();

        $session = $this->manager->createSession($user, [
            'workspace_root' => '/tmp',
            'mode' => 'standard',
        ]);

        $this->assertEquals(RuntimeMode::Standard, $session->mode);
    }

    public function test_stopped_sessions_do_not_count_toward_concurrent_limit(): void
    {
        $user = User::factory()->create();

        // Create and stop 3 sessions
        for ($i = 0; $i < 3; $i++) {
            $session = $this->manager->createSession($user, ['workspace_root' => '/tmp']);
            $this->manager->stopSession($session);
        }

        // Should be able to create a new session since all previous are stopped
        $newSession = $this->manager->createSession($user, ['workspace_root' => '/tmp']);
        $this->assertInstanceOf(RuntimeSession::class, $newSession);
    }

    public function test_change_mode_captures_snapshot_with_correct_reason(): void
    {
        $user = User::factory()->create();
        $session = $this->manager->createSession($user, ['workspace_root' => '/tmp']);

        $this->manager->changeMode($session, RuntimeMode::Full);
        $session->refresh();

        $modeChangeSnapshot = $session->policySnapshots->where(
            'snapshot_reason',
            PolicySnapshotReason::ModeChange
        )->first();

        $this->assertNotNull($modeChangeSnapshot);
        $this->assertEquals('full', $modeChangeSnapshot->policy_json['mode']);
    }

    public function test_create_session_with_browser_persistence(): void
    {
        $user = User::factory()->create();

        $session = $this->manager->createSession($user, [
            'workspace_root' => '/tmp',
            'browser_persistence' => 'persistent',
        ]);

        $this->assertEquals('persistent', $session->browser_persistence_mode->value);
    }
}
