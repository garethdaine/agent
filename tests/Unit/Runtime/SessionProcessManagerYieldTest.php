<?php

namespace Tests\Unit\Runtime;

use App\Services\Runtime\SessionProcessManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class SessionProcessManagerYieldTest extends TestCase
{
    private SessionProcessManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new SessionProcessManager;
    }

    protected function tearDown(): void
    {
        $this->cleanupAllFakeProcesses();
        parent::tearDown();
    }

    private function injectFakeProcess(string $sessionId): void
    {
        $process = proc_open(
            'sleep 10',
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'r'],
                2 => ['pipe', 'r'],
            ],
            $pipes,
        );

        $reflection = new ReflectionClass($this->manager);
        $prop = $reflection->getProperty('activeProcesses');
        $prop->setAccessible(true);

        $existing = $prop->getValue();
        $existing[$sessionId] = [
            'resource' => $process,
            'pipes' => $pipes,
        ];
        $prop->setValue(null, $existing);
    }

    private function mockLogWithChannel(): void
    {
        $channelLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelLogger->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();
        $channelLogger->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $channelLogger->shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes();
        $channelLogger->shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('channel')->with('runtime')->andReturn($channelLogger)->zeroOrMoreTimes();
        Log::shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes();
    }

    private function cleanupAllFakeProcesses(): void
    {
        $reflection = new ReflectionClass($this->manager);
        $prop = $reflection->getProperty('activeProcesses');
        $prop->setAccessible(true);

        $processes = $prop->getValue();
        foreach ($processes as $sessionId => $entry) {
            foreach ($entry['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }
            if (is_resource($entry['resource'])) {
                @proc_terminate($entry['resource']);
                @proc_close($entry['resource']);
            }
        }
        $prop->setValue(null, []);
    }

    public function test_yield_config_defaults(): void
    {
        $this->assertFalse(config('runtime.cli.yield_enabled'));
        $this->assertEquals(120, config('runtime.cli.yield_after_seconds'));
    }

    public function test_read_turn_response_returns_failed_when_yield_disabled_and_timeout(): void
    {
        config(['runtime.cli.yield_enabled' => false]);

        $sessionId = 'yield-disabled-session';
        $this->injectFakeProcess($sessionId);

        $this->mockLogWithChannel();

        $result = $this->manager->readTurnResponse($sessionId, 1);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('timed out', $result['error']);
        $this->assertArrayNotHasKey('yielded', [$result['status']]);
    }

    public function test_read_turn_response_returns_yielded_when_yield_enabled(): void
    {
        config([
            'runtime.cli.yield_enabled' => true,
            'runtime.cli.yield_after_seconds' => 1,
        ]);

        $sessionId = 'yield-enabled-session';
        $this->injectFakeProcess($sessionId);

        $this->mockLogWithChannel();

        $result = $this->manager->readTurnResponse($sessionId, 10, null, 1);

        $this->assertEquals('yielded', $result['status']);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertEquals($sessionId, $result['session_id']);
    }

    public function test_yield_buffers_fragments_to_cache(): void
    {
        config([
            'runtime.cli.yield_enabled' => true,
            'runtime.cli.yield_after_seconds' => 1,
        ]);

        $sessionId = 'yield-buffer-session';
        $this->injectFakeProcess($sessionId);

        $this->mockLogWithChannel();

        $this->manager->readTurnResponse($sessionId, 10, null, 1);

        $buffered = Cache::get("runtime:turn_buffer:{$sessionId}");

        $this->assertIsArray($buffered);
        $this->assertArrayHasKey('fragments', $buffered);
        $this->assertArrayHasKey('runner_session_id', $buffered);
        $this->assertArrayHasKey('start_time', $buffered);
    }

    public function test_yield_invokes_progress_callback_before_yielding(): void
    {
        config([
            'runtime.cli.yield_enabled' => true,
            'runtime.cli.yield_after_seconds' => 1,
        ]);

        $sessionId = 'yield-callback-session';
        $this->injectFakeProcess($sessionId);

        $this->mockLogWithChannel();

        $callbackInvoked = false;
        $progress = function (array $state) use (&$callbackInvoked) {
            $callbackInvoked = true;
        };

        $this->manager->readTurnResponse($sessionId, 10, $progress, 1);

        $this->assertTrue($callbackInvoked);
    }

    public function test_resume_read_turn_response_returns_failed_when_no_active_process(): void
    {
        $result = $this->manager->resumeReadTurnResponse('nonexistent', 60);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('No active process', $result['error']);
    }

    public function test_resume_read_turn_response_loads_buffered_fragments(): void
    {
        $sessionId = 'resume-session';
        $this->injectFakeProcess($sessionId);

        Cache::put("runtime:turn_buffer:{$sessionId}", [
            'fragments' => ['{"type":"content_block_start"}'],
            'runner_session_id' => 'runner-abc',
            'start_time' => time() - 30,
        ], 3600);

        config([
            'runtime.cli.yield_enabled' => true,
            'runtime.cli.yield_after_seconds' => 1,
        ]);

        $this->mockLogWithChannel();

        $result = $this->manager->resumeReadTurnResponse($sessionId, 2);

        $this->assertContains($result['status'], ['yielded', 'failed', 'completed']);
    }

    public function test_resume_clears_buffer_on_completion_or_timeout(): void
    {
        $sessionId = 'resume-clear-session';
        $this->injectFakeProcess($sessionId);

        Cache::put("runtime:turn_buffer:{$sessionId}", [
            'fragments' => [],
            'runner_session_id' => null,
            'start_time' => time() - 60,
        ], 3600);

        config(['runtime.cli.yield_enabled' => false]);

        $this->mockLogWithChannel();

        $result = $this->manager->resumeReadTurnResponse($sessionId, 1);

        $this->assertNotEquals('yielded', $result['status']);
        $this->assertNull(Cache::get("runtime:turn_buffer:{$sessionId}"));
    }
}
