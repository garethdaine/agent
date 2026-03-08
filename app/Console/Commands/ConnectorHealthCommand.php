<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnectorConnection;
use App\Models\Team;
use Illuminate\Console\Command;

class ConnectorHealthCommand extends Command
{
    protected $signature = 'connector:health
        {--team= : Filter by team ID}';

    protected $description = 'Show health status of all connector connections';

    public function handle(): int
    {
        $query = AgentConnectorConnection::with('connector')
            ->whereIn('status', [
                AgentConnectorConnection::STATUS_CONNECTED,
                AgentConnectorConnection::STATUS_DEGRADED,
                AgentConnectorConnection::STATUS_ERROR,
            ]);

        if ($teamId = $this->option('team')) {
            $team = Team::find($teamId);
            if (! $team) {
                $this->error('Team not found.');

                return self::FAILURE;
            }
            $query->where('team_id', $teamId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->info('No active connections found.');

            return self::SUCCESS;
        }

        $rows = $connections->map(function (AgentConnectorConnection $connection) {
            $healthScore = (float) $connection->health_score;
            $status = match (true) {
                $healthScore >= 0.8 => 'healthy',
                $healthScore >= 0.5 => 'degraded',
                default => 'unhealthy',
            };

            return [
                $connection->connector?->display_name ?? $connection->connector?->name ?? 'Unknown',
                $connection->status,
                number_format($healthScore, 2),
                $status,
                $connection->last_health_check_at?->diffForHumans() ?? 'Never',
                $connection->action_count_24h ?? 0,
                $connection->error_count_24h ?? 0,
            ];
        })->toArray();

        $this->table(
            ['Connector', 'Status', 'Health Score', 'Health', 'Last Check', 'Actions (24h)', 'Errors (24h)'],
            $rows
        );

        return self::SUCCESS;
    }
}
