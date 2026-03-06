<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Runtime\SessionProcessManager;

final class ProgressCommandHandler implements SlashCommandHandlerInterface
{
    /**
     * @param  array<int, string>  $args
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $session = $this->resolveActiveSession($user, $chatSessionId);

        if ($session === null) {
            return CommandResult::success('No active session — nothing in progress.');
        }

        $progress = SessionProcessManager::getLiveProgress($session->id);

        if ($progress === null) {
            return CommandResult::success('No live progress data — the session may be idle or between turns.');
        }

        $lines = $this->formatProgress($progress);

        $childProgress = $this->gatherChildProgress($session);
        if ($childProgress !== []) {
            $lines[] = '';
            $lines[] = '**Sub-agents:**';
            foreach ($childProgress as $cp) {
                $lines[] = $cp;
            }
        }

        return CommandResult::success(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $progress
     * @return array<int, string>
     */
    private function formatProgress(array $progress): array
    {
        $elapsed = (int) ($progress['elapsed_seconds'] ?? 0);
        $fragments = (int) ($progress['fragment_count'] ?? 0);
        $textLength = (int) ($progress['text_length'] ?? 0);
        $recentActivity = (string) ($progress['recent_activity'] ?? 'unknown');
        $textPreview = (string) ($progress['text_preview'] ?? '');
        $updatedAt = $progress['updated_at'] ?? null;

        $duration = $this->formatDuration($elapsed);

        $lines = [
            "**Live Progress** ({$duration})",
            "**Fragments:** {$fragments} | **Output:** ".number_format($textLength).' chars',
            "**Recent:** {$recentActivity}",
        ];

        if ($updatedAt !== null) {
            $lines[] = "**Updated:** {$updatedAt}";
        }

        if ($textPreview !== '') {
            $preview = mb_strlen($textPreview) > 300
                ? '…'.mb_substr($textPreview, -300)
                : $textPreview;
            $lines[] = '';
            $lines[] = "**Preview:**\n> ".str_replace("\n", "\n> ", $preview);
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function gatherChildProgress(RuntimeSession $parentSession): array
    {
        $children = $parentSession->children()
            ->where('status', RuntimeSessionStatus::Active)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $lines = [];

        foreach ($children as $child) {
            $childProgress = SessionProcessManager::getLiveProgress($child->id);
            $shortId = substr($child->id, 0, 8);
            $title = $child->title ?? 'Untitled';

            if ($childProgress === null) {
                $lines[] = "• [{$shortId}] **{$title}** — Sub-agent active (no live data)";

                continue;
            }

            $elapsed = $this->formatDuration((int) ($childProgress['elapsed_seconds'] ?? 0));
            $fragments = (int) ($childProgress['fragment_count'] ?? 0);
            $activity = (string) ($childProgress['recent_activity'] ?? 'unknown');

            $lines[] = "• [{$shortId}] **{$title}** — {$elapsed}, {$fragments} fragments, recent: {$activity}";
        }

        return $lines;
    }

    private function resolveActiveSession(User $user, ?string $chatSessionId): ?RuntimeSession
    {
        $query = RuntimeSession::where('user_id', $user->id)
            ->where('status', RuntimeSessionStatus::Active)
            ->whereNull('parent_session_id');

        if ($chatSessionId !== null) {
            $query->where('chat_session_id', $chatSessionId);
        }

        return $query->orderByDesc('started_at')->first();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds % 60;

        return "{$minutes}m {$remaining}s";
    }
}
