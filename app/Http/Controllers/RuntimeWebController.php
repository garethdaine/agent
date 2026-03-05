<?php

namespace App\Http\Controllers;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuntimeWebController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = RuntimeSession::where('user_id', $request->user()->id)
            ->with('turns')
            ->orderBy('started_at', 'desc')
            ->paginate(20);

        return Inertia::render('Messenger/Runtime/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $session = RuntimeSession::where('user_id', $request->user()->id)
            ->with(['turns.toolCalls.approval', 'policySnapshots', 'artifacts'])
            ->findOrFail($id);

        $pendingApprovals = RuntimeApproval::whereHas('toolCall.turn', function ($q) use ($session) {
            $q->where('runtime_session_id', $session->id);
        })->where('state', RuntimeApprovalState::Pending)->get();

        return Inertia::render('Messenger/Runtime/Show', [
            'session' => $session,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }
}
