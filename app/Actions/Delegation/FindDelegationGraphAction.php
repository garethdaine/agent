<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationGraph;
use App\Models\User;

class FindDelegationGraphAction
{
    public function execute(User $user, int $id, bool $withTrashed = true): ?DelegationGraph
    {
        $query = $user->delegationGraphs();

        if ($withTrashed) {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        /** @var DelegationGraph|null */
        return $query->find($id);
    }
}
