<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class FindConnectorAccountAction
{
    public function execute(string $id, bool $withSessionCount = false): ConnectorAccount
    {
        $query = ConnectorAccount::query();

        if ($withSessionCount) {
            $query->withCount('sessions');
        }

        /** @var ConnectorAccount */
        return $query->findOrFail($id);
    }
}
