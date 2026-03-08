<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_session_id' => $this->chat_session_id,
            'connector_account_id' => $this->connector_account_id,
            'direction' => $this->direction,
            'content' => $this->content,
            'attachment_ids' => $this->attachment_ids,
            'provider_message_id' => $this->provider_message_id,
            'provider_timestamp' => $this->provider_timestamp?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'actions' => ChatActionResource::collection($this->whenLoaded('actions')),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'scan_status' => $attachment->scan_status,
            ])),
        ];
    }
}
