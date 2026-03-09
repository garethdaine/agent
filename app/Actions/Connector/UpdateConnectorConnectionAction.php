<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\AgentConnectorConnection;

class UpdateConnectorConnectionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(AgentConnectorConnection $connection, array $attributes): AgentConnectorConnection
    {
        $connection->update($attributes);

        return $connection;
    }
}
