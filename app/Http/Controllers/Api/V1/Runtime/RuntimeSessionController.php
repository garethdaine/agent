<?php

namespace App\Http\Controllers\Api\V1\Runtime;

use App\Http\Controllers\Controller;
use App\Models\Runtime\RuntimeSession;
use App\Services\Runtime\RuntimeSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuntimeSessionController extends Controller
{
    public function __construct(
        private RuntimeSessionManager $sessionManager
    ) {}

    /**
     * List all runtime sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RuntimeSession::query()
            ->forUser($request->user())
            ->orderBy('started_at', 'desc');

        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $sessions = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
            'links' => [
                'first' => $sessions->url(1),
                'last' => $sessions->url($sessions->lastPage()),
                'prev' => $sessions->previousPageUrl(),
                'next' => $sessions->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show a specific runtime session with turns and policy snapshots.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $session = RuntimeSession::query()
            ->forUser($request->user())
            ->with(['turns.toolCalls', 'policySnapshots'])
            ->findOrFail($id);

        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Stop an active runtime session.
     */
    public function stop(Request $request, string $id): JsonResponse
    {
        $session = RuntimeSession::query()->forUser($request->user())->findOrFail($id);

        $this->sessionManager->stopSession($session);

        return response()->json([
            'data' => $session->fresh(),
            'message' => 'Session stopped',
        ]);
    }
}
