<?php

namespace App\Policies;

use App\Models\NlOrgParseAttempt;
use App\Models\User;

class NlOrgParseAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'analytics']);
    }

    public function view(User $user, NlOrgParseAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id
            || $user->hasRole(['admin', 'analytics']);
    }

    public function apply(User $user, NlOrgParseAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }
}
