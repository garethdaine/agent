<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\User;

final class CommandsCommandHandler implements SlashCommandHandlerInterface
{
    private const DESCRIPTIONS = [
        'jobs' => 'list, show, create, delete, run, enable, disable',
        'runs' => 'active, history, show, stop, retry, steer',
        'status' => 'overall system status',
        'sessions' => 'list, stop (runtime sessions)',
        'mode' => 'safe | standard | full',
        'approve' => 'approve pending tool call',
        'deny' => 'deny pending tool call',
        'browser' => 'start, stop, reset, status',
        'ask' => 'ask a question or run a task',
        'context' => 'list | detail | json (context usage)',
        'new' => 'start new runtime session',
        'help' => 'list commands',
        'commands' => 'detailed command list',
        'whoami' => 'your user and connector id',
        'compact' => 'run compaction (optional instructions)',
    ];

    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $lines = ['**Slash commands:**'];
        foreach (self::DESCRIPTIONS as $cmd => $desc) {
            $lines[] = "• /{$cmd} — {$desc}";
        }

        return CommandResult::success(implode("\n", $lines));
    }
}
