<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Runtime\RuntimeApproval;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RuntimeApprovalRequested implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly int $approvalId,
        public readonly int $toolCallId,
        public readonly string $toolName,
        public readonly int $sessionId,
    ) {}

    public static function fromApproval(RuntimeApproval $approval, int $userId): self
    {
        /** @var \App\Models\Runtime\RuntimeToolCall $toolCall */
        $toolCall = $approval->toolCall;

        /** @var \App\Models\Runtime\RuntimeTurn $turn */
        $turn = $toolCall->turn;

        return new self(
            userId: $userId,
            approvalId: (int) $approval->id,
            toolCallId: (int) $toolCall->id,
            toolName: $toolCall->tool_name,
            sessionId: (int) $turn->runtime_session_id,
        );
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'runtime.approval_requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
            'session_id' => $this->sessionId,
        ];
    }
}
