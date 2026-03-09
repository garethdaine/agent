<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRepoAnalysisSessionsAction
{
    /**
     * @param  array{user_id?: int, is_admin?: bool, deleted?: string, status?: string, per_page?: int}  $filters
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = RepoAnalysisSession::query();

        $isAdmin = $filters['is_admin'] ?? false;
        if (! $isAdmin && isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $deleted = $filters['deleted'] ?? '';
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        $status = trim($filters['status'] ?? '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = min(100, max(1, $filters['per_page'] ?? 25));

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
