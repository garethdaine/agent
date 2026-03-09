<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class FindInterrogationSessionAction
{
    public function execute(int $userId, int $sessionId, bool $withTrashed = false): InterrogationSession
    {
        $query = $withTrashed
            ? InterrogationSession::withTrashed()
            : InterrogationSession::query();

        /** @var InterrogationSession */
        return $query->where('user_id', $userId)->findOrFail($sessionId);
    }
}
