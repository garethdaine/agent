<?php

namespace Tests\Unit\Runtime;

use App\Services\Runtime\SessionProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionProcessManagerTest extends TestCase
{
    use RefreshDatabase;

    private SessionProcessManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SessionProcessManager::class);
    }

    public function test_get_returns_null_when_not_set(): void
    {
        $id = $this->manager->getRunnerSessionId('runtime-session-123');

        $this->assertNull($id);
    }

    public function test_set_and_get_roundtrip(): void
    {
        $this->manager->setRunnerSessionId('runtime-session-123', 'claude-session-abc');

        $this->assertSame('claude-session-abc', $this->manager->getRunnerSessionId('runtime-session-123'));
    }

    public function test_clear_removes_runner_session_id(): void
    {
        $this->manager->setRunnerSessionId('runtime-session-123', 'claude-session-abc');
        $this->manager->clearSession('runtime-session-123');

        $this->assertNull($this->manager->getRunnerSessionId('runtime-session-123'));
    }

    public function test_clear_does_not_affect_other_sessions(): void
    {
        $this->manager->setRunnerSessionId('runtime-session-A', 'id-a');
        $this->manager->setRunnerSessionId('runtime-session-B', 'id-b');
        $this->manager->clearSession('runtime-session-A');

        $this->assertNull($this->manager->getRunnerSessionId('runtime-session-A'));
        $this->assertSame('id-b', $this->manager->getRunnerSessionId('runtime-session-B'));
    }
}
