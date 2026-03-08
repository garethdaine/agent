<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Connectors\ConnectorRegistryLoader;
use Illuminate\Console\Command;

class ConnectorSyncCommand extends Command
{
    protected $signature = 'connector:sync {--path= : Path to connector manifests directory}';

    protected $description = 'Sync connector manifests from disk to the database';

    public function handle(ConnectorRegistryLoader $loader): int
    {
        $path = $this->option('path');

        $this->info('Syncing connector registry...');

        $result = $loader->sync($path);

        $this->info("Sync complete: {$result->created} created, {$result->updated} updated, {$result->skipped} skipped, {$result->deprecated} deprecated, {$result->errors} errors.");

        return self::SUCCESS;
    }
}
