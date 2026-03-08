<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;

/**
 * Handler for the /sessions slash command.
 *
 * Lists and stops runtime sessions (interactive agent sessions).
 *
 * Usage:
 * - /sessions list - List active runtime sessions
 * - /sessions stop [session_id] - Stop a session (or most recent)
 */
final class SessionsCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args  [subcommand, ...option values]
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $subcommand = $args[0] ?? null;
        if ($subcommand === null || $subcommand === '') {
            return $this->usage();
        }

        return match (strtolower($subcommand)) {
            'list' => $this->listSessions($user),
            'stop' => $this->stopSession($user, $args[1] ?? null),
            default => $this->usage(),
        };
    }

    private function usage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /sessions <list|stop> [session_id]\n".
            "  list - List active runtime sessions\n".
            '  stop [session_id] - Stop a session (omit to stop most recent)'
        );
    }

    private function listSessions(User $user): CommandResult
    {
        $sessions = RuntimeSession::where('user_id', $user->id)
            ->whereIn('status', [RuntimeSessionStatus::Active, RuntimeSessionStatus::Pending])
            ->orderBy('started_at', 'desc')
            ->get(['id', 'title', 'mode', 'status', 'started_at']);

        if ($sessions->isEmpty()) {
            return CommandResult::success('No active runtime sessions', ['sessions' => []]);
        }

        $lines = ['Active runtime sessions:'];
        $sessionData = [];
        foreach ($sessions as $session) {
            $title = $session->title ?? 'Untitled';
            $mode = $session->mode->value; // @phpstan-ignore property.nonObject
            $status = $session->status->value; // @phpstan-ignore property.nonObject
            $shortId = substr($session->id, 0, 8);
            $lines[] = "• [{$shortId}] {$title} ({$mode}, {$status})";
            $sessionData[] = [
                'id' => $session->id,
                'short_id' => $shortId,
                'title' => $title,
                'mode' => $mode,
                'status' => $status,
                'started_at' => $session->started_at?->toIso8601String(), // @phpstan-ignore method.nonObject
            ];
        }

        return CommandResult::success(implode("\n", $lines), ['sessions' => $sessionData]);
    }

    private function stopSession(User $user, ?string $sessionId): CommandResult
    {
        if ($sessionId !== null && $sessionId !== '') {
            return $this->stopSpecificSession($user, $sessionId);
        }

        return $this->stopMostRecentSession($user);
    }

    private function stopSpecificSession(User $user, string $sessionId): CommandResult
    {
        $query = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active);

        $session = strlen($sessionId) === 36
            ? $query->where('id', $sessionId)->first()
            : $query->where('id', 'like', $sessionId.'%')->first();

        if ($session === null) {
            return CommandResult::failure("Session not found or not active: {$sessionId}");
        }

        $session->update([
            'status' => RuntimeSessionStatus::Stopped,
            'ended_at' => now(),
        ]);

        $shortId = substr($session->id, 0, 8);
        $title = $session->title ?? 'Untitled';

        return CommandResult::success(
            "Stopped session [{$shortId}] {$title}",
            ['session_id' => $session->id, 'stopped_at' => now()->toIso8601String()]
        );
    }

    private function stopMostRecentSession(User $user): CommandResult
    {
        $session = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->orderBy('started_at', 'desc')
            ->first();

        if ($session === null) {
            return CommandResult::failure('No active session to stop');
        }

        $session->update([
            'status' => RuntimeSessionStatus::Stopped,
            'ended_at' => now(),
        ]);

        $shortId = substr($session->id, 0, 8);
        $title = $session->title ?? 'Untitled';

        return CommandResult::success(
            "Stopped session [{$shortId}] {$title}",
            ['session_id' => $session->id, 'stopped_at' => now()->toIso8601String()]
        );
    }
}
