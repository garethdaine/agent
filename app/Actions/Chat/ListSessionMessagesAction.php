<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Collection;

class ListSessionMessagesAction
{
    /**
     * @return Collection<int, \App\Models\ChatMessage>
     */
    public function execute(ChatSession $session, int $limit = 30): Collection
    {
        return $session->messages()
            ->orderByDesc('created_at')
            ->limit(min($limit, 200))
            ->get(['id', 'direction', 'content', 'provider_message_id', 'created_at']);
    }
}
