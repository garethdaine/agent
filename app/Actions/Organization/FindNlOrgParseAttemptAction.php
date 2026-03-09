<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\NlOrgParseAttempt;

class FindNlOrgParseAttemptAction
{
    public function execute(string $id): NlOrgParseAttempt
    {
        return NlOrgParseAttempt::findOrFail($id);
    }
}
