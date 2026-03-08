<?php

namespace App\Console\Commands;

use App\Models\AgentConnector;
use Illuminate\Console\Command;

class ConnectorListCommand extends Command
{
    protected $signature = 'connector:list {--category= : Filter by category} {--status= : Filter by status}';

    protected $description = 'List available connectors in the registry';

    public function handle(): int
    {
        $query = AgentConnector::query();

        if ($category = $this->option('category')) {
            $query->where('category', $category);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $connectors = $query->orderBy('name')->get();

        if ($connectors->isEmpty()) {
            $this->info('No connectors found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Display Name', 'Category', 'Version', 'Status'],
            $connectors->map(fn (AgentConnector $c) => [
                $c->name,
                $c->display_name,
                $c->category,
                $c->version,
                $c->status,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
