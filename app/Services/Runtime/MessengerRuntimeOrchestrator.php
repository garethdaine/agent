<?php

namespace App\Services\Runtime;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Messenger\ApprovalMode;
use App\Enums\Runtime\RuntimeTurnStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Services\Credentials\CredentialsManager;
use App\Services\Security\MessengerSecurityGuard;
use App\Services\Security\TurnSecurityContext;
use Illuminate\Support\Facades\Log;

class MessengerRuntimeOrchestrator
{
    private const MAX_TOOL_ITERATIONS = 10;

    public function __construct(
        private ToolGateway $toolGateway,
        private RuntimeLlmClient $llmClient,
        private CliRuntimeExecutor $cliExecutor,
        private SessionProcessManager $sessionProcessManager,
        private CredentialsManager $credentialsManager,
        private ?MessengerSecurityGuard $securityGuard = null,
    ) {}

    /**
     * Execute one turn. When runtime.use_cli is true, runs the CLI (normal runtime);
     * otherwise runs in-app LLM with tools. API key always from credential manager.
     *
     * @param  string|null  $runnerTypeOverride  When set (e.g. from connector config), use this CLI runner instead of config.
     * @param  string|null  $systemPrompt  Connector soul text (identity, personality, user context) for CLI --system-prompt.
     * @return array{status: 'completed'|'failed'|'pending_approval', text?: string, tool_call_id?: string, error?: string}
     */
    public function executeTurn(RuntimeSession $session, string $userMessage, ?string $runnerTypeOverride = null, ?string $systemPrompt = null, ApprovalMode $approvalMode = ApprovalMode::Autonomous, ?\Closure $onProgress = null): array
    {
        $session->load(['user', 'policySnapshots']);
        $user = $session->user;
        $sequence = $session->turns()->count() + 1;

        if ($sequence === 1 && ($session->title === null || trim((string) $session->title) === '')) {
            $session->update(['title' => $this->deriveSessionTitle($userMessage)]);
            $session->refresh();
        }

        $turn = RuntimeTurn::create([
            'runtime_session_id' => $session->id,
            'sequence' => $sequence,
            'status' => RuntimeTurnStatus::Running,
        ]);

        if (config('runtime.use_cli', true)) {
            if ($this->sessionProcessManager->isWrapperEnabled()) {
                $result = $this->executeViaWrapper($session, $userMessage, $runnerTypeOverride, $systemPrompt, $approvalMode, $onProgress);
            } else {
                $runnerSessionId = $this->sessionProcessManager->getRunnerSessionId($session->id);

                Log::channel('runtime')->info('MessengerRuntimeOrchestrator: CLI turn', [
                    'session_id' => $session->id,
                    'has_runner_session_id' => $runnerSessionId !== null,
                    'runner_session_id' => $runnerSessionId ? mb_substr($runnerSessionId, 0, 12).'…' : null,
                ]);

                $result = $this->cliExecutor->executeTurn($session, $userMessage, $runnerTypeOverride, $runnerSessionId, $systemPrompt, $approvalMode);

                if ($result['status'] === 'completed' && isset($result['runner_session_id'])) {
                    $this->sessionProcessManager->setRunnerSessionId($session->id, $result['runner_session_id']);
                    Log::channel('runtime')->info('MessengerRuntimeOrchestrator: Runner session ID stored', [
                        'session_id' => $session->id,
                        'runner_session_id' => mb_substr($result['runner_session_id'], 0, 12).'…',
                    ]);
                } elseif ($result['status'] === 'completed') {
                    Log::channel('runtime')->warning('MessengerRuntimeOrchestrator: No runner_session_id in CLI result', [
                        'session_id' => $session->id,
                    ]);
                }
            }

            if ($this->securityGuard !== null && isset($result['text'])) {
                $result['text'] = $this->securityGuard->sanitizeResponse($result['text']);
            }

            $summary = $result['text'] ?? $result['error'] ?? null;
            $turn->update([
                'status' => $result['status'] === 'completed' ? RuntimeTurnStatus::Completed : RuntimeTurnStatus::Failed,
                'summary' => $summary !== null && mb_strlen($summary) > 500 ? mb_substr($summary, 0, 497).'...' : $summary,
            ]);

            return $result;
        }

        $policy = $this->getPolicyForSession($session);
        $context = new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $session->mode,
            policy: $policy,
            workspaceRoot: $session->workspace_root,
        );

