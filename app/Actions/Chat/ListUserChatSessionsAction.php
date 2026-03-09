<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserChatSessionsAction
{
    /**
     * @param  array{status?: string, provider?: string, connector_id?: string, per_page?: int}  $filters
     */
    public function execute(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = ChatSession::with('connectorAccount:id,name,provider')
            ->where('user_id', $userId)
            ->withCount('messages');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (! empty($filters['connector_id'])) {
            $query->where('connector_account_id', $filters['connector_id']);
        }

        return $query->orderByDesc('updated_at')
            ->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
