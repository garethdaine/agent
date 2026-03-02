<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DelegationGraphCycleException;
use App\Exceptions\DelegationGraphTaskLimitException;
use App\Http\Controllers\Controller;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Delegation\ContractValidator;
use App\Support\Delegation\DelegationGraphBuilder;
use App\Support\Delegation\GraphStateTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationGraphController extends Controller
{
    public function __construct(
        private DelegationGraphBuilder $graphBuilder,
        private ContractValidator $contractValidator,
        private GraphStateTransitionService $stateTransitionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->delegationGraphs()->newQuery();

        $deleted = $request->string('deleted')->toString();
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $status = strtolower(trim($request->string('status')->toString()));
        if ($status !== '' && in_array($status, [
            DelegationGraph::STATUS_DRAFT,
            DelegationGraph::STATUS_VALIDATING,
            DelegationGraph::STATUS_READY,
            DelegationGraph::STATUS_RUNNING,
            DelegationGraph::STATUS_SUCCEEDED,
            DelegationGraph::STATUS_FAILED,
            DelegationGraph::STATUS_PARTIAL,
            DelegationGraph::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $dir = strtolower($request->string('dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'status'], true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $graphs = $query->paginate($perPage)->withQueryString();

        $data = collect($graphs->items())->map(fn (DelegationGraph $graph): array => $this->transformGraph($graph, false))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $graphs->currentPage(),
                'per_page' => $graphs->perPage(),
                'total' => $graphs->total(),
                'last_page' => $graphs->lastPage(),
            ],
            'links' => [
                'first' => $graphs->url(1),
                'last' => $graphs->url($graphs->lastPage()),
                'prev' => $graphs->previousPageUrl(),
                'next' => $graphs->nextPageUrl(),
            ],
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'deleted' => $request->input('deleted'),
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        return response()->json([
            'data' => $this->transformGraph($graph, true),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.name' => ['required', 'string', 'max:255'],
            'tasks.*.contract' => ['required', 'array'],
            'tasks.*.depends_on' => ['sometimes', 'array'],
            'format' => ['sometimes', 'string', 'in:linear_chain,dag'],
            'max_parallel_tasks' => ['sometimes', 'integer', 'min:1', 'max:15'],
        ]);

        $format = $validated['format'] ?? 'linear_chain';

        // Build input structure based on format
        if ($format === 'dag') {
            $input = ['tasks' => $validated['tasks']];
        } else {
            $input = $validated['tasks'];
        }

        try {
            $graph = $this->graphBuilder->build($request->user(), $input);

            // Update graph with provided name and description
            $graph->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'max_parallel_tasks' => $validated['max_parallel_tasks'] ?? config('delegation.default_max_parallel_tasks', 5),
            ]);

            $auditLogger->recordUserAction(
                request: $request,
                action: 'delegation_graph.create',
                targetType: 'delegation_graph',
                targetId: (int) $graph->id,
                ownerUserId: (int) $graph->user_id,
                changedFields: array_keys($graph->getAttributes()),
                before: null,
                after: $graph->only(['id', 'name', 'status', 'max_parallel_tasks']),
            );

            return response()->json([
                'data' => $this->transformGraph($graph->fresh()->load('tasks'), true),
            ], 201);
        } catch (DelegationGraphCycleException $e) {
            return ErrorEnvelope::make('CYCLE_DETECTED', $e->getMessage(), 422, [
                'cycle_nodes' => $e->cyclePath ?? [],
            ]);
        } catch (DelegationGraphTaskLimitException $e) {
            return ErrorEnvelope::make('TASK_LIMIT_EXCEEDED', $e->getMessage(), 422);
        }
    }

    public function update(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        if ($graph->status !== DelegationGraph::STATUS_DRAFT) {
            return ErrorEnvelope::make('INVALID_STATE', 'Graph can only be updated in draft status.', 409);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_parallel_tasks' => ['sometimes', 'integer', 'min:1', 'max:15'],
        ]);

        $before = $graph->only(['name', 'description', 'max_parallel_tasks']);

        $graph->fill($validated);
        $graph->save();

        $after = $graph->only(array_keys($before));
        $changedFields = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'delegation_graph.update',
                targetType: 'delegation_graph',
                targetId: (int) $graph->id,
                ownerUserId: (int) $graph->user_id,
                changedFields: $changedFields,
                before: $before,
                after: $after,
            );
        }

        return response()->json([
            'data' => $this->transformGraph($graph, true),
        ]);
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        if (! in_array($graph->status, DelegationGraph::TERMINAL_STATUSES, true)) {
            return ErrorEnvelope::make('INVALID_STATE', 'Only terminal graphs can be deleted.', 409);
        }

        $before = ['deleted_at' => optional($graph->deleted_at)?->toIso8601String()];
        $graph->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_graph.delete',
            targetType: 'delegation_graph',
            targetId: (int) $graph->id,
            ownerUserId: (int) $graph->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($graph->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $graph->id,
            ],
        ]);
    }

    public function restore(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $before = ['deleted_at' => optional($graph->deleted_at)?->toIso8601String()];

        if ($graph->trashed()) {
            $graph->restore();
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_graph.restore',
            targetType: 'delegation_graph',
            targetId: (int) $graph->id,
            ownerUserId: (int) $graph->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($graph->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => $this->transformGraph($graph->fresh(), false),
        ]);
    }

    public function validate(Request $request, int $id): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $allErrors = [];
        $allWarnings = [];

        foreach ($graph->tasks as $task) {
            $contractJson = $task->contract_json ?? [];
            $result = $this->contractValidator->validate($contractJson);

            if (! $result->isValid()) {
                foreach ($result->errors() as $error) {
                    $allErrors[] = "Task '{$task->name}': {$error}";
                }
            }

            foreach ($result->warnings() as $warning) {
                $allWarnings[] = "Task '{$task->name}': {$warning}";
            }
        }

        $isValid = count($allErrors) === 0;
        if ($isValid) {
            $this->stateTransitionService->transition(
                graphId: (int) $graph->id,
                fromStatuses: [DelegationGraph::STATUS_DRAFT, DelegationGraph::STATUS_VALIDATING],
                toStatus: DelegationGraph::STATUS_READY,
            );
            $graph->refresh();
        }

        return response()->json([
            'data' => [
                'valid' => $isValid,
                'errors' => $allErrors,
                'warnings' => $allWarnings,
                'status' => $graph->status,
            ],
        ]);
    }

    public function start(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        if ($graph->status !== DelegationGraph::STATUS_READY) {
            return ErrorEnvelope::make('INVALID_STATE', 'Graph must be in ready status to start.', 409);
        }

        $transitioned = $this->stateTransitionService->transition(
            graphId: $graph->id,
            fromStatuses: [DelegationGraph::STATUS_READY],
            toStatus: DelegationGraph::STATUS_RUNNING,
            attributes: ['started_at' => now('UTC')],
        );

        if (! $transitioned) {
            return ErrorEnvelope::make('TRANSITION_FAILED', 'Failed to start graph.', 409);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_graph.start',
            targetType: 'delegation_graph',
            targetId: (int) $graph->id,
            ownerUserId: (int) $graph->user_id,
            changedFields: ['status', 'started_at'],
            before: ['status' => DelegationGraph::STATUS_READY],
            after: ['status' => DelegationGraph::STATUS_RUNNING],
        );

        return response()->json([
            'data' => $this->transformGraph($graph->fresh(), false),
        ], 202);
    }

    public function cancel(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        if (! in_array($graph->status, DelegationGraph::ACTIVE_STATUSES, true)) {
            return ErrorEnvelope::make('INVALID_STATE', 'Only active graphs can be cancelled.', 409);
        }

        $transitioned = $this->stateTransitionService->transition(
            graphId: $graph->id,
            fromStatuses: DelegationGraph::ACTIVE_STATUSES,
            toStatus: DelegationGraph::STATUS_CANCELLED,
            attributes: ['finished_at' => now('UTC')],
        );

        if (! $transitioned) {
            return ErrorEnvelope::make('TRANSITION_FAILED', 'Failed to cancel graph.', 409);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_graph.cancel',
            targetType: 'delegation_graph',
            targetId: (int) $graph->id,
            ownerUserId: (int) $graph->user_id,
            changedFields: ['status', 'finished_at'],
            before: ['status' => $graph->status],
            after: ['status' => DelegationGraph::STATUS_CANCELLED],
        );

        return response()->json([
            'data' => $this->transformGraph($graph->fresh(), false),
        ], 202);
    }

    public function clone(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $validated = $request->validate([
            'mode' => ['sometimes', 'string', 'in:all,failed_subtree'],
        ]);

        $mode = $validated['mode'] ?? 'all';

        // Build clone tasks based on mode
        $tasksToClone = $graph->tasks;
        if ($mode === 'failed_subtree') {
            // Only clone failed tasks and their dependents
            $failedTaskIds = $graph->tasks
                ->filter(fn (DelegationTask $task) => $task->status === DelegationTask::STATUS_FAILED)
                ->pluck('id')
                ->toArray();

            $tasksToClone = $graph->tasks
                ->filter(fn (DelegationTask $task) => in_array($task->id, $failedTaskIds, true)
                    || $task->status === DelegationTask::STATUS_FAILED);
        }

        // Create clone input
        $cloneTasks = [];
        foreach ($tasksToClone as $task) {
            $cloneTasks[] = [
                'name' => $task->name,
                'contract' => $task->contract_json ?? [],
                'depends_on' => $task->dependencies->pluck('name')->toArray(),
            ];
        }

        $newGraph = $this->graphBuilder->build($request->user(), ['tasks' => $cloneTasks]);
        $newGraph->update([
            'name' => $graph->name.' (Clone)',
            'description' => $graph->description,
            'metadata_json' => [
                'cloned_from' => $graph->id,
                'clone_mode' => $mode,
            ],
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_graph.clone',
            targetType: 'delegation_graph',
            targetId: (int) $newGraph->id,
            ownerUserId: (int) $newGraph->user_id,
            changedFields: array_keys($newGraph->getAttributes()),
            before: null,
            after: ['cloned_from' => $graph->id, 'mode' => $mode],
        );

        return response()->json([
            'data' => $this->transformGraph($newGraph->fresh()->load('tasks'), true),
        ], 201);
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($id);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $afterSequence = (int) $request->integer('after_sequence', 0);
        $limit = min(100, max(1, (int) $request->integer('limit', 50)));

        $events = $graph->events()
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence', 'asc')
            ->take($limit)
            ->get();

        $data = $events->map(fn ($event) => [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'sequence' => $event->sequence,
            'payload' => $event->payload_json,
            'task_id' => $event->delegation_task_id,
            'event_ts' => optional($event->event_ts)?->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'returned' => $data->count(),
                'after_sequence' => $afterSequence,
            ],
        ]);
    }

    private function transformGraph(DelegationGraph $graph, bool $includeTasks = false): array
    {
        $taskCounts = null;
        if ($graph->relationLoaded('tasks') || $includeTasks) {
            $tasks = $graph->tasks;
            $taskCounts = [
                'total' => $tasks->count(),
                'completed' => $tasks->whereIn('status', [
                    DelegationTask::STATUS_SUCCEEDED,
                    DelegationTask::STATUS_FAILED,
                    DelegationTask::STATUS_CANCELLED,
                ])->count(),
                'running' => $tasks->where('status', DelegationTask::STATUS_RUNNING)->count(),
            ];
        }

        $payload = [
            'id' => $graph->id,
            'name' => $graph->name,
            'description' => $graph->description,
            'status' => $graph->status,
            'cancellation_policy' => $graph->cancellation_policy,
            'max_parallel_tasks' => $graph->max_parallel_tasks,
            'error_code' => $graph->error_code,
            'error_summary' => $graph->error_summary,
            'metadata' => $graph->metadata_json,
            'started_at' => optional($graph->started_at)?->toIso8601String(),
            'finished_at' => optional($graph->finished_at)?->toIso8601String(),
            'deleted_at' => optional($graph->deleted_at)?->toIso8601String(),
            'created_at' => optional($graph->created_at)?->toIso8601String(),
            'updated_at' => optional($graph->updated_at)?->toIso8601String(),
            'task_counts' => $taskCounts,
        ];

        if ($includeTasks && $graph->relationLoaded('tasks')) {
            $payload['tasks'] = $graph->tasks->map(fn (DelegationTask $task) => [
                'id' => $task->id,
                'name' => $task->name,
                'status' => $task->status,
                'sequence_order' => $task->sequence_order,
                'assigned_profile_id' => $task->assigned_delegatee_profile_id,
                'error_code' => $task->error_code,
                'error_summary' => $task->error_summary,
                'started_at' => optional($task->started_at)?->toIso8601String(),
                'finished_at' => optional($task->finished_at)?->toIso8601String(),
            ])->values();
        }

        return $payload;
    }
}
