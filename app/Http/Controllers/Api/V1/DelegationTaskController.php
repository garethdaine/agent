<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DelegationTaskVerified;
use App\Http\Controllers\Controller;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DelegationTaskController extends Controller
{
    public function index(Request $request, int $graphId): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($graphId);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $query = $graph->tasks()->newQuery();

        $status = strtolower(trim($request->string('status')->toString()));
        if ($status !== '' && in_array($status, [
            DelegationTask::STATUS_PENDING,
            DelegationTask::STATUS_BLOCKED,
            DelegationTask::STATUS_READY,
            DelegationTask::STATUS_ASSIGNED,
            DelegationTask::STATUS_RUNNING,
            DelegationTask::STATUS_VERIFYING,
            DelegationTask::STATUS_SUCCEEDED,
            DelegationTask::STATUS_FAILED,
            DelegationTask::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $sort = $request->string('sort', 'sequence_order')->toString();
        $dir = strtolower($request->string('dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'sequence_order', 'status', 'created_at'], true)) {
            $sort = 'sequence_order';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));
        $tasks = $query->with(['attempts', 'verificationResults', 'assignedProfile'])->paginate($perPage)->withQueryString();

        $data = collect($tasks->items())->map(fn (DelegationTask $task): array => $this->transformTask($task))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'last_page' => $tasks->lastPage(),
            ],
            'links' => [
                'first' => $tasks->url(1),
                'last' => $tasks->url($tasks->lastPage()),
                'prev' => $tasks->previousPageUrl(),
                'next' => $tasks->nextPageUrl(),
            ],
            'filters' => [
                'status' => $request->input('status'),
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, int $graphId, int $taskId): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->withTrashed()->find($graphId);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $task = $graph->tasks()
            ->with(['attempts', 'verificationResults', 'assignedProfile', 'dependencies', 'dependents'])
            ->find($taskId);

        if ($task === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Task not found.', 404);
        }

        return response()->json([
            'data' => $this->transformTask($task, true),
        ]);
    }

    public function resolveVerification(Request $request, int $graphId, int $taskId, AuditLogger $auditLogger): JsonResponse
    {
        $graph = $request->user()->delegationGraphs()->find($graphId);

        if ($graph === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Graph not found.', 404);
        }

        $task = $graph->tasks()->find($taskId);

        if ($task === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Task not found.', 404);
        }

        $validated = $request->validate([
            'verification_result_id' => ['required', 'integer'],
            'verdict' => ['required', 'string', Rule::in(['passed', 'failed'])],
            'evidence' => ['sometimes', 'array'],
        ]);

        $verificationResult = DelegationVerificationResult::query()
            ->where('delegation_task_id', $task->id)
            ->where('id', $validated['verification_result_id'])
            ->first();

        if ($verificationResult === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Verification result not found.', 404);
        }

        if ($verificationResult->verdict !== DelegationVerificationResult::VERDICT_PENDING) {
            return ErrorEnvelope::make('INVALID_STATE', 'Verification result is not pending.', 409);
        }

        $before = ['verdict' => $verificationResult->verdict];

        $newVerdict = $validated['verdict'] === 'passed'
            ? DelegationVerificationResult::VERDICT_PASSED
            : DelegationVerificationResult::VERDICT_FAILED;

        $verificationResult->update([
            'verdict' => $newVerdict,
            'evidence_json' => $validated['evidence'] ?? $verificationResult->evidence_json,
            'finished_at' => now('UTC'),
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegation_verification.resolve',
            targetType: 'delegation_verification_result',
            targetId: (int) $verificationResult->id,
            ownerUserId: (int) $graph->user_id,
            changedFields: ['verdict', 'finished_at'],
            before: $before,
            after: ['verdict' => $newVerdict],
        );

        // Fire event for pipeline continuation.
        $attempt = $verificationResult->attempt
            ?? $task->attempts()->latest('id')->first();

        if ($attempt !== null && class_exists(DelegationTaskVerified::class)) {
            event(new DelegationTaskVerified(
                $task,
                $attempt,
                passed: $newVerdict === DelegationVerificationResult::VERDICT_PASSED,
                failedStepOrder: $newVerdict === DelegationVerificationResult::VERDICT_FAILED
                    ? $verificationResult->step_order
                    : null,
            ));
        }

        return response()->json([
            'data' => [
                'id' => $verificationResult->id,
                'verdict' => $verificationResult->verdict,
                'step_type' => $verificationResult->step_type,
                'evidence' => $verificationResult->evidence_json,
                'finished_at' => optional($verificationResult->finished_at)?->toIso8601String(),
            ],
        ]);
    }

    private function transformTask(DelegationTask $task, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $task->id,
            'name' => $task->name,
            'status' => $task->status,
            'sequence_order' => $task->sequence_order,
            'assigned_profile_id' => $task->assigned_delegatee_profile_id,
            'assigned_profile_name' => $task->assignedProfile?->name,
            'error_code' => $task->error_code,
            'error_summary' => $task->error_summary,
            'started_at' => optional($task->started_at)?->toIso8601String(),
            'finished_at' => optional($task->finished_at)?->toIso8601String(),
            'created_at' => optional($task->created_at)?->toIso8601String(),
            'updated_at' => optional($task->updated_at)?->toIso8601String(),
        ];

        if ($task->relationLoaded('attempts')) {
            $payload['attempts'] = $task->attempts->map(fn (DelegationAttempt $attempt) => [
                'id' => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->status,
                'profile_id' => $attempt->delegatee_profile_id,
                'duration_ms' => $attempt->duration_ms,
                'error_code' => $attempt->error_code,
                'started_at' => optional($attempt->started_at)?->toIso8601String(),
                'finished_at' => optional($attempt->finished_at)?->toIso8601String(),
            ])->values();
        }

        if ($task->relationLoaded('verificationResults')) {
            $payload['verification_results'] = $task->verificationResults->map(fn (DelegationVerificationResult $result) => [
                'id' => $result->id,
                'step_type' => $result->step_type,
                'step_order' => $result->step_order,
                'verdict' => $result->verdict,
                'evidence' => $result->evidence_json,
                'expires_at' => optional($result->expires_at)?->toIso8601String(),
                'started_at' => optional($result->started_at)?->toIso8601String(),
                'finished_at' => optional($result->finished_at)?->toIso8601String(),
            ])->values();
        }

        if ($includeDetails) {
            $payload['contract'] = $task->contract_json;
            $payload['assignment_reason'] = $task->assignment_reason_json;
            $payload['metadata'] = $task->metadata_json;

            if ($task->relationLoaded('dependencies')) {
                $payload['dependencies'] = $task->dependencies->map(fn (DelegationTask $dep) => [
                    'id' => $dep->id,
                    'name' => $dep->name,
                    'status' => $dep->status,
                ])->values();
            }

            if ($task->relationLoaded('dependents')) {
                $payload['dependents'] = $task->dependents->map(fn (DelegationTask $dep) => [
                    'id' => $dep->id,
                    'name' => $dep->name,
                    'status' => $dep->status,
                ])->values();
            }
        }

        return $payload;
    }
}
