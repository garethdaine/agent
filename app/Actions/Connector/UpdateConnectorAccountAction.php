<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class UpdateConnectorAccountAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(ConnectorAccount $connector, array $attributes): ConnectorAccount
    {
        $connector->fill($attributes);
        $connector->save();

        return $connector;
    }
}
