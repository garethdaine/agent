<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;
use App\Models\InterrogationTechStack;

class CreateTechStackAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(InterrogationSession $session, array $validated): InterrogationTechStack
    {
        $maxSequence = (int) $session->techStacks()->max('sequence');

        /** @var InterrogationTechStack $stack */
        $stack = $session->techStacks()->create([
            'sequence' => $maxSequence + 1,
            'name' => $validated['name'],
            'documentation_url' => $validated['documentation_url'],
            'metadata_json' => [
                'source' => 'manual',
            ],
        ]);

        if ((int) $session->phase <= InterrogationSession::PHASE_PROVIDER_SETUP) {
            $session->phase = InterrogationSession::PHASE_TECH_STACK_SETUP;
            $session->status = InterrogationSession::STATUS_SETUP;
            $session->save();
        }

        return $stack;
    }
}
