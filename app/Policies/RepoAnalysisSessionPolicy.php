<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepoAnalysisSession;
use App\Models\User;

class RepoAnalysisSessionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, RepoAnalysisSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, RepoAnalysisSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }

    public function delete(User $user, RepoAnalysisSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }

    public function restore(User $user, RepoAnalysisSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }

    public function forceDelete(User $user, RepoAnalysisSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }
}
