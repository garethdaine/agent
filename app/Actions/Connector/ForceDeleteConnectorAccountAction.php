<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class ForceDeleteConnectorAccountAction
{
    public function execute(ConnectorAccount $connector): void
    {
        $connector->forceDelete();
    }
}
