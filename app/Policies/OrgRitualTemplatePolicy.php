<?php

namespace App\Policies;

use App\Models\OrgRitualTemplate;
use App\Models\User;

class OrgRitualTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function delete(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function restore(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function run(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function forceDelete(User $user, OrgRitualTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
