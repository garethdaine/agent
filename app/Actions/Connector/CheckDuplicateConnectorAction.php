<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class CheckDuplicateConnectorAction
{
    public function execute(string $provider, string $accountKey, ?string $excludeId = null): bool
    {
        $query = ConnectorAccount::query()
            ->where('provider', $provider)
            ->where('account_key', $accountKey);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
