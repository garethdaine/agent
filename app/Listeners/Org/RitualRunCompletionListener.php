<?php

namespace App\Listeners\Org;

use App\Events\DelegationGraphCompleted;
use App\Models\DelegationGraph;
use App\Models\OrgRitualRun;
use App\Support\Org\OrgRitualRunService;

class RitualRunCompletionListener
{
    public function __construct(
        private readonly OrgRitualRunService $runService
    ) {}

    public function handle(DelegationGraphCompleted $event): void
    {
        $run = OrgRitualRun::where('delegation_graph_id', $event->graph->id)
            ->whereIn('state', OrgRitualRun::ACTIVE_STATES)
            ->first();

        if ($run === null) {
            return;
        }

        match ($event->finalStatus) {
            DelegationGraph::STATUS_SUCCEEDED => $this->runService->markSucceeded($run),
            DelegationGraph::STATUS_FAILED => $this->runService->markFailed($run),
            DelegationGraph::STATUS_PARTIAL => $this->runService->markPartial($run),
            DelegationGraph::STATUS_CANCELLED => $run->update([
                'state' => OrgRitualRun::STATE_CANCELLED,
                'completed_at' => now(),
            ]),
            default => null,
        };
    }
}
