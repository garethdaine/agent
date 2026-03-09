<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Collection;

class ListConnectedAccountsAction
{
    /**
     * @return Collection<int, ConnectorAccount>
     */
    public function execute(): Collection
    {
        return ConnectorAccount::where('status', ConnectorAccount::STATUS_CONNECTED)
            ->orderBy('provider')
            ->get(['id', 'name', 'provider', 'status']);
    }
}
