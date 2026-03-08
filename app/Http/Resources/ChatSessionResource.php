<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatSession
 */
class ChatSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'connector_account_id' => $this->connector_account_id,
            'provider' => $this->provider,
            'channel_id' => $this->channel_id,
            'thread_id' => $this->thread_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'connector_account' => $this->whenLoaded('connectorAccount', fn () => [
                'id' => $this->connectorAccount->id,
                'provider' => $this->connectorAccount->provider,
                'name' => $this->connectorAccount->name,
                'status' => $this->connectorAccount->status,
            ]),
            'messages_count' => $this->whenCounted('messages'),
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'actions' => ChatActionResource::collection($this->whenLoaded('messages.actions')),
        ];
    }
}
