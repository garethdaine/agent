<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use Illuminate\Database\Eloquent\Collection;

class ListAvailableConnectorsAction
{
    /**
     * @param  array{category?: string, industry?: string, status?: string, team_id?: string}  $filters
     * @return Collection<int, AgentConnector>
     */
    public function execute(array $filters = []): Collection
    {
        $query = AgentConnector::query()->available();

        if (! empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (! empty($filters['industry'])) {
            $query->whereJsonContains('industries', $filters['industry']);
        }

        $connectionStatuses = ['connected', 'pending', 'degraded', 'disconnected', 'error'];
        $status = $filters['status'] ?? null;

        if ($status && in_array($status, $connectionStatuses, true)) {
            $teamId = $filters['team_id'] ?? null;

            if ($teamId) {
                $connectorIds = AgentConnectorConnection::where('team_id', $teamId)
                    ->where('status', $status)
                    ->pluck('agent_connector_id');

                $query->whereIn('id', $connectorIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->get();
    }
}
