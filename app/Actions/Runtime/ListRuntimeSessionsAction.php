<?php

declare(strict_types=1);

namespace App\Actions\Runtime;

use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRuntimeSessionsAction
{
    public function execute(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return RuntimeSession::query()
            ->forUser($user)
            ->orderBy('started_at', 'desc')
            ->paginate(min(100, max(1, $perPage)))
            ->withQueryString();
    }
}
