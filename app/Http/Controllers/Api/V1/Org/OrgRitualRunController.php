<?php

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Jobs\Org\OrgExecuteRitualJob;
use App\Models\OrgRitualRun;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Org\OrgRitualRunService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgRitualRunController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OrgRitualRunService $runService
    ) {}

    /**
     * List ritual runs with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrgRitualRun::forUser($request->user()->id);

        // Filter by template if provided
        if ($request->has('template_id')) {
            $query->forTemplate($request->input('template_id'));
        }

        // Filter by state if provided
        if ($request->has('state')) {
            $query->where('state', $request->input('state'));
        }

        // Filter by date range if provided
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Paginate results
        $perPage = $request->integer('per_page', 15);
        $runs = $query->with('template')->paginate($perPage);

        return response()->json([
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    /**
     * Show a ritual run with phase outputs.
     */
    public function show(string $id): JsonResponse
    {
        $run = OrgRitualRun::with('template')->find($id);

        if ($run === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual run not found.', 404);
        }

        $this->authorize('view', $run);

        return response()->json([
            'data' => $run,
        ]);
    }

    /**
     * Retry failed phases from a partial/failed run.
     */
    public function retry(string $id): JsonResponse
    {
        $originalRun = OrgRitualRun::with('template')->find($id);

        if ($originalRun === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual run not found.', 404);
        }

        $this->authorize('retry', $originalRun);

        // Only allow retry on failed or partial runs
        if (! in_array($originalRun->state, [OrgRitualRun::STATE_FAILED, OrgRitualRun::STATE_PARTIAL], true)) {
            return ErrorEnvelope::make(
                'INVALID_STATE',
                'Can only retry failed or partial runs.',
                422
            );
        }

        // Create new run preserving successful phase outputs
        $newRun = $this->runService->retryFailedPhases($originalRun);

        // Dispatch execution job for the new run
        OrgExecuteRitualJob::dispatch($originalRun->template)->onQueue('org-rituals');

        return response()->json(['data' => $newRun], 201);
    }
}
