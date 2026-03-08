<?php

declare(strict_types=1);

namespace App\Listeners\Org;

use App\Events\DelegationTaskVerified;
use App\Models\DelegationTask;
use App\Models\OrgCouncilTemplate;
use App\Models\OrgRitualRun;
use App\Support\Org\OrgCouncilService;
use Throwable;

/**
 * Triggers council deliberation when the adversarial review phase
 * of a ritual-linked delegation graph completes successfully.
 *
 * Collects outputs from all preceding reviewer tasks, runs deterministic
 * synthesis via the council, and injects the result into the report
 * synthesis task's context.
 */
class RitualCouncilDeliberationListener
{
    public function __construct(
        private readonly OrgCouncilService $councilService,
    ) {}

    public function handle(DelegationTaskVerified $event): void
    {
        if (! $event->passed) {
            return;
        }

        $task = $event->task->fresh(['graph']);
        if ($task === null || $task->graph === null) {
            return;
        }

        if ($task->name !== 'Adversarial Review') {
            return;
        }

        $ritualRun = OrgRitualRun::where('delegation_graph_id', $task->graph->id)->first();
        if ($ritualRun === null) {
            return;
        }

        $council = OrgCouncilTemplate::where('user_id', $task->graph->user_id)
            ->whereNull('archived_at')
            ->first();

        if ($council === null) {
            return;
        }

        try {
            $this->runDeliberation($task, $council, $ritualRun);
        } catch (Throwable) {
            // Best-effort -- don't block the delegation pipeline
        }
    }

    private function runDeliberation(
        DelegationTask $adversarialTask,
        OrgCouncilTemplate $council,
        OrgRitualRun $ritualRun,
    ): void {
        $graph = $adversarialTask->graph;

        $completedTasks = DelegationTask::where('delegation_graph_id', $graph->id)
            ->where('status', DelegationTask::STATUS_SUCCEEDED)
            ->get();

        $memberResponses = $completedTasks->map(fn (DelegationTask $t) => [
            'agent_id' => $t->assigned_delegatee_profile_id,
            'task_name' => $t->name,
            'decision' => $t->name === 'Adversarial Review' ? 'challenge' : 'approve',
            'findings_summary' => $t->result_json['summary'] ?? $t->name.' completed',
            'severity_vote' => $t->name === 'Adversarial Review' ? 'high' : 'medium',
        ])->values()->all();

        $result = $this->councilService->synthesize($council, $memberResponses);

        $reportTask = DelegationTask::where('delegation_graph_id', $graph->id)
            ->where('name', 'Report Synthesis')
            ->first();

        if ($reportTask) {
            $contract = $reportTask->contract_json ?? [];
            $contract['context_inputs'] = array_merge($contract['context_inputs'] ?? [], [
                'council_deliberation' => $result->toArray(),
                'council_name' => $council->name,
                'council_synthesis_mode' => $council->synthesis_mode,
            ]);
            $reportTask->update(['contract_json' => $contract]);
        }

        $outputs = $ritualRun->phase_outputs ?? [];
        $outputs['council_deliberation'] = $result->toArray();
        $ritualRun->update(['phase_outputs' => $outputs]);
    }
}
