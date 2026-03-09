<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnector;

class FindAgentConnectorAction
{
    public function execute(string $id): AgentConnector
    {
        return AgentConnector::findOrFail($id);
    }

    public function find(string $id): ?AgentConnector
    {
        return AgentConnector::find($id);
    }

    public function findByName(string $name): ?AgentConnector
    {
        return AgentConnector::where('name', $name)->first();
    }
}
