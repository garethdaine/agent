<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ChatMessageReceived;
use App\Models\ChatMessage;

class ChatMessageObserver
{
    public function created(ChatMessage $message): void
    {
        $session = $message->session;
        if (! $session?->user_id) {
            return;
        }

        try {
            event(ChatMessageReceived::fromMessage($message, (int) $session->user_id));
        } catch (\Throwable) {
            // Broadcasting should never break message persistence
        }
    }
}
