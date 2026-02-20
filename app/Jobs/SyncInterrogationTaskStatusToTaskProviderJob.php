<?php

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\InterrogationTaskProviderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncInterrogationTaskStatusToTaskProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $sessionId,
        public int $taskId,
    ) {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(InterrogationTaskProviderSyncService $syncService): void
    {
        $session = InterrogationSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        $task = InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->whereKey($this->taskId)
            ->first();

        if ($task === null) {
            return;
        }

        try {
            $syncService->syncTaskStatus($session, $task);
        } catch (\Throwable $throwable) {
            report($throwable);

            $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
            $links = is_array($metadata['task_provider_links'] ?? null) ? $metadata['task_provider_links'] : [];

            foreach ($links as $driver => $link) {
                if (! is_array($link)) {
                    continue;
                }

                $link['status_synced_at'] = now('UTC')->toIso8601String();
                $link['status_sync_error'] = mb_substr($throwable->getMessage(), 0, 1000);
                $links[$driver] = $link;
            }

            if ($links !== []) {
                $metadata['task_provider_links'] = $links;
                $task->metadata_json = $metadata;
                $task->save();
            }
        }
    }
}
