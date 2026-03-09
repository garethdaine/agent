<?php

declare(strict_types=1);

namespace App\Actions\Runtime;

use App\Models\Runtime\RuntimeSession;
use App\Models\User;

class FindRuntimeSessionAction
{
    /**
     * @param  list<string>  $with
     */
    public function execute(User $user, string $id, array $with = []): RuntimeSession
    {
        $query = RuntimeSession::query()->forUser($user);

        if ($with !== []) {
            $query->with($with);
        }

        /** @var RuntimeSession */
        return $query->findOrFail($id);
    }
}
