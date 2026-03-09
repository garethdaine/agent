<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ConnectorAccount;

class FindConnectorAccountAction
{
    public function execute(string $id): ConnectorAccount
    {
        return ConnectorAccount::findOrFail($id);
    }
}
