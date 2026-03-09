<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgCouncilTemplate;
use Illuminate\Database\Eloquent\Collection;

class ListOrgCouncilTemplatesAction
{
    /**
     * @return Collection<int, OrgCouncilTemplate>
     */
    public function execute(int $userId, bool $includeArchived = false): Collection
    {
        $query = OrgCouncilTemplate::forUser($userId);

        if (! $includeArchived) {
            $query->active();
        }

        return $query->get();
    }
}
