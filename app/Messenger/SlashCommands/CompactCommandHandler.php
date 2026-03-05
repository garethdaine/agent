<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Jobs\Messenger\CompactionJob;
use App\Models\ChatSession;
use App\Models\User;

/**
 * Handler for the /compact slash command.
 * Dispatches compaction for the current chat session (async).
 */
final class CompactCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if ($chatSessionId === null || $chatSessionId === '') {
            return CommandResult::failure('No chat session. Use /compact from a connected channel.');
        }

        $session = ChatSession::where('id', $chatSessionId)->where('user_id', $user->id)->first();
        if ($session === null) {
            return CommandResult::failure('Chat session not found.');
        }

        $instructions = $args !== [] ? trim(implode(' ', $args)) : null;
        if ($instructions !== null && $instructions === '') {
            $instructions = null;
        }

        CompactionJob::dispatch($session->id, $instructions);

        return CommandResult::success(
            'Compaction queued. Older messages will be summarized shortly.'
        );
    }
}
