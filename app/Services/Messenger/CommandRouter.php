<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Messenger\SlashCommands\ApproveCommandHandler;
use App\Messenger\SlashCommands\AskCommandHandler;
use App\Messenger\SlashCommands\BrowserCommandHandler;
use App\Messenger\SlashCommands\CommandsCommandHandler;
use App\Messenger\SlashCommands\CompactCommandHandler;
use App\Messenger\SlashCommands\ConnectorCommandHandler;
use App\Messenger\SlashCommands\ContextCommandHandler;
use App\Messenger\SlashCommands\DenyCommandHandler;
use App\Messenger\SlashCommands\HelpCommandHandler;
use App\Messenger\SlashCommands\JobsCommandHandler;
use App\Messenger\SlashCommands\ModeCommandHandler;
use App\Messenger\SlashCommands\NewCommandHandler;
use App\Messenger\SlashCommands\ProgressCommandHandler;
use App\Messenger\SlashCommands\RunsCommandHandler;
use App\Messenger\SlashCommands\SkillsCommandHandler;
use App\Messenger\SlashCommands\SessionsCommandHandler;
use App\Messenger\SlashCommands\StatusCommandHandler;
use App\Messenger\SlashCommands\SubAgentsCommandHandler;
use App\Messenger\SlashCommands\WhoamiCommandHandler;
use App\Models\User;

/**
 * Router for slash commands.
 *
 * Handles all slash commands with deterministic behavior.
 * Commands are routed to their respective handlers.
 * Takes precedence over AgentRouter for free-form prompts.
 *
 * Supported commands:
 * - /jobs - Manage agent jobs (list, show, create, delete, run, enable, disable)
 * - /runs - Manage agent job runs (active, history, show, stop, retry, steer)
 * - /status - Return current runtime/session/tool state
 * - /sessions - Manage runtime sessions (list, stop)
 * - /mode [safe|standard|full] - View or change execution mode
 * - /approve <id> - Approve pending tool call
 * - /deny <id> [reason] - Deny pending tool call
 * - /browser - Browser sidecar (start, stop, reset, status)
 * - /ask <question> - General question or task
 * - /context [list|detail|json] - Context usage (messages, tokens)
 */
final class CommandRouter
{
    /**
     * @var array<string, class-string<SlashCommandHandlerInterface>>
     */
    private array $handlers = [
        'jobs' => JobsCommandHandler::class,
        'runs' => RunsCommandHandler::class,
        'status' => StatusCommandHandler::class,
        'sessions' => SessionsCommandHandler::class,
        'mode' => ModeCommandHandler::class,
        'approve' => ApproveCommandHandler::class,
        'deny' => DenyCommandHandler::class,
        'browser' => BrowserCommandHandler::class,
        'ask' => AskCommandHandler::class,
        'context' => ContextCommandHandler::class,
        'new' => NewCommandHandler::class,
        'help' => HelpCommandHandler::class,
        'commands' => CommandsCommandHandler::class,
        'whoami' => WhoamiCommandHandler::class,
        'compact' => CompactCommandHandler::class,
        'subagents' => SubAgentsCommandHandler::class,
        'progress' => ProgressCommandHandler::class,
        'skills' => SkillsCommandHandler::class,
        'connector' => ConnectorCommandHandler::class,
    ];

    /**
     * Check if the content is a slash command.
     */
    public function isCommand(string $content): bool
    {
        $trimmed = trim($content);

        return $trimmed !== '' && str_starts_with($trimmed, '/');
    }

    /**
     * Parse a command string into name and arguments.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public function parseCommand(string $content): array
    {
        $trimmed = trim($content);

        // Split on first space to separate command from args
        $parts = preg_split('/\s+/', $trimmed, 2);

        // Extract command name (remove leading slash)
        $name = ltrim($parts[0] ?? '', '/');

        // Parse arguments if present
        $args = [];
        if (isset($parts[1]) && $parts[1] !== '') {
            $args = preg_split('/\s+/', $parts[1]) ?: [];
        }

        return [$name, $args];
    }

    /**
     * Route and execute a command.
     *
     * Returns null if the content is not a command.
     */
    public function route(string $content, User $user, ?string $chatSessionId = null, ?string $connectorAccountId = null): ?CommandResult
    {
        if (! $this->isCommand($content)) {
            return null;
        }

        [$name, $args] = $this->parseCommand($content);

        if (! isset($this->handlers[$name])) {
            return CommandResult::failure("Unknown command: /{$name}");
        }

        /** @var SlashCommandHandlerInterface $handler */
        $handler = app($this->handlers[$name]);

        return $handler->handle($user, $args, $chatSessionId, $connectorAccountId);
    }

    /**
     * Get available command names.
     *
     * @return array<string>
     */
    public function getAvailableCommands(): array
    {
        return array_keys($this->handlers);
    }
}
