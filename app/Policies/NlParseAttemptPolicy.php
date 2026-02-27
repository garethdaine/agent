<?php

namespace App\Policies;

use App\Models\NlParseAttempt;
use App\Models\User;

class NlParseAttemptPolicy
{
    /**
     * Determine if the user can view all parse attempts (telemetry).
     *
     * Only admin/analytics roles can view all attempts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'analytics']);
    }

    /**
     * Determine if the user can view a specific parse attempt.
     *
     * Users can view their own attempts, admin/analytics can view all.
     */
    public function view(User $user, NlParseAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id
            || $user->hasRole(['admin', 'analytics']);
    }
}
