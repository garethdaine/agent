<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Collection;

class ListConnectorAccountSummariesAction
{
    /**
     * @return Collection<int, ConnectorAccount>
     */
    public function execute(): Collection
    {
        return ConnectorAccount::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider']);
    }
}
