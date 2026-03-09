<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgAgentProfile;
use Illuminate\Database\Eloquent\Collection;

class ListOrgAgentProfilesAction
{
    /**
     * @param  list<string>  $with
     * @return Collection<int, OrgAgentProfile>
     */
    public function execute(int $userId, bool $includeArchived = false, array $with = ['delegateeProfile']): Collection
    {
        $query = OrgAgentProfile::forUser($userId);

        if (! $includeArchived) {
            $query->active();
        }

        return $query->with($with)->get();
    }
}
