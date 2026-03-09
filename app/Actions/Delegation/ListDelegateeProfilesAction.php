<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDelegateeProfilesAction
{
    /**
     * @param  array{q?: string, deleted?: string, is_active?: bool|null, runner_type?: string, sort?: string, dir?: string, per_page?: int}  $filters
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->delegateeProfiles()->newQuery();

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
                    ->orWhere('runner_type', 'like', "%{$q}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['runner_type']) && $filters['runner_type'] !== '') {
            $query->where('runner_type', $filters['runner_type']);
        }

        $sort = $filters['sort'] ?? 'name';
        $dir = strtolower($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'is_active', 'runner_type'], true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, $filters['per_page'] ?? 25));

        return $query->with(['capabilities', 'metric'])->paginate($perPage)->withQueryString();
    }
}
