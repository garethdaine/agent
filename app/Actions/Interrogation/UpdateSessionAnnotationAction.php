<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;

class UpdateSessionAnnotationAction
{
    public function execute(InterrogationSession $session, string $key, mixed $value): InterrogationSession
    {
        $annotations = (array) ($session->annotations_json ?? []);
        $annotations[$key] = $value;
        $session->annotations_json = $annotations;
        $session->save();

        return $session;
    }
}
