<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInterrogationSessionsAction
{
    /**
     * @return LengthAwarePaginator<InterrogationSession>
     */
    public function execute(
        int $userId,
        string $deleted,
        string $status,
        string $type,
        string $runner,
        string $search,
        int $perPage,
    ): LengthAwarePaginator {
        $query = InterrogationSession::query()->where('user_id', $userId);

        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($type !== '') {
            $query->where('interrogation_type', $type);
        }

        if ($runner !== '') {
            $query->where('runner_type', $runner);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('feature_brief', 'like', "%{$search}%")
                    ->orWhere('project_directory', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, $perPage));

        /** @var LengthAwarePaginator<InterrogationSession> */
        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
