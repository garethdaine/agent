<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Collection;

class ListAllConnectorAccountsAction
{
    /**
     * @param  array<int, string>|null  $columns
     * @return Collection<int, ConnectorAccount>
     */
    public function execute(?array $columns = null, int $limit = 100): Collection
    {
        $query = ConnectorAccount::query()->limit($limit);

        if ($columns !== null) {
            $query->select($columns);
        }

        return $query->get();
    }
}
