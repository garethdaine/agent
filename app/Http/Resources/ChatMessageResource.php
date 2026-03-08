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
        /** @var ChatMessage $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'chat_session_id' => $resource->chat_session_id,
            'connector_account_id' => $resource->connector_account_id,
            'direction' => $resource->direction,
            'content' => $resource->content,
            'attachment_ids' => $resource->attachment_ids,
            'provider_message_id' => $resource->provider_message_id,
            'provider_timestamp' => $resource->provider_timestamp?->toIso8601String(),
            'created_at' => $resource->created_at?->toIso8601String(),
            'actions' => ChatActionResource::collection($this->whenLoaded('actions')),
            'attachments' => $this->whenLoaded('attachments', fn () => $resource->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'scan_status' => $attachment->scan_status,
            ])),
        ];
    }
}
