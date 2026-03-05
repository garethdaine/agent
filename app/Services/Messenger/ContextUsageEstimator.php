<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\ChatSession;

/**
 * Estimates context usage for a chat session (message count and estimated tokens).
 */
final class ContextUsageEstimator
{
    public function estimate(ChatSession $session, ?int $limit = null): array
    {
        $charsPerToken = config('messenger.context.chars_per_token', 4);
        $charsPerToken = $charsPerToken > 0 ? $charsPerToken : 4;

        $query = $session->messages()->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $messages = $query->get();
        $messageCount = $messages->count();
        $totalChars = $messages->sum(fn ($m) => strlen((string) ($m->content ?? '')));
        $estimatedTokens = (int) ceil($totalChars / $charsPerToken);

        return [
            'message_count' => $messageCount,
            'estimated_tokens' => $estimatedTokens,
            'total_chars' => $totalChars,
        ];
    }

    public function getContextWindowSize(): int
    {
        return (int) config('messenger.context.estimated_context_window', 200_000);
    }
}
