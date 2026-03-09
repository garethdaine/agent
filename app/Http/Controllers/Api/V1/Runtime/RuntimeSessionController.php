<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Runtime;

use App\Actions\Runtime\FindRuntimeSessionAction;
use App\Actions\Runtime\ListRuntimeSessionsAction;
use App\Http\Controllers\Controller;
use App\Services\Runtime\RuntimeSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuntimeSessionController extends Controller
{
    public function __construct(
        private readonly RuntimeSessionManager $sessionManager,
        private readonly ListRuntimeSessionsAction $listSessions,
        private readonly FindRuntimeSessionAction $findSession,
    ) {}

    /**
     * List all runtime sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = $this->listSessions->execute(
            $request->user(),
            $request->integer('per_page', 20),
        );

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
        $session = $this->findSession->execute(
            $request->user(),
            $id,
            ['turns.toolCalls', 'policySnapshots'],
        );

        return response()->json([
            'data' => $session,
        ]);
    }

    /**
     * Stop an active runtime session.
     */
    public function stop(Request $request, string $id): JsonResponse
    {
        $session = $this->findSession->execute($request->user(), $id);

        $this->sessionManager->stopSession($session);

        return response()->json([
            'data' => $session->fresh(),
            'message' => 'Session stopped',
        ]);
    }
}
