<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class UpdateSessionStaleMarkAction
{
    public function execute(InterrogationSession $session, string $questionId): InterrogationSession
    {
        $metadata = (array) ($session->metadata_json ?? []);
        $metadata['stale_from_question_id'] = $questionId;
        $session->metadata_json = $metadata;
        $session->save();

        return $session;
    }
}
