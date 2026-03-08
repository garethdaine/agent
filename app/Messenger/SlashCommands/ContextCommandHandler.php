<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\ChatSession;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Messenger\ContextUsageEstimator;
use App\Services\Runtime\RuntimeSessionManager;

/**
 * Handler for the /context slash command.
 *
 * Reports context usage: chat message count, estimated tokens, runtime session tokens.
 * Supports list (default), detail, and json output.
 */
final class ContextCommandHandler implements SlashCommandHandlerInterface
{
    public function __construct(
        private ContextUsageEstimator $estimator,
        private RuntimeSessionManager $sessionManager,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $sub = strtolower(trim($args[0] ?? 'list'));
        if (! in_array($sub, ['list', 'detail', 'json'], true)) {
            $sub = 'list';
        }

        $chatStats = null;
        $runtimeSession = null;

        if ($chatSessionId !== null && $chatSessionId !== '') {
            $chatSession = ChatSession::where('id', $chatSessionId)->where('user_id', $user->id)->first();
            if ($chatSession !== null) {
                $chatStats = $this->estimator->estimate($chatSession);
                $runtimeSession = $this->sessionManager->getActiveSessionForChat($user, $chatSessionId);
            }
        }

        $activeSessions = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->get();

        $totalIn = $activeSessions->sum(fn ($s) => (int) ($s->total_input_tokens ?? 0));
        $totalOut = $activeSessions->sum(fn ($s) => (int) ($s->total_output_tokens ?? 0));

        $contextWindow = $this->estimator->getContextWindowSize();
        $runtimeSessionId = $runtimeSession?->id;
        $runtimeInput = $runtimeSession !== null ? (int) ($runtimeSession->total_input_tokens ?? 0) : null;
        $runtimeOutput = $runtimeSession !== null ? (int) ($runtimeSession->total_output_tokens ?? 0) : null;

        $data = [
            'message_count' => $chatStats['message_count'] ?? null,
            'estimated_chat_tokens' => $chatStats['estimated_tokens'] ?? null,
            'runtime_session_id' => $runtimeSessionId,
            'runtime_input_tokens' => $runtimeInput,
            'runtime_output_tokens' => $runtimeOutput,
            'user_active_sessions' => $activeSessions->count(),
            'user_total_input_tokens' => $totalIn,
            'user_total_output_tokens' => $totalOut,
            'context_window_size' => $contextWindow,
        ];

        if ($sub === 'json') {
            return CommandResult::success(
                json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                $data
            );
        }

        $lines = [];
        if ($chatStats !== null) {
            $lines[] = "Chat: {$chatStats['message_count']} messages, ~{$chatStats['estimated_tokens']} tokens";
            if ($runtimeSession !== null) {
                $lines[] = "This session: in {$runtimeInput}, out {$runtimeOutput}";
                if ($contextWindow > 0) {
                    $used = $runtimeInput + $runtimeOutput;
                    $pct = round($used / $contextWindow * 100, 1);
                    $lines[] = "Context window: ~{$pct}% of {$contextWindow}";
                }
            }
        }
        if ($lines === []) {
            $lines[] = "Active runtime sessions: {$activeSessions->count()}";
            $lines[] = "Total tokens: in {$totalIn}, out {$totalOut}";
        }

        if ($sub === 'detail' && $runtimeSession !== null) {
            $turns = $runtimeSession->turns()->orderByDesc('sequence')->limit(5)->get();
            foreach ($turns as $turn) {
                $in = (int) ($turn->input_tokens ?? 0);
                $out = (int) ($turn->output_tokens ?? 0);
                $summary = $turn->summary !== null ? substr((string) $turn->summary, 0, 60).'...' : '-'; // @phpstan-ignore property.notFound
                $lines[] = "Turn #{$turn->sequence}: in {$in}, out {$out} — {$summary}"; // @phpstan-ignore property.notFound
            }
        }

        return CommandResult::success(
            implode("\n", $lines),
            $data
        );
    }
}
