<?php

namespace App\Policies;

use App\Models\InterrogationSession;
use App\Models\User;

class InterrogationSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, InterrogationSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $activeCount = InterrogationSession::query()
            ->forUser($user->id)
            ->active()
            ->count();

        return $activeCount < 3;
    }

    public function update(User $user, InterrogationSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function delete(User $user, InterrogationSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function restore(User $user, InterrogationSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function forceDelete(User $user, InterrogationSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
