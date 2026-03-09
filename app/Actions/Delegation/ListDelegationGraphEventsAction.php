<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use Illuminate\Database\Eloquent\Collection;

class ListDelegationGraphEventsAction
{
    /**
     * @return Collection<int, DelegationEvent>
     */
    public function execute(DelegationGraph $graph, int $afterSequence, int $limit): Collection
    {
        /** @var Collection<int, DelegationEvent> */
        return $graph->events()
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence', 'asc')
            ->take($limit)
            ->get();
    }
}
