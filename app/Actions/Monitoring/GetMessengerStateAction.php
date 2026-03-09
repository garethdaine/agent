<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\ConnectorAccount;

class GetMessengerStateAction
{
    /**
     * @return array{channels: array<int, array<string, mixed>>}
     */
    public function execute(): array
    {
        $connectors = ConnectorAccount::query()
            ->whereNull('deleted_at')
            ->get();

        return [
            'channels' => $connectors->map(fn (ConnectorAccount $c) => [
                'id' => $c->id,
                'platform' => $c->provider,
                'name' => $c->name,
                'status' => $c->status ?? 'unknown',
                'unread' => 0,
            ])->values()->toArray(),
        ];
    }
}
