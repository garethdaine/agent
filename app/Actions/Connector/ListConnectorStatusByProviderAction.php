<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class ListConnectorStatusByProviderAction
{
    /**
     * @return array<string, array<int, array{id: string, name: string, status: string}>>
     */
    public function execute(): array
    {
        return ConnectorAccount::query()
            ->select('id', 'provider', 'name', 'status')
            ->get()
            ->groupBy('provider')
            ->map(fn ($accounts) => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'status' => $account->status,
            ])->values())
            ->toArray();
    }
}
