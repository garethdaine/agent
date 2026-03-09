<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgEscalation;

class FindOrgEscalationAction
{
    public function execute(string $id): ?OrgEscalation
    {
        return OrgEscalation::find($id);
    }
}
