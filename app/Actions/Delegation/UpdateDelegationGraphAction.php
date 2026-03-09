<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationGraph;

class UpdateDelegationGraphAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(DelegationGraph $graph, array $attributes): DelegationGraph
    {
        $graph->fill($attributes);
        $graph->save();

        return $graph;
    }
}
