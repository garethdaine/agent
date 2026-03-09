<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Cache;

class FindConnectorAccountAction
{
    private const CACHE_TTL_SECONDS = 300;

    public function execute(string $id, bool $withSessionCount = false): ConnectorAccount
    {
        if ($withSessionCount) {
            $query = ConnectorAccount::query()->withCount('sessions');

            /** @var ConnectorAccount */
            return $query->findOrFail($id);
        }

        /** @var ConnectorAccount */
        return Cache::remember(
            "connector:account:{$id}",
            self::CACHE_TTL_SECONDS,
            static fn () => ConnectorAccount::query()->findOrFail($id),
        );
    }
}
