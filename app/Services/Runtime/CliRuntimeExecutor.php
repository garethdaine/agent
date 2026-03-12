<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Enums\Messenger\ApprovalMode;
use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\InjectionAction;
use App\Models\Runtime\RuntimeSession;
use App\Services\Credentials\CredentialsManager;
use App\Services\Credentials\VaultRuntimeTokenService;
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

        $apiKey = $this->credentialsManager->get($user, 'anthropic', 'api_key'); // @phpstan-ignore argument.type
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
            $parentEnv = array_merge($_ENV, $_SERVER);
            $env = array_merge(
                array_filter($parentEnv, static fn ($_, string $k): bool => ! str_starts_with($k, 'ANTHROPIC_'), ARRAY_FILTER_USE_BOTH),
                ['ANTHROPIC_API_KEY' => $apiKey]
            );
            // Inject browser profile, CDP, and headed env vars so the CLI agent
            // passes the correct flags when invoking agent-browser.
            $this->injectBrowserEnv($env, $session);

            // Inject vault runtime access token so the CLI agent can retrieve
            // persistent credentials via the vault API endpoint.
            $this->injectVaultEnv($env, $session);

            // Suppress session-nesting marker env vars so the child CLI does
            // not refuse to start. Setting the value to false makes Symfony
            // Process treat the key as present (preventing getDefaultEnv()
            // backfill from the parent process) while skipping it from the
            // envPairs array passed to proc_open().
            $env['CLAUDECODE'] = false;

            // Also suppress Codex session vars when running the codex runner
            // to prevent nested-session conflicts (same pattern as CodexAdapter).
            if ($runnerType === 'codex') {
                $env['CODEX_THREAD_ID'] = false;
                $env['CODEX_SESSION_ID'] = false;
                $env['CODEX_INTERNAL_ORIGINATOR_OVERRIDE'] = false;
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

    /**
     * Inject browser profile, CDP, and headed environment variables.
     *
     * The CLI sub-agent invokes agent-browser via Bash; these env vars
     * ensure the correct --profile, --cdp, --headed, or --auto-connect
     * flags are picked up by agent-browser natively.
     *
     * @param  array<string, mixed>  $env
     */
    private function injectBrowserEnv(array &$env, RuntimeSession $session): void
    {
        try {
            $persistenceMode = $session->browser_persistence_mode;

            Log::channel('runtime')->info('CliRuntimeExecutor: Browser env injection', [
                'session_id' => $session->id,
                'persistence_mode' => $persistenceMode?->value,
                'profile_name' => $session->browser_profile_name,
                'cdp_endpoint' => $session->browser_cdp_endpoint,
                'has_persistent_profile' => $session->hasPersistentBrowserProfile(),
                'has_cdp' => $session->hasCdpConnection(),
            ]);

            // Persistent profile: inject profile path
            if ($persistenceMode === BrowserPersistenceMode::Persistent
                && $session->browser_profile_name !== null
                && $session->browser_profile_name !== '') {
                $user = $session->user;
                if ($user !== null) {
                    $resolver = app(BrowserProfileResolver::class);
                    $profilePath = $resolver->resolveProfilePath($user, $session->browser_profile_name);
                    $resolver->ensureProfileDirectory($profilePath);
                    $env['AGENT_BROWSER_PROFILE'] = $profilePath;
                }
            }

            // CDP endpoint: agent-browser has no AGENT_BROWSER_CDP env var.
            // The --cdp flag creates an ISOLATED context (no cookies from Chrome).
            // Instead, enable auto-connect so agent-browser discovers Chrome's debugging port,
            // and the system prompt instructs the agent to use `connect <port>` command
            // which attaches to Chrome's existing context with active login sessions.
            if ($persistenceMode === BrowserPersistenceMode::Cdp
                && $session->browser_cdp_endpoint !== null
                && $session->browser_cdp_endpoint !== '') {
                $env['AGENT_BROWSER_AUTO_CONNECT'] = '1';
            }

            // Auto-connect: discover running Chrome instance (global config)
            if (config('runtime.browser.auto_connect', false)) {
                $env['AGENT_BROWSER_AUTO_CONNECT'] = '1';
            }

            // Headed mode: persistent auto-headed or global config
            $headed = false;
            if ($persistenceMode === BrowserPersistenceMode::Persistent
                && $session->browser_profile_name !== null
                && config('runtime.browser.auto_headed_for_persistent', true)) {
                $headed = true;
            }
            if (config('runtime.browser.headed', false)) {
                $headed = true;
            }
            if ($headed) {
                $env['AGENT_BROWSER_HEADED'] = '1';
            }

            // Custom executable path (e.g. separate Chromium install to avoid macOS singleton conflict)
            $executablePath = config('runtime.browser.executable_path');
            if ($executablePath !== null && $executablePath !== '') {
                $env['AGENT_BROWSER_EXECUTABLE_PATH'] = $executablePath;
            }

            // Custom browser launch args (comma-separated Chromium flags)
            // When headed mode is active, inject flags to help Chromium coexist with Chrome on macOS
            $args = config('runtime.browser.args');
            if ($args !== null && $args !== '') {
                $env['AGENT_BROWSER_ARGS'] = $args;
            } elseif ($headed) {
                // Default coexistence flags for headed mode:
                // --no-first-run: skip first-run dialog
                // --no-default-browser-check: skip default browser prompt
                // --disable-background-networking: reduce background activity
                $env['AGENT_BROWSER_ARGS'] = '--no-first-run,--no-default-browser-check,--disable-background-networking';
            }
        } catch (\Throwable $e) {
            Log::channel('runtime')->warning('CliRuntimeExecutor: Failed to inject browser env', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract the port number from a CDP WebSocket URL.
     *
     * E.g. "ws://localhost:9222/devtools/browser/abc123" → 9222
     *      "ws://127.0.0.1:9222" → 9222
     *      "9222" → 9222
     */
    private function extractCdpPort(string $endpoint): ?int
    {
        // Already a plain port number
        if (ctype_digit($endpoint)) {
            return (int) $endpoint;
        }

        // Parse ws:// or wss:// URL
        $parsed = parse_url($endpoint);
        if ($parsed !== false && isset($parsed['port'])) {
            return (int) $parsed['port'];
        }

        // Try http:// prefix for parse_url (ws:// may not parse correctly in all PHP versions)
        $httpUrl = preg_replace('/^wss?:/', 'http:', $endpoint);
        if ($httpUrl !== null) {
            $parsed = parse_url($httpUrl);
            if ($parsed !== false && isset($parsed['port'])) {
                return (int) $parsed['port'];
            }
        }

        return null;
    }

    /**
     * Inject vault runtime environment variables so the CLI agent can retrieve
     * persistent credentials via curl.
     *
     * @param  array<string, mixed>  $env
     */
    private function injectVaultEnv(array &$env, RuntimeSession $session): void
    {
        try {
            $user = $session->user;
            if ($user === null) {
                return;
            }

            $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
            $vaultBase = $appUrl.'/agent/api/v1/runtime/vault';

            $tokenService = app(VaultRuntimeTokenService::class);
            $ttl = (int) config('runtime.cli.timeout_seconds', 1800);
            $token = $tokenService->generate($user, (string) $session->id, $ttl);

            $env['VAULT_API_BASE'] = $vaultBase;
            $env['VAULT_TOKEN'] = $token;
        } catch (\Throwable $e) {
            Log::channel('runtime')->warning('CliRuntimeExecutor: Failed to inject vault env', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
