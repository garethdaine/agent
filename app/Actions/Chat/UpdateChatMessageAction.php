<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatMessage;

class UpdateChatMessageAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ChatMessage $message, array $data): ChatMessage
    {
        $message->update($data);

        return $message;
    }
}
