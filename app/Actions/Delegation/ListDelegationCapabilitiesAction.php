<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationCapability;
use Illuminate\Database\Eloquent\Collection;

class ListDelegationCapabilitiesAction
{
    /**
     * @return Collection<int, DelegationCapability>
     */
    public function execute(): Collection
    {
        /** @var Collection<int, DelegationCapability> */
        return DelegationCapability::query()
            ->active()
            ->orderBy('slug')
            ->get(['id', 'slug', 'name', 'description']);
    }
}
