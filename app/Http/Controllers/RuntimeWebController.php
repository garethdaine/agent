<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Runtime\FindRuntimeSessionAction;
use App\Actions\Runtime\GetPendingApprovalsAction;
use App\Actions\Runtime\ListRuntimeSessionsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuntimeWebController extends Controller
{
    public function __construct(
        private readonly ListRuntimeSessionsAction $listSessions,
        private readonly FindRuntimeSessionAction $findSession,
        private readonly GetPendingApprovalsAction $getPendingApprovals,
    ) {}

    public function index(Request $request): Response
    {
        $sessions = $this->listSessions->execute($request->user(), 20);

        return Inertia::render('Messenger/Runtime/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $session = $this->findSession->execute(
            $request->user(),
            $id,
            ['turns.toolCalls.approval', 'policySnapshots', 'artifacts'],
        );

        $pendingApprovals = $this->getPendingApprovals->execute($session);

        return Inertia::render('Messenger/Runtime/Show', [
            'session' => $session,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }
}
