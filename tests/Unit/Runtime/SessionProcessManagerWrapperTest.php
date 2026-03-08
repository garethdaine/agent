<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Services\Runtime\SessionProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SessionProcessManagerWrapperTest extends TestCase
{
    use RefreshDatabase;

    private SessionProcessManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SessionProcessManager::class);
    }

    public function test_is_wrapper_enabled_returns_false_by_default(): void
    {
        config(['runtime.cli.wrapper_enabled' => false]);

        $this->assertFalse($this->manager->isWrapperEnabled());
    }

    public function test_is_wrapper_enabled_returns_true_when_configured(): void
    {
        config(['runtime.cli.wrapper_enabled' => true]);

        $this->assertTrue($this->manager->isWrapperEnabled());
    }

    public function test_has_active_wrapper_returns_false_when_none_started(): void
    {
        $this->assertFalse($this->manager->hasActiveWrapper('session-no-wrapper'));
    }

    public function test_has_active_wrapper_returns_false_when_pid_not_running(): void
    {
        Cache::put('runtime:wrapper_pid:session-stale', 999999999, 86400);

        $this->assertFalse($this->manager->hasActiveWrapper('session-stale'));
    }

    public function test_send_message_fails_when_no_active_wrapper(): void
    {
        $result = $this->manager->sendMessage('nonexistent-session', 'hello');

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('No active process for session', $result['error']);
    }

    public function test_terminate_session_clears_cache_and_runner_id(): void
    {
        $sessionId = 'session-to-terminate';
        $this->manager->setRunnerSessionId($sessionId, 'claude-session-abc');
        Cache::put('runtime:wrapper_pid:'.$sessionId, 999999999, 86400);

        $this->manager->terminateSession($sessionId);

        $this->assertNull($this->manager->getRunnerSessionId($sessionId));
        $this->assertNull(Cache::get('runtime:wrapper_pid:'.$sessionId));
    }

    public function test_start_wrapper_returns_false_when_script_missing(): void
    {
        $original = base_path('bin/session-wrapper');
        $backup = base_path('bin/session-wrapper.bak');
        $needsRestore = false;

        if (file_exists($original)) {
            rename($original, $backup);
            $needsRestore = true;
        }

        try {
            $result = $this->manager->startWrapper(
                runtimeSessionId: 'test-session',
                runnerExecutable: '/nonexistent/claude',
                runnerType: 'claude',
                workingDirectory: '/tmp',
                apiKey: 'test-key',
            );

            if ($result) {
                $this->manager->terminateSession('test-session');
                $this->fail('Expected startWrapper to return false when script is missing');
            }

            $this->assertFalse($result);
        } finally {
            if ($needsRestore) {
                rename($backup, $original);
            }
        }
    }

    public function test_start_wrapper_launches_process_and_records_pid(): void
    {
        $wrapperScript = base_path('bin/session-wrapper');
        if (! file_exists($wrapperScript)) {
            $this->markTestSkipped('Wrapper script not found at bin/session-wrapper');
        }

        $sessionId = 'test-wrapper-start-'.uniqid();

        $result = $this->manager->startWrapper(
            runtimeSessionId: $sessionId,
            runnerExecutable: '/usr/bin/echo',
            runnerType: 'claude',
            workingDirectory: '/tmp',
            apiKey: 'test-key',
        );

        $this->assertTrue($result);
        $this->assertTrue($this->manager->hasActiveWrapper($sessionId));

        $pid = Cache::get('runtime:wrapper_pid:'.$sessionId);
        $this->assertNotNull($pid);
        $this->assertIsInt($pid);

        $this->manager->terminateSession($sessionId);
    }
}
