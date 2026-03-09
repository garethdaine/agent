<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\OrgRitualTemplate;

class FindOrgRitualTemplateAction
{
    public function execute(string $id): ?OrgRitualTemplate
    {
        return OrgRitualTemplate::find($id);
    }
}
