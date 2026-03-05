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
 * Handler for the /approve slash command.
 *
 * Approves a pending tool call by ID.
 *
 * Usage:
 * - /approve <id> - Approve the specified tool call
 */
final class ApproveCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /approve <tool_call_id>');
        }

        $toolCallId = $args[0];

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
            'status' => RuntimeToolCallStatus::Approved,
            'approved_at' => now(),
        ]);

        // Update approval record if exists
        if ($toolCall->approval !== null) {
            $toolCall->approval->update([
                'state' => RuntimeApprovalState::Approved,
                'decision_by' => $user->id,
            ]);
        }

        $shortId = substr($toolCall->id, 0, 8);

        return CommandResult::success(
            "Approved tool call [{$shortId}] {$toolCall->tool_name}",
            [
                'tool_call_id' => $toolCall->id,
                'tool_name' => $toolCall->tool_name,
                'approved_at' => now()->toIso8601String(),
            ]
        );
    }
}
