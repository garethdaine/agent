<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatAction;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Collection;

class ListSessionActionsAction
{
    /**
     * @return Collection<int, ChatAction>
     */
    public function execute(ChatSession $session, int $limit = 30): Collection
    {
        $messageIds = $session->messages()->pluck('id');

        return ChatAction::whereIn('chat_message_id', $messageIds)
            ->orderByDesc('created_at')
            ->limit(min($limit, 200))
            ->get(['id', 'action_type', 'status', 'chat_message_id', 'created_at']);
    }
}
