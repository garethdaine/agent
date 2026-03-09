<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class DeleteConnectorAccountAction
{
    public function execute(ConnectorAccount $connector): void
    {
        $connector->forceDelete();
    }
}
