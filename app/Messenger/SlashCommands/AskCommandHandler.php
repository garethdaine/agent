<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\Handlers\GeneralTaskHandler;
use App\Models\User;

final class AskCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args  Question words (joined to form the question)
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $question = trim(implode(' ', $args));
        if ($question === '') {
            return CommandResult::failure('Usage: /ask <question>');
        }

        $context = new ChatActionContext(
            user: $user,
            parameters: ['task_description' => $question],
            action: 'general.task',
        );

        $handler = app(GeneralTaskHandler::class);
        $result = $handler->handle($context);

        if ($result->isSuccess()) {
            return CommandResult::success($result->getMessage(), $result->getData());
        }

        return CommandResult::failure($result->getMessage());
    }
}
