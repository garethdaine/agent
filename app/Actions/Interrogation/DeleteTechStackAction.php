<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;
use App\Models\InterrogationTechStack;

class DeleteTechStackAction
{
    public function execute(InterrogationSession $session, int $stackId): void
    {
        $stack = InterrogationTechStack::query()
            ->where('interrogation_session_id', $session->id)
            ->whereKey($stackId)
            ->firstOrFail();

        $stack->delete();
    }
}
