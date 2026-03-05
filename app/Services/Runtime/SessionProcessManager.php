<?php

namespace App\Services\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manages runner session state and wrapper process lifecycle for runtime sessions.
 *
 * IMPORTANT: The wrapper process is managed via in-memory static state ($activeProcesses).
 * This means all turns for a given session MUST be processed by the same queue worker.
 * When using Horizon, ensure session affinity (e.g. route by session ID hash) or use
 * a single-process queue for the runtime queue. Without affinity, the wrapper pipes
 * are only accessible in the worker that started them.
 */
class SessionProcessManager
{
    private const CACHE_PREFIX = 'runtime:runner_session:';

    private const PROCESS_PREFIX = 'runtime:wrapper_pid:';

    private const TTL_SECONDS = 86400;

    /** @var array<string, array{resource: resource, pipes: array, pid: int}> */
    private static array $activeProcesses = [];

    public function getRunnerSessionId(string $runtimeSessionId): ?string
    {
        $key = self::CACHE_PREFIX.$runtimeSessionId;
        $value = Cache::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setRunnerSessionId(string $runtimeSessionId, string $runnerSessionId): void
    {
        Cache::put(self::CACHE_PREFIX.$runtimeSessionId, $runnerSessionId, self::TTL_SECONDS);
    }

    public function clearSession(string $runtimeSessionId): void
    {
        Cache::forget(self::CACHE_PREFIX.$runtimeSessionId);
    }

    public function isWrapperEnabled(): bool
    {
        return (bool) config('runtime.cli.wrapper_enabled', false);
    }

    public function hasActiveWrapper(string $runtimeSessionId): bool
    {
        if (isset(self::$activeProcesses[$runtimeSessionId])) {
            $resource = self::$activeProcesses[$runtimeSessionId]['resource'];
            if (is_resource($resource)) {
                $status = proc_get_status($resource);
                if ($status['running']) {
                    return true;
                }
            }
            unset(self::$activeProcesses[$runtimeSessionId]);
        }

        // PID exists in cache but this worker has no pipes -- kill the orphan
        // so startWrapper() can create a fresh wrapper we can actually use.
        $pid = (int) (Cache::get(self::PROCESS_PREFIX.$runtimeSessionId) ?? 0);
        if ($pid > 0 && posix_kill($pid, 0)) {
            posix_kill($pid, 15);
            Cache::forget(self::PROCESS_PREFIX.$runtimeSessionId);
        }

        return false;
    }

    public function startWrapper(
        string $runtimeSessionId,
        string $runnerExecutable,
        string $runnerType,
        string $workingDirectory,
        string $apiKey,
        ?string $systemPrompt = null,
        \App\Enums\Messenger\ApprovalMode $approvalMode = \App\Enums\Messenger\ApprovalMode::Autonomous,
    ): bool {
        if ($this->hasActiveWrapper($runtimeSessionId)) {
            return true;
        }

        $wrapperScript = base_path('bin/session-wrapper');

        if (! file_exists($wrapperScript)) {
            Log::error('SessionProcessManager: wrapper script not found', [
                'session_id' => $runtimeSessionId,
                'path' => $wrapperScript,
            ]);

            return false;
        }

        $env = [
            'WRAPPER_RUNNER_EXECUTABLE' => $runnerExecutable,
            'WRAPPER_RUNNER_TYPE' => $runnerType,
            'WRAPPER_WORKING_DIRECTORY' => $workingDirectory,
            'WRAPPER_IDLE_TIMEOUT' => (string) config('runtime.cli.wrapper_idle_timeout', 3600),
            'WRAPPER_OUTPUT_FORMAT' => 'stream-json',
            'WRAPPER_SYSTEM_PROMPT' => $systemPrompt ?? '',
            'WRAPPER_APPROVAL_MODE' => $approvalMode->value,
            'ANTHROPIC_API_KEY' => $apiKey,
            'HOME' => getenv('HOME') ?: '/tmp',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = [PHP_BINARY, $wrapperScript];
        $resource = proc_open($command, $descriptors, $pipes, $workingDirectory, $env);

        if (! is_resource($resource)) {
            Log::error('SessionProcessManager: failed to start wrapper', [
                'session_id' => $runtimeSessionId,
            ]);

            return false;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $status = proc_get_status($resource);
        $pid = $status['pid'] ?? 0;

        Cache::put(self::PROCESS_PREFIX.$runtimeSessionId, $pid, self::TTL_SECONDS);

        self::$activeProcesses[$runtimeSessionId] = [
            'resource' => $resource,
            'pipes' => $pipes,
            'pid' => $pid,
        ];

        Log::info('SessionProcessManager: wrapper started', [
            'session_id' => $runtimeSessionId,
            'pid' => $pid,
            'runner_type' => $runnerType,
        ]);

        return true;
    }

    /**
     * @return array{status: 'completed'|'failed', text?: string, runner_session_id?: string, error?: string}
     */
    public function sendMessage(string $runtimeSessionId, string $message, int $timeoutSeconds = 300): array
    {
        $entry = self::$activeProcesses[$runtimeSessionId] ?? null;
        if ($entry === null) {
            return ['status' => 'failed', 'error' => 'No active process for session.'];
        }

        $pipes = $entry['pipes'];
        $stdin = $pipes[0];

        $jsonMessage = json_encode(['message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $written = @fwrite($stdin, $jsonMessage."\n");
        @fflush($stdin);

        if ($written === false || $written === 0) {
            return ['status' => 'failed', 'error' => 'Failed to write to process stdin.'];
        }

        return $this->readTurnResponse($runtimeSessionId, $timeoutSeconds);
    }

    public function terminateSession(string $runtimeSessionId): void
    {
        $entry = self::$activeProcesses[$runtimeSessionId] ?? null;

        if ($entry !== null) {
            $pipes = $entry['pipes'];
            $resource = $entry['resource'];

            $shutdownMsg = json_encode(['type' => 'shutdown']);
            @fwrite($pipes[0], $shutdownMsg."\n");
            @fflush($pipes[0]);

            usleep(200_000);

            @fclose($pipes[0]);
            @fclose($pipes[1]);
            @fclose($pipes[2]);

            $status = proc_get_status($resource);
            if ($status['running']) {
                proc_terminate($resource, 15);
                usleep(500_000);
                $status = proc_get_status($resource);
                if ($status['running']) {
                    proc_terminate($resource, 9);
                }
            }

            @proc_close($resource);

            unset(self::$activeProcesses[$runtimeSessionId]);

            Log::info('SessionProcessManager: process terminated', [
                'session_id' => $runtimeSessionId,
            ]);
        } else {
            $pid = (int) (Cache::get(self::PROCESS_PREFIX.$runtimeSessionId) ?? 0);
            if ($pid > 0 && posix_kill($pid, 0)) {
                posix_kill($pid, 15);
                usleep(500_000);
                if (posix_kill($pid, 0)) {
                    posix_kill($pid, 9);
                }
            }
        }

        Cache::forget(self::PROCESS_PREFIX.$runtimeSessionId);
        $this->clearSession($runtimeSessionId);
    }

    /**
     * @return array{status: 'completed'|'failed', text?: string, runner_session_id?: string, error?: string}
     */
    public function readTurnResponse(string $runtimeSessionId, int $timeoutSeconds): array
    {
        $entry = self::$activeProcesses[$runtimeSessionId] ?? null;
        if ($entry === null) {
            return ['status' => 'failed', 'error' => 'No active process for session.'];
        }

        $stdout = $entry['pipes'][1];
        $stderr = $entry['pipes'][2];
        $deadline = time() + $timeoutSeconds;
        $fragments = [];
        $runnerSessionId = null;

        while (time() < $deadline) {
            $line = @fgets($stdout);
            if ($line !== false && trim($line) !== '') {
                $decoded = json_decode(trim($line), true);

                if (is_array($decoded) && ($decoded['type'] ?? '') === 'turn_complete') {
                    $runnerSessionId = $decoded['session_id'] ?? $runnerSessionId;
                    $exitCode = $decoded['exit_code'] ?? 0;

                    if ($runnerSessionId !== null) {
                        $this->setRunnerSessionId($runtimeSessionId, $runnerSessionId);
                    }

                    if ($exitCode !== 0 && $fragments === []) {
                        return [
                            'status' => 'failed',
                            'error' => 'Request failed with exit code '.$exitCode,
                            'runner_session_id' => $runnerSessionId,
                        ];
                    }

                    $text = $this->extractTextFromFragments($fragments);

                    return [
                        'status' => 'completed',
                        'text' => $text !== '' ? $text : 'Done.',
                        'runner_session_id' => $runnerSessionId,
                    ];
                }

                if (is_array($decoded)) {
                    $sessionId = $decoded['session_id'] ?? null;
                    if (is_string($sessionId) && $sessionId !== '') {
                        $runnerSessionId = $sessionId;
                    }
                    $event = $this->unwrapStreamEvent($decoded);
                    $eventSessionId = $event['session_id'] ?? null;
                    if (is_string($eventSessionId) && $eventSessionId !== '') {
                        $runnerSessionId = $eventSessionId;
                    }
                }

                $fragments[] = trim($line);
            }

            $stderrLine = @fgets($stderr);
            if ($stderrLine !== false && trim($stderrLine) !== '') {
                Log::debug('SessionProcessManager: wrapper stderr', [
                    'session_id' => $runtimeSessionId,
                    'line' => trim($stderrLine),
                ]);
            }

            if ($line === false) {
                $resource = $entry['resource'];
                $status = proc_get_status($resource);
                if (! $status['running']) {
                    $text = $this->extractTextFromFragments($fragments);

                    return [
                        'status' => $text !== '' ? 'completed' : 'failed',
                        'text' => $text !== '' ? $text : null,
                        'error' => $text === '' ? 'Process exited unexpectedly.' : null,
                        'runner_session_id' => $runnerSessionId,
                    ];
                }

                usleep(10_000);
            }
        }

        $text = $this->extractTextFromFragments($fragments);

        return [
            'status' => 'failed',
            'error' => 'Request timed out after '.$timeoutSeconds.'s.',
            'text' => $text !== '' ? $text : null,
            'runner_session_id' => $runnerSessionId,
        ];
    }

    private function extractTextFromFragments(array $fragments): string
    {
        $textParts = [];
        $resultText = '';
        $inTextBlock = false;

        foreach ($fragments as $fragment) {
            $decoded = json_decode($fragment, true);
            if (! is_array($decoded)) {
                continue;
            }

            $event = $this->unwrapStreamEvent($decoded);
            $type = $event['type'] ?? '';

            if ($type === 'content_block_start') {
                $blockType = $event['content_block']['type'] ?? '';
                $inTextBlock = $blockType === 'text';
            }

            if ($type === 'content_block_stop') {
                $inTextBlock = false;
            }

            if ($type === 'content_block_delta' && $inTextBlock) {
                $delta = $event['delta'] ?? [];
                $deltaType = is_array($delta) ? ($delta['type'] ?? '') : '';
                if ($deltaType === 'text_delta') {
                    $text = $delta['text'] ?? '';
                    if ($text !== '') {
                        $textParts[] = $text;
                    }
                }
            }

            if ($type === 'result' && isset($event['result']) && is_string($event['result'])) {
                $resultText = $event['result'];
            }
        }

        $assembled = implode('', $textParts);

        return $assembled !== '' ? $assembled : $resultText;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function unwrapStreamEvent(array $decoded): array
    {
        if (($decoded['type'] ?? '') === 'stream_event' && isset($decoded['event']) && is_array($decoded['event'])) {
            return $decoded['event'];
        }

        return $decoded;
    }
}
