<?php

namespace App\Services\Messenger;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatSessionManager
{
    /**
     * Find an existing session or create a new one.
     */
    public function findOrCreate(
        ConnectorAccount $account,
        string $channelId,
        ?string $threadId,
        User $user
    ): ChatSession {
        return ChatSession::firstOrCreate(
            [
                'connector_account_id' => $account->id,
                'channel_id' => $channelId,
                'thread_id' => $threadId,
            ],
            [
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'provider' => $account->provider,
                'status' => ChatSession::STATUS_ACTIVE,
            ]
        );
    }

    /**
     * Resolve the thread ID from a message payload.
     * Returns the thread_ts (Slack) or reply_to_message_id (Telegram) if present.
     */
    public function resolveThread(ChatSession $session, array $payload): ?string
    {
        // Slack: thread_ts in event
        if (isset($payload['event']['thread_ts'])) {
            return $payload['event']['thread_ts'];
        }

        // Telegram: reply_to_message.message_id
        if (isset($payload['message']['reply_to_message']['message_id'])) {
            return (string) $payload['message']['reply_to_message']['message_id'];
        }

        return $session->thread_id;
    }

    /**
     * Get session message history for AI context.
     *
     * @return Collection<int, ChatMessage>
     */
    public function getSessionHistory(ChatSession $session, ?int $limit = null): Collection
    {
        $limit = $limit ?? $this->getHistoryLimit($session);

        return $session->messages()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Update the session's last activity timestamp.
     */
    public function updateActivity(ChatSession $session): void
    {
        $session->touch();
    }

    /**
     * Archive a session.
     */
    public function archive(ChatSession $session): void
    {
        $session->update([
            'status' => ChatSession::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Reactivate an archived session.
     */
    public function reactivate(ChatSession $session): void
    {
        $session->update([
            'status' => ChatSession::STATUS_ACTIVE,
        ]);
    }

    /**
     * Get the latest inbound message for threading.
     */
    public function getLatestInboundMessage(ChatSession $session): ?ChatMessage
    {
        return $session->messages()
            ->where('direction', ChatMessage::DIRECTION_INBOUND)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get the latest outbound message for threading.
     */
    public function getLatestOutboundMessage(ChatSession $session): ?ChatMessage
    {
        return $session->messages()
            ->where('direction', ChatMessage::DIRECTION_OUTBOUND)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get the configured history limit for a session.
     */
    private function getHistoryLimit(ChatSession $session): int
    {
        $account = $session->connectorAccount;

        return $account->config['session_history_limit']
            ?? config("messenger.providers.{$account->provider}.session_history_limit", 20);
    }
}
