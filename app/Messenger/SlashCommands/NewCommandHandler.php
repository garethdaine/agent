<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Runtime\RuntimeSessionManager;

/**
 * Handler for the /new slash command.
 *
 * Stops the current runtime session for this chat (if any) and creates a new one.
 */
final class NewCommandHandler implements SlashCommandHandlerInterface
{
    public function __construct(
        private RuntimeSessionManager $sessionManager,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if ($chatSessionId === null || $chatSessionId === '') {
            return CommandResult::failure('No chat session. Use /new from a connected channel.');
        }

        $existing = $this->sessionManager->getActiveSessionForChat($user, $chatSessionId);

        if ($existing !== null) {
            $this->sessionManager->stopSession($existing);
        }

        $connector = $connectorAccountId !== null && $connectorAccountId !== ''
            ? ConnectorAccount::find($connectorAccountId)
            : null;

        $this->sessionManager->createSession($user, [
            'chat_session_id' => $chatSessionId,
        ], $connector);

        $message = $existing !== null
            ? 'Previous session stopped. New session started.'
            : 'New session started.';

        return CommandResult::success($message);
    }
}
