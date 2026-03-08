<?php

namespace Tests\Unit\Runtime;

use App\Services\Runtime\SessionProcessManager;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class SessionProcessManagerFragmentLoggingTest extends TestCase
{
    private function injectFakeProcess(SessionProcessManager $manager, string $sessionId): void
    {
        $process = proc_open(
            'sleep 5',
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'r'],
                2 => ['pipe', 'r'],
            ],
            $pipes,
        );

        $reflection = new ReflectionClass($manager);
        $prop = $reflection->getProperty('activeProcesses');
        $prop->setAccessible(true);

        $existing = $prop->getValue();
        $existing[$sessionId] = [
            'resource' => $process,
            'pipes' => $pipes,
        ];
        $prop->setValue(null, $existing);
    }

    private function cleanupFakeProcess(SessionProcessManager $manager, string $sessionId): void
    {
        $reflection = new ReflectionClass($manager);
        $prop = $reflection->getProperty('activeProcesses');
        $prop->setAccessible(true);

        $processes = $prop->getValue();
        if (isset($processes[$sessionId])) {
            foreach ($processes[$sessionId]['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }
            if (is_resource($processes[$sessionId]['resource'])) {
                @proc_terminate($processes[$sessionId]['resource']);
                @proc_close($processes[$sessionId]['resource']);
            }
            unset($processes[$sessionId]);
            $prop->setValue(null, $processes);
        }
    }

    public function test_heartbeat_logs_turn_activity(): void
    {
        $manager = new SessionProcessManager;
        $sessionId = 'test-logging-session';

        $this->injectFakeProcess($manager, $sessionId);

        $logged = false;
        $channelLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelLogger->shouldReceive('debug')
            ->withArgs(function (string $message, array $context = []) use (&$logged) {
                if ($message === 'SessionProcessManager: turn activity') {
                    $logged = true;

                    return isset($context['session_id'])
                        && isset($context['elapsed_seconds'])
                        && isset($context['fragment_count']);
                }

                return true;
            })
            ->zeroOrMoreTimes();
        $channelLogger->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $channelLogger->shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes();
        $channelLogger->shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('channel')->with('runtime')->andReturn($channelLogger)->zeroOrMoreTimes();
        Log::shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes();

        $result = $manager->readTurnResponse($sessionId, 2, null, 1);

        $this->cleanupFakeProcess($manager, $sessionId);

        $this->assertTrue($logged, 'Expected "SessionProcessManager: turn activity" log entry was not emitted');
        $this->assertEquals('failed', $result['status']);
    }
}
