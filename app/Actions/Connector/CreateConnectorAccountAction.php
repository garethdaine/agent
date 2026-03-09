<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class CreateConnectorAccountAction
{
    /**
     * @param  array{provider: string, name: string, credentials: array<string, mixed>, webhook_secret: ?string, connection_mode: string, status: string, config: array<string, mixed>, account_key: string}  $attributes
     */
    public function execute(array $attributes): ConnectorAccount
    {
        return ConnectorAccount::create($attributes);
    }
}
