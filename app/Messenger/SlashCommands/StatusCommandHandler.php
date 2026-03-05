<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;

/**
 * Handler for the /status slash command.
 *
 * Returns current runtime/session/tool state for the user.
 */
final class StatusCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        // Count active sessions for this user
        $activeSessions = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->count();

        if ($activeSessions === 0) {
            return CommandResult::success(
                'No active runtime sessions',
                ['active_sessions' => 0, 'pending_approvals' => 0]
            );
        }

        // Count pending approvals across user's active sessions
        $pendingApprovals = RuntimeToolCall::whereHas('turn.session', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', RuntimeSessionStatus::Active);
        })
            ->where('status', RuntimeToolCallStatus::PendingApproval)
            ->count();

        $message = "Active sessions: {$activeSessions}";
        if ($pendingApprovals > 0) {
            $message .= "\nPending approvals: {$pendingApprovals}";
        }

        return CommandResult::success(
            $message,
            [
                'active_sessions' => $activeSessions,
                'pending_approvals' => $pendingApprovals,
            ]
        );
    }
}
