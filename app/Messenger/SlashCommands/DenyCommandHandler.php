<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeApprovalState;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;

/**
 * Handler for the /deny slash command.
 *
 * Denies a pending tool call by ID.
 *
 * Usage:
 * - /deny <id> [reason] - Deny the specified tool call with optional reason
 */
final class DenyCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /deny <tool_call_id> [reason]');
        }

        $toolCallId = $args[0];
        $reason = isset($args[1]) ? implode(' ', array_slice($args, 1)) : null;

        // Find tool call by ID (supports short ID or full UUID)
        $query = RuntimeToolCall::where('status', RuntimeToolCallStatus::PendingApproval)
            ->whereHas('turn.session', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('status', RuntimeSessionStatus::Active);
            });

        if (strlen($toolCallId) === 36) {
            $toolCall = $query->where('id', $toolCallId)->first();
        } else {
            $toolCall = $query->where('id', 'like', $toolCallId.'%')->first();
        }

        if ($toolCall === null) {
            return CommandResult::failure("Tool call not found or not pending approval: {$toolCallId}");
        }

        // Update tool call status
        $toolCall->update([
            'status' => RuntimeToolCallStatus::Denied,
        ]);

        // Update approval record if exists
        if ($toolCall->approval !== null) {
            $toolCall->approval->update([
                'state' => RuntimeApprovalState::Denied,
                'decision_by' => $user->id,
                'decision_reason' => $reason,
            ]);
        }

        $shortId = substr($toolCall->id, 0, 8);
        $message = "Denied tool call [{$shortId}] {$toolCall->tool_name}";
        if ($reason !== null) {
            $message .= " - Reason: {$reason}";
        }

        return CommandResult::success(
            $message,
            [
                'tool_call_id' => $toolCall->id,
                'tool_name' => $toolCall->tool_name,
                'reason' => $reason,
                'denied_at' => now()->toIso8601String(),
            ]
        );
    }
}
