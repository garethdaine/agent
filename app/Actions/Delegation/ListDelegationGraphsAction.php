<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDelegationGraphsAction
{
    /**
     * @param  array{q?: string, status?: string, deleted?: string, sort?: string, dir?: string, per_page?: int}  $filters
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->delegationGraphs()->newQuery();

        $deleted = $filters['deleted'] ?? '';
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed(); // @phpstan-ignore method.notFound
        } elseif ($deleted === 'all') {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $status = strtolower(trim($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, [
            \App\Models\DelegationGraph::STATUS_DRAFT,
            \App\Models\DelegationGraph::STATUS_VALIDATING,
            \App\Models\DelegationGraph::STATUS_READY,
            \App\Models\DelegationGraph::STATUS_RUNNING,
            \App\Models\DelegationGraph::STATUS_SUCCEEDED,
            \App\Models\DelegationGraph::STATUS_FAILED,
            \App\Models\DelegationGraph::STATUS_PARTIAL,
            \App\Models\DelegationGraph::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'status'], true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, $filters['per_page'] ?? 25));

        return $query->withCount([
            'tasks',
            'tasks as tasks_completed_count' => fn ($q) => $q->whereIn('status', [
                DelegationTask::STATUS_SUCCEEDED,
                DelegationTask::STATUS_FAILED,
                DelegationTask::STATUS_CANCELLED,
            ]),
        ])->paginate($perPage)->withQueryString();
    }
}
