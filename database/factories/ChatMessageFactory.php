<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'chat_session_id' => ChatSession::factory(),
            'connector_account_id' => ConnectorAccount::factory(),
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $this->faker->sentence(),
            'attachment_ids' => null,
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'provider_event_id' => 'Ev'.strtoupper(Str::random(10)),
            'provider_message_id' => $this->faker->numerify('##########.######'),
            'provider_timestamp' => now(),
            'created_at' => now(),
        ];
    }

    public function forSession(ChatSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'chat_session_id' => $session->id,
            'connector_account_id' => $session->connector_account_id,
        ]);
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => ChatMessage::DIRECTION_INBOUND,
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
        ]);
    }

    public function withAttachments(array $attachmentIds): static
    {
        return $this->state(fn (array $attributes) => [
            'attachment_ids' => $attachmentIds,
        ]);
    }
}
