<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\User;
use App\Support\Connectors\Messenger\ConnectorCommandHandler as ConnectorHandler;

/**
 * Handler for the /connector slash command.
 *
 * Routes connector subcommands to the ConnectorCommandHandler.
 *
 * Usage:
 * - /connector connect {service} - Initiate connection
 * - /connector list connections - Show connected services
 * - /connector test {service} - Run health check
 * - /connector disconnect {service} - Remove connection
 */
final class ConnectorCommandHandler implements SlashCommandHandlerInterface
{
    public function __construct(
        private readonly ConnectorHandler $handler,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure(
                "Usage: /connector {connect|list|test|disconnect} [service]\n\n"
                .'Commands:\n'
                ."- `/connector connect {service}` — Initiate connection\n"
                ."- `/connector list connections` — Show connected services\n"
                ."- `/connector test {service}` — Run health check\n"
                .'- `/connector disconnect {service}` — Remove connection'
            );
        }

        $subcommand = array_shift($args);

        return $this->handler->handleCommand($subcommand, $args, $user);
    }
}
