<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class FindDuplicateConnectorWithTrashedAction
{
    public function execute(string $provider, string $accountKey, string $excludeId): ?ConnectorAccount
    {
        /** @var ConnectorAccount|null */
        return ConnectorAccount::withTrashed()
            ->where('provider', $provider)
            ->where('account_key', $accountKey)
            ->where('id', '!=', $excludeId)
            ->first();
    }
}
