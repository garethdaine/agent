<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Support\Facades\Log;

class SubAgentSpawner
{
    /**
     * @return array{status: 'spawned', child_session_id: string}|array{status: 'rejected', reason: string}
     */
    public function spawn(
        RuntimeSession $parentSession,
        string $task,
        ?string $label = null,
        ?string $model = null,
    ): array {
        if (! config('runtime.subagents.enabled', false)) {
            return ['status' => 'rejected', 'reason' => 'Sub-agent spawning is disabled.'];
        }

        $maxDepth = (int) config('runtime.subagents.max_spawn_depth', 1);
        if ($parentSession->spawn_depth >= $maxDepth) {
            return ['status' => 'rejected', 'reason' => "Maximum spawn depth ({$maxDepth}) exceeded."];
        }

        $maxConcurrent = (int) config('runtime.subagents.max_concurrent_per_session', 4);
        $activeChildren = $parentSession->children()
            ->where('status', RuntimeSessionStatus::Active)
            ->count();

        if ($activeChildren >= $maxConcurrent) {
            return ['status' => 'rejected', 'reason' => "Maximum concurrent sub-agents ({$maxConcurrent}) reached."];
        }

        $child = RuntimeSession::create([
            'user_id' => $parentSession->user_id,
            'team_id' => $parentSession->team_id,
            'chat_session_id' => $parentSession->chat_session_id,
            'parent_session_id' => $parentSession->id,
            'spawn_depth' => $parentSession->spawn_depth + 1,
            'status' => RuntimeSessionStatus::Active,
            'mode' => $parentSession->mode ?? RuntimeMode::Safe,
            'title' => $label,
            'workspace_root' => $parentSession->workspace_root,
            'started_at' => now(),
        ]);

        $connectorAccountId = $parentSession->chatSession?->connector_account_id;
        $queue = config('runtime.subagents.queue', 'subagent');

        ProcessRuntimeTurnJob::dispatch(
            runtimeSessionId: $child->id,
            userMessage: $task,
            chatSessionId: $parentSession->chat_session_id,
            connectorAccountId: $connectorAccountId,
        )->onQueue($queue);

        Log::channel('runtime')->info('SubAgentSpawner: spawned sub-agent', [
            'parent_session_id' => $parentSession->id,
            'child_session_id' => $child->id,
            'label' => $label,
            'spawn_depth' => $child->spawn_depth,
            'queue' => $queue,
        ]);

        return [
            'status' => 'spawned',
            'child_session_id' => $child->id,
        ];
    }
}
