<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorInvocation;
use Illuminate\Database\Eloquent\Collection;

class ListInvocationsAction
{
    /**
     * @return Collection<int, AgentConnectorInvocation>
     */
    public function execute(string $connectorId, string $connectionId): Collection
    {
        return AgentConnectorInvocation::where('connector_id', $connectorId)
            ->where('connection_id', $connectionId)
            ->orderByDesc('created_at')
            ->get();
    }
}
