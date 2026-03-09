<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListConnectorAccountsAction
{
    /**
     * @param  array{provider?: string, status?: string, connection_mode?: string, sort?: string, dir?: string, with_session_count?: bool, per_page?: int}  $filters
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = ConnectorAccount::query();

        $provider = $filters['provider'] ?? '';
        $validProviders = [
            ConnectorAccount::PROVIDER_SLACK,
            ConnectorAccount::PROVIDER_TELEGRAM,
            ConnectorAccount::PROVIDER_DISCORD,
            ConnectorAccount::PROVIDER_WHATSAPP,
        ];
        if (in_array($provider, $validProviders, true)) {
            $query->where('provider', $provider);
        }

        $status = $filters['status'] ?? '';
        $validStatuses = [
            ConnectorAccount::STATUS_CONNECTED,
            ConnectorAccount::STATUS_DISCONNECTED,
            ConnectorAccount::STATUS_ERROR,
        ];
        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        }

        $connectionMode = $filters['connection_mode'] ?? '';
        if (in_array($connectionMode, [ConnectorAccount::MODE_LOCAL, ConnectorAccount::MODE_WEBHOOK], true)) {
            $query->where('connection_mode', $connectionMode);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['name', 'created_at', 'updated_at', 'provider'], true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        if ($filters['with_session_count'] ?? false) {
            $query->withCount('sessions');
        }

        $perPage = min(100, max(1, $filters['per_page'] ?? 25));

        return $query->paginate($perPage)->withQueryString();
    }
}
