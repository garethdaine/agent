<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class FindConnectorAccountByProviderAction
{
    public function execute(string $provider, string $status): ?ConnectorAccount
    {
        return ConnectorAccount::where('provider', $provider)
            ->where('status', $status)
            ->first();
    }
}
