<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationGraph;

class RestoreDelegationGraphAction
{
    public function execute(DelegationGraph $graph): bool
    {
        if ($graph->trashed()) {
            return (bool) $graph->restore();
        }

        return false;
    }
}
