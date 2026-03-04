<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Services\Escalation\WorkflowGovernanceService;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class WorkflowGovernanceController extends Controller
{
    public function pause(
        Request $request,
        string $workflowKey,
        WorkflowGovernanceService $governanceService,
    ): JsonResponse {
        $job = $this->findJob($workflowKey);

        if ($job === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Workflow not found.', 404, [
                'workflow_key' => [$workflowKey],
            ]);
        }

        $payload = $this->validatePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $actorId = (string) $request->user()?->id;
        $isForce = (bool) ($payload['force'] ?? false);
        $ability = $isForce ? 'workflow.override' : 'workflow.pause';

        $decision = Gate::forUser($request->user())->inspect($ability, $job);
        if (! $decision->allowed()) {
            $governanceService->recordDeniedAttempt(
                job: $job,
                actorId: $actorId,
                newState: 'paused',
                reason: (string) $payload['reason'],
                runId: (string) $payload['run_id'],
                action: $isForce ? 'force_pause' : 'pause',
            );

            return ErrorEnvelope::make('FORBIDDEN', 'You do not have permission to pause this workflow.', 403);
        }

        $job = $governanceService->pauseWorkflow(
            job: $job,
            actorId: $actorId,
            reason: (string) $payload['reason'],
            runId: (string) $payload['run_id'],
            force: $isForce,
        );

        return response()->json([
            'data' => $this->transform($job),
        ]);
    }

    public function resume(
        Request $request,
        string $workflowKey,
        WorkflowGovernanceService $governanceService,
    ): JsonResponse {
        $job = $this->findJob($workflowKey);

        if ($job === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Workflow not found.', 404, [
                'workflow_key' => [$workflowKey],
            ]);
        }

        $payload = $this->validatePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $actorId = (string) $request->user()?->id;
        $isForce = (bool) ($payload['force'] ?? false);
        $ability = $isForce ? 'workflow.override' : 'workflow.resume';

        $decision = Gate::forUser($request->user())->inspect($ability, $job);
        if (! $decision->allowed()) {
            $governanceService->recordDeniedAttempt(
                job: $job,
                actorId: $actorId,
                newState: 'active',
                reason: (string) $payload['reason'],
                runId: (string) $payload['run_id'],
                action: $isForce ? 'force_resume' : 'resume',
            );

            return ErrorEnvelope::make('FORBIDDEN', 'You do not have permission to resume this workflow.', 403);
        }

        $job = $governanceService->resumeWorkflow(
            job: $job,
            actorId: $actorId,
            reason: (string) $payload['reason'],
            runId: (string) $payload['run_id'],
            force: $isForce,
        );

        return response()->json([
            'data' => $this->transform($job),
        ]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validatePayload(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:3', 'max:4000'],
            'run_id' => ['required', 'string', 'max:191'],
            'force' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelope::make(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                $validator->errors()->toArray(),
            );
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        return $validated;
    }

    private function findJob(string $workflowKey): ?AgentJob
    {
        return AgentJob::query()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AgentJob $job): array
    {
        return [
            'id' => (int) $job->id,
            'workflow_key' => (string) $job->workflow_key,
            'governance_paused' => $job->governance_paused_at !== null,
            'governance_paused_at' => $job->governance_paused_at?->toIso8601String(),
            'governance_pause_reason' => $job->governance_pause_reason,
            'governance_paused_by' => $job->governance_paused_by,
        ];
    }
}
