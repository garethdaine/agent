<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgRitualTemplate;
use Illuminate\Database\Eloquent\Collection;

class ListOrgRitualTemplatesAction
{
    /**
     * @return Collection<int, OrgRitualTemplate>
     */
    public function execute(int $userId, bool $includeArchived = false): Collection
    {
        $query = OrgRitualTemplate::forUser($userId);

        if (! $includeArchived) {
            $query->active();
        }

        return $query->get();
    }
}
