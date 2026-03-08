<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Services\Connectors\ConnectionLifecycleService;
use Illuminate\Console\Command;

class ConnectorDisconnectCommand extends Command
{
    protected $signature = 'connector:disconnect
        {name : The connector name}
        {--team= : Team ID to disconnect}
        {--force : Skip confirmation}';

    protected $description = 'Disconnect a team from an external connector';

    public function handle(ConnectionLifecycleService $service): int
    {
        $connector = AgentConnector::where('name', $this->argument('name'))->first();

        if (! $connector) {
            $this->error("Connector '{$this->argument('name')}' not found.");

            return self::FAILURE;
        }

        $team = Team::find($this->option('team'));
        if (! $team) {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->where('status', AgentConnectorConnection::STATUS_CONNECTED)
            ->first();

        if (! $connection) {
            $this->error('No active connection found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Disconnect from '{$connector->display_name}'?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        /** @var \App\Models\User|null $user */
        $user = $team->users()->first();
        if (! $user) {
            $this->error('No users found for this team.');

            return self::FAILURE;
        }

        $service->disconnect($connection, $user);

        $this->info("Disconnected from '{$connector->display_name}'.");

        return self::SUCCESS;
    }
}
