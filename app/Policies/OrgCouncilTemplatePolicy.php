<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrgCouncilTemplate;
use App\Models\User;

class OrgCouncilTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, OrgCouncilTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function update(User $user, OrgCouncilTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function delete(User $user, OrgCouncilTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function restore(User $user, OrgCouncilTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function forceDelete(User $user, OrgCouncilTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
