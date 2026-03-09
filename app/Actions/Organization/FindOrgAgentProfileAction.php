<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgAgentProfile;

class FindOrgAgentProfileAction
{
    public function execute(string $id): ?OrgAgentProfile
    {
        return OrgAgentProfile::find($id);
    }
}
