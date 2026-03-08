<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\SubAgentSpawner;

final class SubAgentsCommandHandler implements SlashCommandHandlerInterface
{
    public function __construct(
        private SubAgentSpawner $spawner,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $subcommand = $args[0] ?? 'list';

        return match ($subcommand) {
            'list' => $this->listSubAgents($user, $chatSessionId),
            'spawn' => $this->spawnSubAgent($user, array_slice($args, 1), $chatSessionId),
            'kill' => $this->killSubAgent($user, $args[1] ?? null),
            default => CommandResult::failure("Unknown subcommand: {$subcommand}. Usage: /subagents [list|spawn <task>|kill <id>]"),
        };
    }

    private function listSubAgents(User $user, ?string $chatSessionId): CommandResult
    {
        $parentSession = $this->resolveActiveParentSession($user, $chatSessionId);

        if ($parentSession === null) {
            return CommandResult::success('No active session — no sub-agents to list.');
        }

        $children = RuntimeSession::where('parent_session_id', $parentSession->id)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        if ($children->isEmpty()) {
            return CommandResult::success('No sub-agents for the current session.');
        }

        $lines = $children->map(function (RuntimeSession $child) {
            $shortId = substr($child->id, 0, 8);
            $title = $child->title ?? 'Untitled';
            $status = $child->status->value; // @phpstan-ignore property.nonObject
            $duration = $child->started_at
                ? now()->diffForHumans($child->started_at, true) // @phpstan-ignore argument.type
                : '—';

            return "• [{$shortId}] **{$title}** — {$status} ({$duration})";
        });

        return CommandResult::success(
            "**Sub-agents** ({$children->count()}):\n".implode("\n", $lines->all()),
            ['count' => $children->count()]
        );
    }

    private function spawnSubAgent(User $user, array $taskArgs, ?string $chatSessionId): CommandResult
    {
        if ($taskArgs === []) {
            return CommandResult::failure('Usage: /subagents spawn <task description>');
        }

        $task = implode(' ', $taskArgs);
        $parentSession = $this->resolveActiveParentSession($user, $chatSessionId);

        if ($parentSession === null) {
            return CommandResult::failure('No active session to spawn a sub-agent from.');
        }

        $result = $this->spawner->spawn($parentSession, $task);

        if ($result['status'] === 'rejected') {
            return CommandResult::failure($result['reason']);
        }

        $shortId = substr($result['child_session_id'], 0, 8);

        return CommandResult::success(
            "Spawned sub-agent [{$shortId}] with task: {$task}",
            ['child_session_id' => $result['child_session_id']]
        );
    }

    private function killSubAgent(User $user, ?string $sessionId): CommandResult
    {
        if ($sessionId === null) {
            return CommandResult::failure('Usage: /subagents kill <ID>');
        }

        $query = RuntimeSession::where('user_id', $user->id)
            ->whereNotNull('parent_session_id')
            ->where('status', RuntimeSessionStatus::Active);

        $child = strlen($sessionId) === 36
            ? $query->where('id', $sessionId)->first()
            : $query->where('id', 'like', $sessionId.'%')->first();

        if ($child === null) {
            return CommandResult::failure("Sub-agent not found or not active: {$sessionId}");
        }

        $child->update([
            'status' => RuntimeSessionStatus::Stopped,
            'ended_at' => now(),
        ]);

        $shortId = substr($child->id, 0, 8);
        $title = $child->title ?? 'Untitled';

        return CommandResult::success("Stopped sub-agent [{$shortId}] {$title}");
    }

    private function resolveActiveParentSession(User $user, ?string $chatSessionId): ?RuntimeSession
    {
        $query = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->whereNull('parent_session_id');

        if ($chatSessionId !== null) {
            $query->where('chat_session_id', $chatSessionId);
        }

        return $query->orderByDesc('started_at')->first();
    }
}
