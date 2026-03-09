<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatSession;

class UpdateChatSessionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ChatSession $session, array $data): ChatSession
    {
        $session->update($data);

        return $session;
    }
}
