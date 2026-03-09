<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgCouncilTemplate;

class FindOrgCouncilTemplateAction
{
    public function execute(string $id): ?OrgCouncilTemplate
    {
        return OrgCouncilTemplate::find($id);
    }
}
