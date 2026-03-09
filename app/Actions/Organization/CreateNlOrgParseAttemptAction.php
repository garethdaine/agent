<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\NlOrgParseAttempt;
use App\Support\NlOrg\NlOrgParseResult;

class CreateNlOrgParseAttemptAction
{
    public function execute(int $userId, string $rawInput, NlOrgParseResult $result): NlOrgParseAttempt
    {
        /** @var NlOrgParseAttempt */
        return NlOrgParseAttempt::create([
            'user_id' => $userId,
            'raw_input' => $rawInput,
            'parsed_result' => $result->toArray(),
            'confidence' => $result->confidence,
            'applied_at' => null,
        ]);
    }
}
