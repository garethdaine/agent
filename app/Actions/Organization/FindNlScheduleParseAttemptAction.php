<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\NlParseAttempt;

class FindNlScheduleParseAttemptAction
{
    public function execute(string $id): NlParseAttempt
    {
        return NlParseAttempt::findOrFail($id);
    }
}
