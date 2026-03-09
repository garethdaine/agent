<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use Illuminate\Support\Collection;

class ListInterrogationEventsAction
{
    /**
     * @return array{events: Collection<int, InterrogationEvent>, has_more: bool}
     */
    public function execute(InterrogationSession $session, int $afterSequence, int $limit): array
    {
        $limit = min(500, max(1, $limit));

        $events = $session->events()
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();

        $hasMore = $events->count() > $limit;
        $returned = $events->take($limit)->values();

        return [
            'events' => $returned,
            'has_more' => $hasMore,
        ];
    }
}
