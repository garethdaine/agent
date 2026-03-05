<?php

namespace App\Console\Commands;

use App\Models\ConnectorAccount;
use Illuminate\Console\Command;

class AgentConnectorsPruneCommand extends Command
{
    protected $signature = 'agent:connectors:prune
        {--dry-run : List soft-deleted connector accounts without deleting}';

    protected $description = 'Permanently delete all soft-deleted connector accounts';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $trashed = ConnectorAccount::onlyTrashed()->get();

        if ($trashed->isEmpty()) {
            $this->info('No soft-deleted connector accounts to prune.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn(sprintf('Dry run: would force-delete %d connector account(s).', $trashed->count()));
            $this->table(
                ['ID', 'Provider', 'Name', 'Deleted at'],
                $trashed->map(fn (ConnectorAccount $c): array => [
                    $c->id,
                    $c->provider,
                    $c->name,
                    $c->deleted_at?->toIso8601String() ?? '-',
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($trashed as $connector) {
            $connector->forceDelete();
            $count++;
        }

        $this->info(sprintf('Force-deleted %d soft-deleted connector account(s).', $count));

        return self::SUCCESS;
    }
}