        $messages = [['role' => 'user', 'content' => $userMessage]];
        $systemPrompt = $this->buildSystemPrompt($context);
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $turnSecurityContext = new TurnSecurityContext;

        try {
            for ($iter = 0; $iter < self::MAX_TOOL_ITERATIONS; $iter++) {
                $response = $this->llmClient->complete($messages, $systemPrompt, $user);
                $content = $response['content'];
                $stopReason = $response['stop_reason'];
                $totalInputTokens += $response['usage']['input_tokens'];
                $totalOutputTokens += $response['usage']['output_tokens'];

                $toolUses = $this->extractToolUses($content);
                $textBlocks = $this->extractTextBlocks($content);

                if ($stopReason === 'end_turn' || $toolUses === []) {
                    $finalText = implode("\n", $textBlocks);
                    if ($this->securityGuard !== null) {
                        $finalText = $this->securityGuard->sanitizeResponse($finalText);
                    }
                    $turn->update([
                        'status' => RuntimeTurnStatus::Completed,
                        'summary' => mb_strlen($finalText) > 500 ? mb_substr($finalText, 0, 497).'...' : $finalText,
                        'input_tokens' => $totalInputTokens,
                        'output_tokens' => $totalOutputTokens,
                    ]);

                    return [
                        'status' => 'completed',
                        'text' => $finalText,
                    ];
                }

                $toolResults = [];
                foreach ($toolUses as $toolUse) {
                    $toolName = $toolUse['name'] ?? '';
                    $toolId = $toolUse['id'] ?? '';
                    $args = is_array($toolUse['input'] ?? null) ? $toolUse['input'] : [];

                    $result = $this->toolGateway->call($toolName, $context, $args, $turnSecurityContext);

                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolId,
                        'content' => $this->formatToolResultForLlm($result),
                    ];

                    if ($result->success && isset($result->data['pending_approval']) && $result->data['pending_approval'] === true) {
                        $turn->update([
                            'status' => RuntimeTurnStatus::Pending,
                            'input_tokens' => $totalInputTokens,
                            'output_tokens' => $totalOutputTokens,
                        ]);

                        return [
                            'status' => 'pending_approval',
                            'tool_call_id' => $result->data['tool_call_id'] ?? null,
                        ];
                    }
                }

                $assistantContent = $content;
                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
                $messages[] = [
                    'role' => 'user',
                    'content' => array_map(fn ($r) => [
                        'type' => 'tool_result',
                        'tool_use_id' => $r['tool_use_id'],
                        'content' => $r['content'],
                    ], $toolResults),
                ];
            }

            $turn->update([
                'status' => RuntimeTurnStatus::Failed,
                'summary' => 'Max tool iterations ('.self::MAX_TOOL_ITERATIONS.') exceeded',
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
            ]);

