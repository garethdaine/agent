<?php

declare(strict_types=1);

namespace App\Jobs\Runtime;

use App\DTOs\Messenger\OutboundPayload;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubAgentCompletionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    private const MAX_TEXT_LENGTH = 2000;

    public function __construct(
        public string $childSessionId,
        public string $parentSessionId,
        public string $status,
        public ?string $text = null,
        public ?string $error = null,
        public ?string $label = null,
    ) {
        $this->onQueue('messenger-default');
    }

    public function handle(ConnectorManager $connectorManager): void
    {
        $child = RuntimeSession::find($this->childSessionId);
        $parent = RuntimeSession::with('chatSession')->find($this->parentSessionId);

        if ($parent === null || $parent->chatSession === null) {
            Log::warning('SubAgentCompletionJob: parent session or chat session not found', [
                'child_session_id' => $this->childSessionId,
                'parent_session_id' => $this->parentSessionId,
            ]);

            return;
        }

        $chatSession = $parent->chatSession;
        $connectorAccountId = $chatSession->connector_account_id;
        $account = ConnectorAccount::find($connectorAccountId);

        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        $content = $this->buildAnnouncement();

        try {
            $adapter->sendMessage($chatSession, new OutboundPayload(
                content: $content,
                channelId: $chatSession->channel_id,
                threadId: $chatSession->thread_id,
            ));
        } catch (\Throwable $e) {
            Log::error('SubAgentCompletionJob: Failed to send announcement', [
                'child_session_id' => $this->childSessionId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($child !== null) {
            $child->update([
                'status' => RuntimeSessionStatus::Stopped,
                'ended_at' => now(),
            ]);
        }
    }

    private function buildAnnouncement(): string
    {
        $label = $this->label ?? 'Sub-agent';
        $duration = $this->calculateDuration();

        if ($this->status === 'completed') {
            $text = $this->text !== null
                ? $this->truncateText($this->text)
                : 'No output.';

            return "**Sub-agent completed:** {$label}\n**Status:** completed\n**Duration:** {$duration}\n\n{$text}";
        }

        $errorMsg = $this->error ?? 'Unknown error.';

        return "**Sub-agent failed:** {$label} — {$errorMsg}";
    }

    private function truncateText(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_TEXT_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH).'…';
    }

    private function calculateDuration(): string
    {
        $child = RuntimeSession::find($this->childSessionId);
        if ($child === null || $child->started_at === null) {
            return 'unknown';
        }

        $seconds = (int) now()->diffInSeconds($child->started_at);
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }

    public function tags(): array
    {
        return [
            'runtime',
            'subagent-completion',
            'child:'.$this->childSessionId,
            'parent:'.$this->parentSessionId,
        ];
    }
}
