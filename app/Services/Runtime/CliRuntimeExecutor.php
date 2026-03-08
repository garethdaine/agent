<?php

namespace App\Services\Runtime;

use App\Enums\Messenger\ApprovalMode;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\InjectionAction;
use App\Models\Runtime\RuntimeSession;
use App\Services\Credentials\CredentialsManager;
use App\Services\Security\InjectionDetectionEngine;
use App\Services\Security\SecurityEventLogger;
use App\Support\Agent\EngineeringRulesInjector;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Executes a single runtime turn by running the CLI (claude/codex) as a conversational endpoint.
 *
 * The message is piped via stdin (not a file path) so the CLI treats it as a direct prompt.
 * --strict-mcp-config skips all MCP server initialization for fast startup.
 * --system-prompt injects the connector personality/identity.
 * --resume preserves conversation context between turns.
 */
class CliRuntimeExecutor
{
    public function __construct(
        private CredentialsManager $credentialsManager,
        private ?InjectionDetectionEngine $injectionDetector = null,
        private ?SecurityEventLogger $securityLogger = null,
    ) {}

    /**
     * @param  string|null  $runnerTypeOverride  When set (e.g. from connector config), use this runner instead of config.
     * @param  string|null  $runnerSessionId  When set, --resume is passed for conversation continuity.
     * @param  string|null  $systemPrompt  Connector soul (personality, identity) to inject via --system-prompt.
     * @return array{status: 'completed'|'failed', text?: string, error?: string, runner_session_id?: string}
     */
    public function executeTurn(
        RuntimeSession $session,
        string $userMessage,
        ?string $runnerTypeOverride = null,
        ?string $runnerSessionId = null,
        ?string $systemPrompt = null,
        ApprovalMode $approvalMode = ApprovalMode::Autonomous,
    ): array {
        $session->load('user');
        $user = $session->user;
        if ($user === null) {
            return ['status' => 'failed', 'error' => 'Runtime session has no user.'];
        }

        $apiKey = $this->credentialsManager->get($user, 'anthropic', 'api_key');
        if ($apiKey === null || $apiKey === '') {
            return [
                'status' => 'failed',
                'error' => 'Anthropic API key not found. Add your key in the credential manager (Settings → Credentials → Anthropic).',
            ];
        }

        $runnerType = $runnerTypeOverride ?? config('runtime.cli.runner_type', 'claude');
        $executables = config('agent.runner_executables', []);
        $executable = $executables[$runnerType] ?? null;

        if ($executable === null) {
            return [
                'status' => 'failed',
                'error' => "Runtime CLI runner '{$runnerType}' is not configured (runner_executables).",
            ];
        }

        if ($this->injectionDetector !== null && $systemPrompt !== null) {
            $mode = RuntimeMode::tryFrom($session->mode ?? 'safe') ?? RuntimeMode::Safe;
            $scanResult = $this->injectionDetector->scan($userMessage, $mode);
            if ($scanResult->recommendedAction === InjectionAction::Block) {
                $this->securityLogger?->logInjectionDetected($scanResult->score, 'cli.messenger_input', $userMessage, (string) $session->id, null);

                return ['status' => 'failed', 'error' => 'Message blocked by security policy: potential prompt injection detected.'];
            }
            if ($scanResult->score > 0) {
                $this->securityLogger?->logInjectionDetected($scanResult->score, 'cli.messenger_input', $userMessage, (string) $session->id, null);
            }
        }

        $workingDir = $session->workspace_root !== null && $session->workspace_root !== ''
            ? $session->workspace_root
            : (config('agent.allowed_working_directory_bases', [])[0] ?? base_path());

        // Engineering rules injection for sub-agent system prompt (non-blocking)
        try {
            $rulesInjector = app(EngineeringRulesInjector::class);
            if ($rulesInjector->isEnabled()) {
                $rulesProfile = (string) config('agent.engineering_rules.profiles.sub_agent', 'core');
                $systemPrompt = $rulesInjector->injectIntoSystemPrompt($systemPrompt ?? '', $rulesProfile);
            }
        } catch (\Throwable $e) {
            Log::channel('runtime')->warning('Engineering rules injection failed for runtime turn', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }

        $sessionResumeEnabled = (bool) config('runtime.cli.session_resume', true);
        $useResume = $sessionResumeEnabled && $runnerSessionId !== null && $runnerSessionId !== '';

        try {
            $command = $this->buildRuntimeCommand($executable, $runnerType, $useResume ? $runnerSessionId : null, $systemPrompt, $approvalMode);
            $parentEnv = array_merge($_ENV ?? [], $_SERVER ?? []);
            $env = array_merge(
                array_filter($parentEnv, static fn ($_, string $k): bool => ! str_starts_with($k, 'ANTHROPIC_'), ARRAY_FILTER_USE_BOTH),
                ['ANTHROPIC_API_KEY' => $apiKey]
            );
            if (config('runtime.browser.headed', false)) {
                $env['AGENT_BROWSER_HEADED'] = '1';
            }

            Log::channel('runtime')->info('CliRuntimeExecutor: Starting CLI process', [
                'session_id' => $session->id,
                'runner_type' => $runnerType,
                'has_system_prompt' => $systemPrompt !== null && $systemPrompt !== '',
                'has_resume' => $useResume,
                'timeout_seconds' => (int) config('runtime.cli.timeout_seconds', 300),
            ]);

            $process = new Process($command, $workingDir, $env);
            $process->setInput($userMessage);
            $process->setTimeout((int) config('runtime.cli.timeout_seconds', 300));
            $process->run();

            Log::channel('runtime')->info('CliRuntimeExecutor: CLI process finished', [
                'session_id' => $session->id,
                'exit_code' => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
            ]);

            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            $text = $this->extractFinalText($stdout, $runnerType);
            $parsedRunnerSessionId = $this->extractRunnerSessionId($stdout, $runnerType);

            Log::channel('runtime')->info('CliRuntimeExecutor: Output parsed', [
                'session_id' => $session->id,
                'runner_session_id' => $parsedRunnerSessionId,
                'text_length' => strlen($text),
                'text_preview' => $text !== '' ? mb_substr($text, 0, 120) : '(empty)',
                'stdout_length' => strlen($stdout),
                'stderr_length' => strlen($stderr),
                'had_resume' => $useResume,
            ]);

            if ($process->isSuccessful()) {
                if ($text === '' && $stdout !== '') {
                    Log::channel('runtime')->warning('CliRuntimeExecutor: Successful process but no text extracted', [
                        'session_id' => $session->id,
                        'stdout_tail' => mb_substr($stdout, -500),
                    ]);
                }

                $result = [
                    'status' => 'completed',
                    'text' => $text !== '' ? $text : 'Done.',
                ];
                if ($parsedRunnerSessionId !== null) {
                    $result['runner_session_id'] = $parsedRunnerSessionId;
                }

                return $result;
            }

            $error = trim($stderr) !== '' ? trim($stderr) : 'CLI process exited with code '.$process->getExitCode();
            if (strlen($error) > 500) {
                $error = substr($error, 0, 497).'…';
            }

            return ['status' => 'failed', 'error' => $error];
        } catch (\Throwable $e) {
            Log::channel('runtime')->error('CliRuntimeExecutor: Process exception', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the CLI command for a runtime conversational turn.
     * Message is piped via stdin (not a file path) so the CLI treats it as a direct prompt.
     *
     * @return array<int, string>
     */
    private function buildRuntimeCommand(
        string $executable,
        string $runnerType,
        ?string $runnerSessionId,
        ?string $systemPrompt,
        ApprovalMode $approvalMode = ApprovalMode::Autonomous,
    ): array {
        $command = [$executable];

        if ($runnerType === 'claude') {
            $command[] = '--verbose';
            $command[] = '-p';
            $command[] = '--output-format';
            $command[] = 'stream-json';
            $command[] = '--strict-mcp-config';

            match ($approvalMode) {
                ApprovalMode::Autonomous => $command[] = '--dangerously-skip-permissions',
                ApprovalMode::Supervised => array_push($command, '--allowedTools', 'Read,Write,Edit,Grep,Glob,LS,WebSearch,WebFetch,Bash'),
                ApprovalMode::Restricted => array_push($command, '--allowedTools', 'Read,Grep,Glob,LS,WebSearch,WebFetch'),
            };

            if ($systemPrompt !== null && $systemPrompt !== '') {
                $command[] = '--system-prompt';
                $command[] = $systemPrompt;
            }

            if ($runnerSessionId !== null && $runnerSessionId !== '') {
                $command[] = '--resume';
                $command[] = $runnerSessionId;
            }
        }

        if ($runnerType === 'codex') {
            $command[] = 'exec';
            $command[] = '--json';
        }

        return $command;
    }

    private function extractFinalText(string $stdout, string $runnerType): string
    {
        $lines = preg_split('/\R/', trim($stdout)) ?: [];
        $fragments = [];
        $resultText = '';
        $inTextBlock = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            if ($runnerType === 'claude') {
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
                            $fragments[] = $text;
                        }
                    }
                }

                if ($type === 'result' && isset($event['result']) && is_string($event['result'])) {
                    $resultText = $event['result'];
                }
            }

            if ($runnerType === 'codex') {
                $text = $decoded['text'] ?? $decoded['content'] ?? $decoded['message'] ?? '';
                if (is_string($text) && $text !== '') {
                    $fragments[] = $text;
                }
            }
        }

        $assembled = implode('', $fragments);

        return $assembled !== '' ? $assembled : $resultText;
    }

    private function extractRunnerSessionId(string $stdout, string $runnerType): ?string
    {
        if ($runnerType !== 'claude') {
            return null;
        }

        $lines = preg_split('/\R/', trim($stdout)) ?: [];
        $lastSessionId = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            $sessionId = $decoded['session_id'] ?? null;
            if (is_string($sessionId) && $sessionId !== '') {
                $lastSessionId = $sessionId;
            }

            $event = $this->unwrapStreamEvent($decoded);
            $eventSessionId = $event['session_id'] ?? null;
            if (is_string($eventSessionId) && $eventSessionId !== '') {
                $lastSessionId = $eventSessionId;
            }
        }

        return $lastSessionId;
    }

    /**
     * Claude CLI --output-format stream-json wraps API events in
     * {"type":"stream_event","event":{...}}. Unwrap to get the inner event.
     *
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
