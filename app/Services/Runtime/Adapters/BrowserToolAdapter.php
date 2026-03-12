<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Services\Runtime\BrowserProfileResolver;
use Illuminate\Support\Facades\Process;

class BrowserToolAdapter extends AbstractToolAdapter
{
    /**
     * Commands that only read page state (require browser_snapshot capability).
     */
    private const READ_COMMANDS = [
        'screenshot', 'snapshot', 'get', 'is', 'find',
        'console', 'errors', 'diff', 'network',
        'session', 'pdf',
    ];

    /**
     * Commands that mutate page state (require browser_action capability).
     */
    private const MUTATION_COMMANDS = [ // @phpstan-ignore classConstant.unused
        'open', 'click', 'dblclick', 'fill', 'type', 'press',
        'hover', 'focus', 'check', 'uncheck', 'select',
        'drag', 'upload', 'download', 'scroll', 'scrollintoview',
        'eval', 'back', 'forward', 'reload', 'wait',
        'set', 'keyboard', 'mouse', 'tab', 'close',
        'cookies', 'storage', 'highlight',
        'auth',
    ];

    public function name(): string
    {
        return 'browser';
    }

    public function schema(): array
    {
        return [
            'description' => implode("\n", [
                'Browser automation via agent-browser CLI. Pass the command as a string.',
                '',
                'Workflow: open URL → snapshot -i (get interactive elements with @ref IDs) → interact via @refs → screenshot to verify.',
                '',
                'Navigation: open <url>, back, forward, reload',
                'Interaction: click <sel>, fill <sel> <text>, type <sel> <text>, press <key>, hover <sel>, select <sel> <value>',
                'Forms: check <sel>, uncheck <sel>, focus <sel>, upload <sel> <files...>',
                'Inspection: snapshot [-i] [-c], screenshot [path] [--full] [--annotate], get text|html|value|url|title [sel]',
                'State checks: is visible|enabled|checked <sel>, find role|text|label <value> [action]',
                'Waiting: wait <sel|ms>, wait --load networkidle',
                'Scrolling: scroll up|down|left|right [px], scrollintoview <sel>',
                'JavaScript: eval <js>',
                'Info: get text <sel>, get url, get title, console, errors, network requests',
                'Browser auth (session-scoped): auth save <name> --url <url> --username <user> --password <pass>, auth login <name>',
                'Tabs: tab new, tab list, tab close, tab <n>',
                'Cookies: cookies get, cookies set --url <url> --domain <d> ..., cookies clear',
                '',
                'Selectors: CSS selectors or @ref IDs from snapshot output (e.g. @e1, @e2).',
                'Use snapshot -i first to discover interactive elements, then use their @refs.',
            ]),
            'parameters' => [
                'command' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The agent-browser command and arguments, e.g. "open https://example.com", "click @e2", "snapshot -i", "fill @e3 test@example.com"',
                ],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        $command = $args['command'] ?? '';
        $operation = $this->extractOperation($command);

        if ($context->mode === RuntimeMode::Safe) {
            return $this->isReadCommand($operation);
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        $command = $args['command'] ?? '';
        $operation = $this->extractOperation($command);

        if ($this->isReadCommand($operation)) {
            return 'browser_snapshot';
        }

        return 'browser_action';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $command = trim($args['command'] ?? '');

        if ($command === '') {
            return ToolResult::failure(
                'Browser command cannot be empty',
                $this->duration($startTime)
            );
        }

        $tokens = self::tokenizeCommand($command);
        $operation = $tokens[0] ?? '';

        $denied = config('runtime.browser.denied_commands', []);
        if (is_array($denied) && in_array($operation, $denied, true)) {
            return ToolResult::failure(
                "Browser command denied: {$operation}",
                $this->duration($startTime)
            );
        }

        $binary = config('runtime.browser.sidecar_binary', '/usr/local/bin/agent-browser');
        if ($binary === '' || ! is_executable($binary)) {
            return ToolResult::failure(
                'Browser sidecar binary not configured or not executable (AGENT_BROWSER_PATH)',
                $this->duration($startTime)
            );
        }

        $cmd = $this->buildCommand($binary, $tokens, $context);
        $timeout = (int) config('runtime.browser.timeout', 60);

        try {
            $result = Process::timeout($timeout)->run($cmd);

            if (! $result->successful()) {
                return ToolResult::failure(
                    'Browser sidecar failed: '.trim($result->errorOutput() ?: $result->output()),
                    $this->duration($startTime)
                );
            }

            $output = trim($result->output());

            return ToolResult::success([
                'command' => $operation,
                'output' => $output,
                'success' => true,
            ], $this->duration($startTime));
        } catch (\Throwable $e) {
            return ToolResult::failure(
                'Browser tool error: '.$e->getMessage(),
                $this->duration($startTime)
            );
        }
    }

    /**
     * Extract the operation (first token) from a command string.
     */
    private function extractOperation(string $command): string
    {
        $trimmed = ltrim($command);
        $spacePos = strpos($trimmed, ' ');

        return $spacePos !== false ? substr($trimmed, 0, $spacePos) : $trimmed;
    }

    /**
     * Check if an operation is read-only.
     */
    private function isReadCommand(string $operation): bool
    {
        return in_array($operation, self::READ_COMMANDS, true);
    }

    /**
     * Build the full CLI command array for Process execution.
     *
     * Reads per-session browser settings (profile, CDP, headed) from the
     * RuntimeContext, falling back to global config values.
     *
     * @param  array<int, string>  $tokens  Tokenized command arguments
     * @return array<int, string>
     */
    private function buildCommand(string $binary, array $tokens, RuntimeContext $context): array
    {
        $cmd = [$binary];
        $session = $context->session;

        // CDP mode: use `connect` command to attach to Chrome's existing context.
        // The --cdp flag creates an ISOLATED context (no cookies), so we use the
        // `connect` command which shares Chrome's default context with login sessions.
        // The daemon remembers the connection, so connect is idempotent.
        if ($session->hasCdpConnection()) {
            $cdpTarget = (string) ($this->extractCdpPort($session->browser_cdp_endpoint)
                ?? $session->browser_cdp_endpoint);

            // Pre-connect to Chrome (idempotent — harmless if already connected)
            Process::timeout(10)->run([$binary, 'connect', $cdpTarget]);

            // Then run the actual command (daemon uses the connected Chrome)
            return array_merge($cmd, $tokens);
        }

        // Auto-connect mode: auto-discover a running Chrome instance
        if (config('runtime.browser.auto_connect', false)) {
            $cmd[] = '--auto-connect';

            return array_merge($cmd, $tokens);
        }

        // Headed mode: per-session or global
        if ($this->resolveHeadedMode($session)) {
            $cmd[] = '--headed';
        }

        // Persistent profile: pass --profile with resolved filesystem path
        if ($session->hasPersistentBrowserProfile()) {
            $resolver = app(BrowserProfileResolver::class);
            $profilePath = $resolver->resolveProfilePath($context->user, $session->browser_profile_name);
            $resolver->ensureProfileDirectory($profilePath);

            $cmd[] = '--profile';
            $cmd[] = $profilePath;
        }

        // Custom executable path (e.g. separate Chromium to avoid macOS singleton conflict)
        $executablePath = config('runtime.browser.executable_path');
        if ($executablePath !== null && $executablePath !== '') {
            $cmd[] = '--executable-path';
            $cmd[] = $executablePath;
        }

        // Custom browser launch args (comma-separated Chromium flags)
        $args = config('runtime.browser.args');
        if ($args !== null && $args !== '') {
            $cmd[] = '--args';
            $cmd[] = $args;
        }

        // Session name (existing behavior, fallback for non-profile persistence)
        $sessionName = config('runtime.browser.session_name');
        if ($sessionName !== null && $sessionName !== '') {
            $cmd[] = '--session';
            $cmd[] = $sessionName;
        }

        return array_merge($cmd, $tokens);
    }

    /**
     * Determine whether the browser should run in headed (visible) mode.
     */
    private function resolveHeadedMode(RuntimeSession $session): bool
    {
        // Persistent profiles auto-enable headed mode for initial login
        if ($session->hasPersistentBrowserProfile()
            && config('runtime.browser.auto_headed_for_persistent', true)) {
            return true;
        }

        // Global config fallback
        return (bool) config('runtime.browser.headed', false);
    }

    /**
     * Tokenize a command string into arguments, handling quoted strings.
     *
     * Supports double quotes ("hello world") and single quotes ('hello world').
     * Backslash escapes within double-quoted strings are supported.
     *
     * @return array<int, string>
     */
    public static function tokenizeCommand(string $command): array
    {
        $tokens = [];
        $current = '';
        $inDouble = false;
        $inSingle = false;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($inDouble) {
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $command[++$i];
                } elseif ($char === '"') {
                    $inDouble = false;
                } else {
                    $current .= $char;
                }
            } elseif ($inSingle) {
                if ($char === "'") {
                    $inSingle = false;
                } else {
                    $current .= $char;
                }
            } elseif ($char === '"') {
                $inDouble = true;
            } elseif ($char === "'") {
                $inSingle = true;
            } elseif ($char === ' ' || $char === "\t") {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Extract the port number from a CDP WebSocket URL.
     *
     * E.g. "ws://localhost:9222/devtools/browser/abc123" → 9222
     *      "9222" → 9222
     */
    private function extractCdpPort(string $endpoint): ?int
    {
        if (ctype_digit($endpoint)) {
            return (int) $endpoint;
        }

        $httpUrl = (string) preg_replace('/^wss?:/', 'http:', $endpoint);
        $parsed = parse_url($httpUrl);

        return isset($parsed['port']) ? (int) $parsed['port'] : null;
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
