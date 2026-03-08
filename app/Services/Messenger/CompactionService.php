<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Compacts older chat messages into a summary when context exceeds thresholds.
 */
final class CompactionService
{
    private const MAX_SUMMARY_CHARS = 8000;

    public function __construct(
        private ContextUsageEstimator $estimator,
    ) {}

    /**
     * Whether the session has exceeded compaction thresholds and compaction would run if invoked.
     * Use this to decide whether to dispatch CompactionJob (e.g. after a turn) so compaction runs only when context is running low.
     */
    public function isCompactionNeeded(ChatSession $session): bool
    {
        if (! config('messenger.compaction.enabled', true)) {
            return false;
        }

        $triggerMessages = (int) config('messenger.compaction.trigger_message_count', 30);
        $triggerTokens = (int) config('messenger.compaction.trigger_estimated_tokens', 15_000);
        $targetMessages = (int) config('messenger.compaction.target_message_count', 10);

        $stats = $this->estimator->estimate($session);
        $messageCount = $stats['message_count'];
        $estimatedTokens = $stats['estimated_tokens'];

        if ($messageCount < $triggerMessages && $estimatedTokens < $triggerTokens) {
            return false;
        }

        $messages = $session->messages()->orderBy('created_at')->get();
        if ($messages->count() <= $targetMessages) {
            return false;
        }

        $toSummarize = $messages->slice(0, -$targetMessages);

        return $toSummarize->isNotEmpty();
    }

    /**
     * Run compaction if the session exceeds configured thresholds.
     * Returns true if compaction was performed, false otherwise.
     */
    public function compactIfNeeded(ChatSession $session, ?string $instructions = null): bool
    {
        if (! config('messenger.compaction.enabled', true)) {
            return false;
        }

        $triggerMessages = (int) config('messenger.compaction.trigger_message_count', 30);
        $triggerTokens = (int) config('messenger.compaction.trigger_estimated_tokens', 15_000);
        $targetMessages = (int) config('messenger.compaction.target_message_count', 10);

        $stats = $this->estimator->estimate($session);
        $messageCount = $stats['message_count'];
        $estimatedTokens = $stats['estimated_tokens'];

        if ($messageCount < $triggerMessages && $estimatedTokens < $triggerTokens) {
            return false;
        }

        /** @var \Illuminate\Support\Collection<int, ChatMessage> $messages */
        $messages = $session->messages()->orderBy('created_at')->get();
        if ($messages->count() <= $targetMessages) {
            return false;
        }

        /** @var \Illuminate\Support\Collection<int, ChatMessage> $toSummarize */
        $toSummarize = $messages->slice(0, -$targetMessages);
        /** @var ChatMessage|null $boundaryMessage */
        $boundaryMessage = $toSummarize->last();
        if ($boundaryMessage === null) {
            return false;
        }

        $summary = $this->buildSummary($toSummarize, $instructions);

        $session->update([
            'compaction_summary' => $summary,
            'compaction_message_count' => $toSummarize->count(),
            'compaction_at' => now(),
            'compaction_boundary_message_id' => $boundaryMessage->id,
        ]);

        Log::info('Chat session compacted', [
            'chat_session_id' => $session->id,
            'messages_summarized' => $toSummarize->count(),
            'recent_kept' => $targetMessages,
        ]);

        return true;
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function buildSummary(Collection $messages, ?string $instructions): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $role = $msg->direction === 'inbound' ? 'User' : 'Assistant';
            $content = trim((string) ($msg->content ?? ''));
            if ($content !== '') {
                $lines[] = "{$role}: {$content}";
            }
        }
        $full = implode("\n\n", $lines);
        $summary = mb_strlen($full) > self::MAX_SUMMARY_CHARS
            ? mb_substr($full, 0, self::MAX_SUMMARY_CHARS - 3).'...'
            : $full;

        if ($instructions !== null && $instructions !== '') {
            $summary = "Instructions: {$instructions}\n\n".$summary;
        }

        return $summary;
    }
}
