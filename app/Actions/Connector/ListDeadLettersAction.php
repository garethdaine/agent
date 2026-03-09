<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\MessengerDeadLetter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDeadLettersAction
{
    public function execute(?string $connectorId = null, int $perPage = 25): LengthAwarePaginator
    {
        return MessengerDeadLetter::query()
            ->when(
                $connectorId,
                fn ($query, $connectorId) => $query->where('connector_account_id', $connectorId)
            )
            ->with('connectorAccount')
            ->orderByDesc('failed_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
