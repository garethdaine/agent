<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\User;
use App\Services\Messenger\CommandRouter;

final class HelpCommandHandler implements SlashCommandHandlerInterface
{
    public function __construct(
        private CommandRouter $router,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $commands = $this->router->getAvailableCommands();
        $list = implode(', ', array_map(fn (string $c) => "/{$c}", $commands));

        return CommandResult::success(
            "**Commands:** {$list}\n\nUse /commands for details."
        );
    }
}
