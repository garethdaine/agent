<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly int $sessionId,
        public readonly string $direction,
        public readonly string $preview,
    ) {}

    public static function fromMessage(ChatMessage $message, int $userId): self
    {
        return new self(
            userId: $userId,
            sessionId: (int) $message->chat_session_id,
            direction: $message->direction,
            preview: str($message->content)->limit(120)->toString(),
        );
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message_received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'direction' => $this->direction,
            'preview' => $this->preview,
        ];
    }
}
