<?php

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\ResolveEscalationRequest;
use App\Models\OrgEscalation;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Org\OrgEscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrgEscalationController extends Controller
{
    public function __construct(
        private readonly OrgEscalationService $escalationService
    ) {}

    /**
     * List pending escalations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->escalationReadTablesExist()) {
            return response()->json([
                'data' => [],
            ]);
        }

        try {
            $user = $request->user();

            $escalations = $this->escalationService->getUserPendingEscalations($user->id);

            return response()->json([
                'data' => $escalations->map(fn (OrgEscalation $escalation) => [
                    'id' => $escalation->id,
                    'ritual_run_id' => $escalation->ritual_run_id,
                    'escalation_type' => $escalation->escalation_type,
                    'escalated_to_agent_id' => $escalation->escalated_to_agent_id,
                    'escalated_to_agent' => $escalation->escalatedToAgent,
                    'state' => $escalation->state,
                    'timeout_at' => $escalation->timeout_at?->toIso8601String(),
                    'created_at' => $escalation->created_at->toIso8601String(),
                ]),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'data' => [],
            ]);
        }
    }

    /**
     * Resolve an escalation (approve or reject).
     */
    public function resolve(ResolveEscalationRequest $request, string $id): JsonResponse
    {
        $escalation = OrgEscalation::find($id);

        if ($escalation === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Escalation not found.', 404);
        }

        // Verify user owns the ritual run
        if ($escalation->ritualRun->user_id !== $request->user()->id) {
            return ErrorEnvelope::make('FORBIDDEN', 'You do not have permission to resolve this escalation.', 403);
        }

        if (! $escalation->isPending()) {
            return ErrorEnvelope::make('INVALID_STATE', 'Escalation is not pending.', 422);
        }

        $resolution = $request->validated('resolution');
        $resolvedBy = $request->user()->email ?? $request->user()->id;
        $notes = $request->validated('notes');

        $escalation = $this->escalationService->resolve(
            $escalation,
            $resolution,
            $resolvedBy,
            $notes
        );

        return response()->json([
            'data' => [
                'id' => $escalation->id,
                'state' => $escalation->state,
                'resolved_by' => $escalation->resolved_by,
                'resolved_at' => $escalation->resolved_at?->toIso8601String(),
                'resolution_notes' => $escalation->resolution_notes,
            ],
        ]);
    }

    private function escalationReadTablesExist(): bool
    {
        return Schema::hasTable('org_escalations')
            && Schema::hasTable('org_ritual_runs');
    }
}
