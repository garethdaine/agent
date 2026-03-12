<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAgentJobsAction
{
    /**
     * @param  array{
     *     deleted?: string,
     *     q?: string,
     *     is_enabled?: bool|null,
     *     runner_type?: string,
     *     workflow_key?: string,
     *     source?: string,
     *     sort?: string,
     *     dir?: string,
     *     per_page?: int,
     * }  $filters
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AgentJob::query()->forUser($user);

        $deleted = $filters['deleted'] ?? '';
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', $filters['is_enabled']);
        }

        if (! empty($filters['runner_type'])) {
            $query->where('runner_type', $filters['runner_type']);
        }

        $workflowKey = trim($filters['workflow_key'] ?? '');
        if ($workflowKey !== '') {
            $query->where('workflow_key', $workflowKey);
        }

        $source = strtolower(trim($filters['source'] ?? ''));
        if ($source === 'build') {
            $query->where(function ($builder): void {
                $builder->where('name', 'like', 'Interrogation Build S%')
                    ->orWhere('env_json->AGENT_JOB_SOURCE', 'interrogation_build');
            });
        } elseif ($source === 'delegation') {
            $query->where('name', 'like', 'Delegation: %');
        } elseif ($source === 'user') {
            $query->where(function ($builder): void {
                $builder->where('name', 'not like', 'Interrogation Build S%')
                    ->where('name', 'not like', 'Delegation: %')
                    ->where(function ($inner): void {
                        $inner->whereNull('env_json->AGENT_JOB_SOURCE')
                            ->orWhere('env_json->AGENT_JOB_SOURCE', '!=', 'interrogation_build');
                    });
            });
        }

        $sort = $filters['sort'] ?? 'name';
        $dir = strtolower($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'is_enabled'], true)) {
            $sort = 'name';
        }

        if ($sort === 'is_enabled') {
            $query->orderBy('is_enabled', 'desc')->orderBy('name', 'asc');
        } else {
            $query->orderBy($sort, $dir);
        }

        $perPage = min(100, max(1, $filters['per_page'] ?? 25));

        return $query->paginate($perPage)->withQueryString();
    }
}
