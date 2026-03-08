<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DelegationGraph;
use App\Models\User;

class DelegationGraphPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function update(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id && $graph->status === DelegationGraph::STATUS_DRAFT;
    }

    public function delete(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id && in_array($graph->status, DelegationGraph::TERMINAL_STATUSES, true);
    }

    public function restore(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id;
    }

    public function forceDelete(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id && in_array($graph->status, DelegationGraph::TERMINAL_STATUSES, true);
    }

    public function start(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id && $graph->status === DelegationGraph::STATUS_READY;
    }

    public function cancel(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id && in_array($graph->status, DelegationGraph::ACTIVE_STATUSES, true);
    }

    public function clone(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id;
    }

    public function validate(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id;
    }

    public function events(User $user, DelegationGraph $graph): bool
    {
        return $graph->user_id === $user->id;
    }
}
