<?php

namespace App\Jobs\Org;

use App\Events\DelegationGraphStarted;
use App\Events\Office\AgentActivityChanged;
use App\Models\DelegationGraph;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Support\Delegation\DelegationGraphBuilder;
use App\Support\Delegation\GraphStateTransitionService;
use App\Support\Org\OrgRitualRunService;
use App\Support\Org\RitualToDelegationMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class OrgExecuteRitualJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 600;

    public function __construct(
        public readonly OrgRitualTemplate $template,
        public readonly ?OrgRitualRun $existingRun = null,
    ) {
        $this->onQueue('org-rituals');
    }

    public function handle(
        OrgRitualRunService $runService,
        RitualToDelegationMapper $mapper,
        DelegationGraphBuilder $graphBuilder,
        GraphStateTransitionService $transitionService,
    ): void {
        $run = $this->existingRun ?? $runService->createRun($this->template);

        try {
            $runService->markRunning($run);

            $this->broadcastActivity('ritual.started', [
                'ritual_name' => $this->template->name,
                'run_id' => $run->id,
            ]);

            $taskDefs = $mapper->mapPhasesToTasks($this->template, $run);

            if (empty($taskDefs)) {
                $runService->markFailed($run);

                return;
            }

            // Build a map from phase ID → task name so depends_on references resolve correctly.
            // The mapper returns phase IDs in depends_on, but the graph builder matches by task name.
            $idToName = [];
            foreach ($taskDefs as $t) {
                $idToName[$t['id']] = $t['name'];
            }

            $graphInput = [
                'tasks' => array_map(fn (array $t) => [
                    'name' => $t['name'],
                    'contract' => $t['contract'],
                    'depends_on' => array_map(
                        fn (string $depId) => $idToName[$depId] ?? $depId,
                        $t['depends_on'],
                    ),
                ], $taskDefs),
            ];

            $graph = $graphBuilder->build($this->template->user, $graphInput);

            $run->update(['delegation_graph_id' => $graph->id]);

            // Transition draft → ready → running, then fire the start event
            // so the DelegationCoordinator picks up root tasks.
            $transitionService->transition(
                $graph->id,
                [DelegationGraph::STATUS_DRAFT],
                DelegationGraph::STATUS_READY,
            );

            $transitionService->transition(
                $graph->id,
                [DelegationGraph::STATUS_READY],
                DelegationGraph::STATUS_RUNNING,
                ['started_at' => now('UTC')],
            );

            DelegationGraphStarted::dispatch($graph->fresh());

            $this->broadcastActivity('delegation.graph_created', [
                'graph_id' => $graph->id,
                'task_count' => $graph->tasks->count(),
                'ritual_name' => $this->template->name,
            ]);
        } catch (Throwable $e) {
            report($e);
            $runService->markFailed($run);

            $this->broadcastActivity('ritual.failed', [
                'ritual_name' => $this->template->name,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'org:ritual',
            "template:{$this->template->id}",
            "user:{$this->template->user_id}",
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    private function broadcastActivity(string $eventType, array $payload): void
    {
        try {
            AgentActivityChanged::dispatch(
                $this->template->user_id,
                $eventType,
                $payload,
            );
        } catch (Throwable) {
            // Broadcasting is best-effort
        }
    }
}
