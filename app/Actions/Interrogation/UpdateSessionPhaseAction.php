<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class UpdateSessionPhaseAction
{
    public function execute(InterrogationSession $session, int $phase, string $status): void
    {
        $session->phase = $phase;
        $session->status = $status;
        $session->save();
    }
}
