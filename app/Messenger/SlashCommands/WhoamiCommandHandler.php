<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\ConnectorAccount;
use App\Models\User;

final class WhoamiCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $lines = [
            "User ID: {$user->id}",
            'Email: '.($user->email ?? '(none)'),
        ];
        if ($connectorAccountId !== null && $connectorAccountId !== '') {
            $account = ConnectorAccount::find($connectorAccountId);
            if ($account !== null) {
                $lines[] = "Connector: {$account->provider}";
                $externalId = $account->external_id ?? '(none)';
                $lines[] = "Connector account ID: {$account->id}";
                $lines[] = "External ID: {$externalId}";
            }
        }

        return CommandResult::success(implode("\n", $lines));
    }
}
