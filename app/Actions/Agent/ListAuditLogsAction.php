<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAuditLogsAction
{
    /**
     * @param  array{
     *     action_prefix?: string|null,
     *     target_type?: string|null,
     *     actor_type?: string|null,
     *     outcome?: string|null,
     *     per_page?: int,
     * }  $filters
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = AgentAuditLog::query()
            ->orderByDesc('created_at');

        if (! empty($filters['action_prefix'])) {
            $query->where('action', 'like', $filters['action_prefix'].'%');
        }

        if (! empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }

        if (! empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        $perPage = min($filters['per_page'] ?? 50, 200);

        return $query->paginate($perPage);
    }
}
