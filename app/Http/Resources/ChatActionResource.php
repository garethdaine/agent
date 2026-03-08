<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChatAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatAction
 */
class ChatActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_message_id' => $this->chat_message_id,
            'action_type' => $this->action_type,
            'parameters' => $this->parameters,
            'status' => $this->status,
            'result' => $this->result,
            'error' => $this->error,
            'requires_confirmation' => $this->requires_confirmation,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'is_pending' => $this->isPending(),
            'is_executing' => $this->isExecuting(),
            'is_terminal' => $this->isTerminal(),
            'is_destructive' => $this->isDestructive(),
        ];
    }
}
