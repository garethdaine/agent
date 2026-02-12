<?php

namespace App\Console\Commands;

use App\Support\Agent\DispatchDueService;
use App\Support\Agent\ReconcileActiveRunsService;
use Illuminate\Console\Command;

class AgentDispatchDueCommand extends Command
{
    private static bool $bootReconciled = false;

    protected $signature = 'agent:dispatch-due';

    protected $description = 'Dispatch due agent jobs for the current scheduler tick';

    public function handle(DispatchDueService $dispatchDueService, ReconcileActiveRunsService $reconcile): int
    {
        if (! self::$bootReconciled) {
            $reconcile->reconcile('boot');
            self::$bootReconciled = true;
        }

        $result = $dispatchDueService->dispatch();

        $this->info(sprintf(
            'Dispatched=%d skipped_overlap=%d skipped_cooldown=%d deferred=%d errors=%d watermark=%s',
            $result['dispatched_count'],
            $result['skipped_overlap_count'],
            $result['skipped_cooldown_count'],
            $result['deferred_capacity_count'],
            $result['error_count'],
            $result['watermark_minute_utc'],
        ));

        return self::SUCCESS;
    }
}