            return [
                'status' => 'failed',
                'error' => 'Max tool iterations exceeded',
            ];
        } catch (\Throwable $e) {
            Log::channel('runtime')->error('MessengerRuntimeOrchestrator: turn failed', [
                'turn_id' => $turn->id,
                'error' => $e->getMessage(),
            ]);

            $turn->update([
                'status' => RuntimeTurnStatus::Failed,
                'summary' => $e->getMessage(),
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: 'completed'|'failed', text?: string, runner_session_id?: string, error?: string}
     */
    private function executeViaWrapper(RuntimeSession $session, string $userMessage, ?string $runnerTypeOverride = null, ?string $systemPrompt = null, ApprovalMode $approvalMode = ApprovalMode::Autonomous, ?\Closure $onProgress = null): array
    {
        $user = $session->user;
        if ($user === null) {
            return ['status' => 'failed', 'error' => 'Runtime session has no user.'];
        }

        $apiKey = $this->credentialsManager->get($user, 'anthropic', 'api_key');
        if ($apiKey === null || $apiKey === '') {
            return ['status' => 'failed', 'error' => 'Anthropic API key not found.'];
        }

        $runnerType = $runnerTypeOverride ?? config('runtime.cli.runner_type', 'claude');
        $executables = config('agent.runner_executables', []);
        $executable = $executables[$runnerType] ?? null;

        if ($executable === null) {
            return ['status' => 'failed', 'error' => "Runner executable not configured for '{$runnerType}'."];
        }

        $workingDir = $session->workspace_root !== null && $session->workspace_root !== ''
            ? $session->workspace_root
            : (config('agent.allowed_working_directory_bases', [])[0] ?? base_path());

        if (! $this->sessionProcessManager->hasActiveWrapper($session->id)) {
            $started = $this->sessionProcessManager->startWrapper(
                runtimeSessionId: $session->id,
                runnerExecutable: $executable,
                runnerType: $runnerType,
                workingDirectory: $workingDir,
                apiKey: $apiKey,
                systemPrompt: $systemPrompt,
                approvalMode: $approvalMode,
            );

            if (! $started) {
                return ['status' => 'failed', 'error' => 'Failed to start wrapper process.'];
            }
        }

        $timeout = (int) config('runtime.cli.timeout_seconds', 1800);

        return $this->sessionProcessManager->sendMessage($session->id, $userMessage, $timeout, $onProgress);
    }

    private function getPolicyForSession(RuntimeSession $session): array
    {
        $snapshot = $session->policySnapshots()->orderByDesc('captured_at')->first();

        return $snapshot?->policy_json ?? [];
    }

    private function buildSystemPrompt(RuntimeContext $context): string
    {
        $parts = [
            'You are an assistant with access to tools. Use them when needed to fulfill the user request.',
            'Current mode: '.$context->mode->value.'.',
            self::browserInstructions(),
        ];

        if ($context->workspaceRoot !== null && $context->workspaceRoot !== '') {
            $parts[] = 'Workspace root: '.$context->workspaceRoot.'. All file paths must be within this directory.';
        }

        return implode("\n", $parts);
    }

    /**
     * Browser tool instructions shared between in-app LLM and CLI system prompts.
     */
    public static function browserInstructions(): string
    {
        return implode("\n", [
            'You have a "browser" tool powered by agent-browser. Use it when the user asks to visit a website, interact with a page, or use the browser. Prefer the browser tool over WebFetch for interactive tasks.',
            '',
            'Browser workflow: Use the browser tool with a command string.',
            '1. open <url> — navigate to a URL',
            '2. snapshot -i — get interactive elements with @ref IDs (e.g. @e1, @e2)',
            '3. Interact using @refs: click @e2, fill @e3 "text", press Enter',
            '4. screenshot — verify the result visually',
            '',
            'Key commands:',
            '- open <url>, back, forward, reload — navigation',
            '- click <sel>, fill <sel> <text>, type <sel> <text>, press <key> — interaction',
            '- check <sel>, uncheck <sel>, select <sel> <value>, hover <sel> — forms',
            '- snapshot [-i] [-c], screenshot [path] [--full] [--annotate] — inspection',
            '- get text|url|title [sel], is visible|enabled <sel> — page info',
            '- wait <sel|ms>, wait --load networkidle — wait for content',
            '- scroll up|down [px], scrollintoview <sel> — scrolling',
            '- eval <js> — run JavaScript',
            '- auth save <name> --url <url> --username <u> --password <p>, auth login <name> — credential vault',
            '- tab new|list|close|<n> — tab management',
            '',
            'Selectors: Use CSS selectors or @ref IDs from snapshot output.',
        ]);
    }

    /**
     * @param  array<int, array{type?: string, id?: string, name?: string, input?: mixed}>  $content
     * @return array<int, array{id: string, name: string, input: array}>
     */
    private function extractToolUses(array $content): array
    {
        $uses = [];
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $uses[] = [
                    'id' => $block['id'] ?? '',
                    'name' => $block['name'] ?? '',
                    'input' => is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return $uses;
    }

    /**
     * @param  array<int, array{type?: string, text?: string}>  $content
     * @return array<int, string>
     */
    private function extractTextBlocks(array $content): array
    {
        $texts = [];
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $texts[] = $block['text'];
            }
        }

        return $texts;
    }

    private function formatToolResultForLlm(ToolResult $result): string
    {
        if ($result->success) {
            return json_encode($result->data);
        }

        return 'Error: '.$result->error;
    }

    private const SESSION_TITLE_MAX_LENGTH = 80;

    private function deriveSessionTitle(string $userMessage): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $userMessage));
        if ($trimmed === '') {
            return 'New session';
        }

        if (strlen($trimmed) <= self::SESSION_TITLE_MAX_LENGTH) {
            return $trimmed;
        }

        return substr($trimmed, 0, self::SESSION_TITLE_MAX_LENGTH - 3).'...';
    }
}
