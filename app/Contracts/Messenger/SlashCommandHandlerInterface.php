<?php

declare(strict_types=1);

namespace App\Contracts\Messenger;

use App\DTOs\Messenger\CommandResult;
use App\Models\User;

/**
 * Interface for slash command handlers.
 *
 * All slash command handlers must implement this interface to ensure
 * consistent behavior across the command routing system.
 */
interface SlashCommandHandlerInterface
{
    /**
     * Handle the slash command execution.
     *
     * @param  User  $user  The user executing the command
     * @param  array<int, string>  $args  Command arguments (parsed from command string)
     * @param  string|null  $chatSessionId  Current chat session id (when invoked from messenger)
     * @param  string|null  $connectorAccountId  Connector account id (when invoked from messenger)
     * @return CommandResult The result of command execution
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult;
}
