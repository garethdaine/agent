<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatMessage;

class CreateChatMessageAction
{
    /**
     * @param  array{chat_session_id: string, connector_account_id: string, direction: string, content: string, idempotency_key: string}  $data
     */
    public function execute(array $data): ChatMessage
    {
        return ChatMessage::create($data);
    }
}
