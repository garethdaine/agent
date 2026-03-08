<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Services\Connectors\ConnectionLifecycleService;
use Illuminate\Console\Command;

class ConnectorTestCommand extends Command
{
    protected $signature = 'connector:test
        {name : The connector name}
        {--team= : Team ID to test}';

    protected $description = 'Test the health of a connector connection';

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

        $result = $service->testConnection($connection);

        if ($result->connected) {
            $this->info("Connected — latency: {$result->latencyMs}ms");
        } else {
            $this->error("Disconnected — error: {$result->error}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
