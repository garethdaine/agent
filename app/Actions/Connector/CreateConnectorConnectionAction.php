<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorConnection;

class CreateConnectorConnectionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): AgentConnectorConnection
    {
        return AgentConnectorConnection::create($attributes);
    }
}
