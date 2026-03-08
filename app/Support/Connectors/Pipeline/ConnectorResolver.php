<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Models\AgentConnectorConnection;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\ConnectorNotConnectedException;
use App\Support\Connectors\Exceptions\ConnectorUnhealthyException;
use Closure;

class ConnectorResolver
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        if (! config('connectors.enabled')) {
            throw new ConnectorNotConnectedException(
                $request->connector->name,
                'Connectors feature is disabled.'
            );
        }

        if ($request->connection->status !== AgentConnectorConnection::STATUS_CONNECTED) {
            throw new ConnectorNotConnectedException($request->connector->name);
        }

        $healthScore = (float) $request->connection->health_score;
        $threshold = config('connectors.health.thresholds.degraded', 0.5);

        if ($healthScore < $threshold) {
            throw new ConnectorUnhealthyException($request->connector->name, $healthScore);
        }

        return $next($request);
    }
}
