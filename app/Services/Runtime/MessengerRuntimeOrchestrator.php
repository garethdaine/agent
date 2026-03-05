<?php

namespace App\Services\Runtime;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeTurnStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use Illuminate\Support\Facades\Log;

class MessengerRuntimeOrchestrator
{
    private const MAX_TOOL_ITERATIONS = 10;

    public function __construct(
        private ToolGateway $toolGateway,
        private RuntimeLlmClient $llmClient,
        private CliRuntimeExecutor $cliExecutor,
    ) {}

    /**
     * Execute one turn. When runtime.use_cli is true, runs the CLI (normal runtime);
     * otherwise runs in-app LLM with tools. API key always from credential manager.
     *
     * @return array{status: 'completed'|'failed'|'pending_approval', text?: string, tool_call_id?: string, error?: string}
     */
    public function executeTurn(RuntimeSession $session, string $userMessage): array
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
            $result = $this->cliExecutor->executeTurn($session, $userMessage);
            $summary = $result['text'] ?? $result['error'] ?? null;
            $turn->update([
                'status' => $result['status'] === 'completed' ? RuntimeTurnStatus::Completed : RuntimeTurnStatus::Failed,
                'summary' => $summary !== null && strlen($summary) > 500 ? substr($summary, 0, 497).'...' : $summary,
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
                    $turn->update([
                        'status' => RuntimeTurnStatus::Completed,
                        'summary' => strlen($finalText) > 500 ? substr($finalText, 0, 497).'...' : $finalText,
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

                    $result = $this->toolGateway->call($toolName, $context, $args);

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
            Log::error('MessengerRuntimeOrchestrator: turn failed', [
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
            'You have a "browser" tool: use it to navigate to URLs, click, type, take screenshots, or extract content when the user asks you to check a website, open a page, or use the browser. Prefer the browser tool over WebFetch when the user explicitly asks to use the browser or to visit a site in a browser.',
        ];

        if ($context->workspaceRoot !== null && $context->workspaceRoot !== '') {
            $parts[] = 'Workspace root: '.$context->workspaceRoot.'. All file paths must be within this directory.';
        }

        return implode("\n", $parts);
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
