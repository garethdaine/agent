<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnector;
use App\Models\Team;
use App\Models\User;
use App\Services\Connectors\ConnectionLifecycleService;
use Illuminate\Console\Command;

class ConnectorConnectCommand extends Command
{
    protected $signature = 'connector:connect
        {name : The connector name}
        {--api-key= : API key for authentication}
        {--api-secret= : API secret for authentication}
        {--team= : Team ID to connect for}';

    protected $description = 'Connect a team to an external connector';

    public function handle(ConnectionLifecycleService $service): int
    {
        $connector = AgentConnector::where('name', $this->argument('name'))
            ->where('status', AgentConnector::STATUS_AVAILABLE)
            ->first();

        if (! $connector) {
            $this->error("Connector '{$this->argument('name')}' not found.");

            return self::FAILURE;
        }

        $team = Team::find($this->option('team'));
        if (! $team) {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        $user = $team->users()->first();
        if (! $user) {
            $this->error('No users found for this team.');

            return self::FAILURE;
        }

        $authParams = null;
        if ($this->option('api-key')) {
            $authParams = [
                'api_key' => $this->option('api-key'),
                'api_secret' => $this->option('api-secret'),
            ];
        }

        try {
            $result = $service->connect($connector, $team, $user, $authParams);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->connected) {
            $this->info("Connected to '{$connector->display_name}' successfully.");
        } else {
            $this->info('Please visit the following URL to authorize:');
            $this->line($result->authorizationUrl);
        }

        return self::SUCCESS;
    }
}
