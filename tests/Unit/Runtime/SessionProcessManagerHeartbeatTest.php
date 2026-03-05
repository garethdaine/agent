<?php

namespace Tests\Unit\Runtime;

use App\Services\Runtime\SessionProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionProcessManagerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private SessionProcessManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SessionProcessManager::class);
    }

    public function test_send_message_accepts_optional_progress_callback(): void
    {
        $result = $this->manager->sendMessage('nonexistent-session', 'hello', 300, null);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('No active process for session', $result['error']);
    }

    public function test_send_message_accepts_heartbeat_interval_parameter(): void
    {
        $result = $this->manager->sendMessage('nonexistent-session', 'hello', 300, null, 15);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('No active process for session', $result['error']);
    }

    public function test_read_turn_response_accepts_optional_progress_callback(): void
    {
        $result = $this->manager->readTurnResponse('nonexistent-session', 10, null, 5);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('No active process for session', $result['error']);
    }

    public function test_default_timeout_is_1800_seconds(): void
    {
        $this->assertEquals(1800, config('runtime.cli.timeout_seconds'));
    }
}
