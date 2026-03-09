<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatSession;

class CreateChatSessionAction
{
    /**
     * @param  array{user_id: int, connector_account_id: string, provider: string, channel_id: string, session_key: string, status: string}  $data
     */
    public function execute(array $data): ChatSession
    {
        return ChatSession::create($data);
    }
}
