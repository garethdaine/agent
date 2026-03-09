<?php

declare(strict_types=1);

namespace App\Actions\Pairing;

use App\Models\MessengerIdentityLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMessengerIdentityLinksAction
{
    /**
     * @param  array{status?: string, connector_account_id?: string, per_page?: int}  $filters
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = MessengerIdentityLink::query()
            ->with('connectorAccount:id,name,provider')
            ->orderByDesc('created_at');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->statusFilter($filters['status']);
        }

        if (isset($filters['connector_account_id']) && $filters['connector_account_id'] !== '') {
            $query->forConnector($filters['connector_account_id']);
        }

        $perPage = min($filters['per_page'] ?? 50, 200);

        return $query->paginate($perPage);
    }
}
