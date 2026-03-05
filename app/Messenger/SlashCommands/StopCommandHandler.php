<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;

/**
 * Handler for the /stop slash command.
 *
 * Terminates a runtime session by ID or the most recent active session.
 *
 * Usage:
 * - /stop [session_id] - Stop the specified session
 * - /stop - Stop the most recent active session
 */
final class StopCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $sessionId = $args[0] ?? null;

        if ($sessionId !== null) {
            return $this->stopSpecificSession($user, $sessionId);
        }

        return $this->stopMostRecentSession($user);
    }

    private function stopSpecificSession(User $user, string $sessionId): CommandResult
    {
        // Support both full UUID and short ID (first 8 chars)
        $query = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active);

        if (strlen($sessionId) === 36) {
            // Full UUID
            $session = $query->where('id', $sessionId)->first();
        } else {
            // Short ID - prefix match
            $session = $query->where('id', 'like', $sessionId.'%')->first();
        }

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
