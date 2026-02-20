<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\TaskProviders\InterrogationTaskProviderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncInterrogationTasksToTaskProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $sessionId)
    {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(InterrogationTaskProviderSyncService $syncService): void
    {
        $session = InterrogationSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        try {
            $syncService->syncBuildTasks($session);
        } catch (\Throwable $throwable) {
            report($throwable);
            $syncService->markSyncFailed($session, $throwable->getMessage());
        }
    }
}
