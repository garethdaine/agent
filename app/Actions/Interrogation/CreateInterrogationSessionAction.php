<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class CreateInterrogationSessionAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $metadata
     */
    public function execute(int $userId, array $validated, array $metadata): InterrogationSession
    {
        /** @var InterrogationSession */
        return InterrogationSession::query()->create([
            'user_id' => $userId,
            'name' => $validated['name'] ?? null,
            'runner_type' => $validated['runner_type'],
            'model' => $validated['model'] ?? null,
            'project_directory' => $validated['project_directory'],
            'interrogation_type' => $validated['interrogation_type'],
            'feature_brief' => $validated['feature_brief'] ?? null,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
            'metadata_json' => $metadata,
        ]);
    }
}
