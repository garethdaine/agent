<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Agent\LicenseService;
use Illuminate\Console\Command;

class AgentUpdateCommand extends Command
{
    protected $signature = 'agent:update';

    protected $description = 'Run post-update tasks (migrations, cache clear, license verification)';

    public function handle(LicenseService $license): int
    {
        $version = (string) config('agent.version', 'unknown');
        $this->components->info("Agent Ops v{$version} — running post-update tasks...");

        $this->components->task('Clearing caches', function () {
            $this->callSilently('config:clear');
            $this->callSilently('route:clear');
            $this->callSilently('view:clear');

            return true;
        });

        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate', ['--force' => true]);

            return true;
        });

        $this->components->task('Verifying license', function () use ($license) {
            $license->clearCache();
            $status = $license->validate();

            if (! $status->valid) {
                $this->newLine();
                $this->components->warn("License validation failed: {$status->error}");
            }

            return true;
        });

        if (file_exists(base_path('CHANGELOG.md'))) {
            $this->newLine();
            $this->components->info('See CHANGELOG.md for details on this release.');
        }

        $this->newLine();
        $this->components->info('Update complete.');

        return self::SUCCESS;
    }
}
