<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ConnectorAccount;
use App\Services\Messenger\SlashCommandRegistrar;
use Illuminate\Console\Command;

class MessengerSyncDiscordCommandsCommand extends Command
{
    protected $signature = 'messenger:sync-discord-commands
                            {--account= : Sync only this connector account ID (optional)}';

    protected $description = 'Register or update Discord slash commands for Discord connector(s)';

    public function handle(SlashCommandRegistrar $registrar): int
    {
        $accountId = $this->option('account');

        $accounts = $accountId !== null
            ? ConnectorAccount::where('provider', ConnectorAccount::PROVIDER_DISCORD)->whereKey($accountId)->get()
            : ConnectorAccount::where('provider', ConnectorAccount::PROVIDER_DISCORD)->get();

        if ($accounts->isEmpty()) {
            $this->warn($accountId !== null
                ? "No Discord connector found with ID: {$accountId}"
                : 'No Discord connectors found.');

            return self::FAILURE;
        }

        $synced = 0;
        foreach ($accounts as $account) {
            $result = $registrar->register($account);
            if ($result->isSuccessful()) {
                $this->info(sprintf(
                    '[%s] Synced %d slash commands: /%s',
                    $account->name ?? $account->id,
                    $result->getCommandCount(),
                    implode(', /', $result->getCommandNames() ?: ['(none)'])
                ));
                $synced++;
            } else {
                $this->error(sprintf('[%s] %s', $account->name ?? $account->id, $result->getMessage()));
            }
        }

        if ($synced > 0) {
            $this->info('Discord will show the updated commands when you type "/" in a channel where the bot is present.');
        }

        return $synced === $accounts->count() ? self::SUCCESS : self::FAILURE;
    }
}
