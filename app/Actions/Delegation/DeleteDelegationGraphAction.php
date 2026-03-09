<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationGraph;

class DeleteDelegationGraphAction
{
    public function execute(DelegationGraph $graph): bool
    {
        return (bool) $graph->delete();
    }
}
