<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\NlOrgParseAttempt;

class MarkNlOrgParseAttemptAppliedAction
{
    public function execute(NlOrgParseAttempt $attempt): NlOrgParseAttempt
    {
        $attempt->update(['applied_at' => now()]);

        return $attempt->fresh();
    }
}
