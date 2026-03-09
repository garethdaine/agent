<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;

class FindUserChatActionAction
{
    public function execute(int $userId, string $actionId): ?ChatAction
    {
        $sessionIds = ChatSession::query()
            ->where('user_id', $userId)
            ->pluck('id');

        $messageIds = ChatMessage::query()
            ->whereIn('chat_session_id', $sessionIds)
            ->pluck('id');

        return ChatAction::query()
            ->whereIn('chat_message_id', $messageIds)
            ->where('id', $actionId)
            ->first();
    }
}
