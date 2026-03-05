<?php

namespace App\Http\Controllers\Api\V1\Runtime;

use App\Http\Controllers\Controller;
use App\Models\Runtime\RuntimeToolCall;
use App\Services\Runtime\ApprovalGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuntimeToolCallController extends Controller
{
    public function __construct(
        private ApprovalGate $approvalGate
    ) {}

    /**
     * Approve a pending tool call.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $toolCall = $this->getAuthorizedToolCall($request, $id);
        $approval = $toolCall->approval;

        if (! $approval) {
            return response()->json(['error' => 'No pending approval'], 404);
        }

        $success = $this->approvalGate->approve(
            $approval,
            $request->user(),
            $request->input('reason'),
            (bool) $request->input('allow_always', false),
        );

        if (! $success) {
            return response()->json(['error' => 'Cannot approve this request'], 400);
        }

        return response()->json([
            'data' => $toolCall->fresh(),
            'message' => 'Approved',
        ]);
    }

    /**
     * Deny a pending tool call.
     */
    public function deny(Request $request, string $id): JsonResponse
    {
        $toolCall = $this->getAuthorizedToolCall($request, $id);
        $approval = $toolCall->approval;

        if (! $approval) {
            return response()->json(['error' => 'No pending approval'], 404);
        }

        $success = $this->approvalGate->deny(
            $approval,
            $request->user(),
            $request->input('reason')
        );

        if (! $success) {
            return response()->json(['error' => 'Cannot deny this request'], 400);
        }

        return response()->json([
            'data' => $toolCall->fresh(),
            'message' => 'Denied',
        ]);
    }

    /**
     * Get a tool call that the user is authorized to access.
     *
     * A user can access a tool call if it belongs to a turn within one of their sessions.
     */
    private function getAuthorizedToolCall(Request $request, string $id): RuntimeToolCall
    {
        return RuntimeToolCall::whereHas('turn.session', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);
    }
}
