<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatAttachment>
 */
class ChatAttachmentFactory extends Factory
{
    protected $model = ChatAttachment::class;

    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 10485760),
            'storage_path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'provider_file_id' => null,
            'scan_status' => 'clean',
            'expires_at' => null,
            'created_at' => now(),
        ];
    }
}
