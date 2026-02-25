<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatActionResource;
use App\Models\ChatAction;
use App\Models\ChatSession;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatActionController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $action = $this->findActionForUser($request, $id);

        if ($action === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Action not found.', 404);
        }

        return response()->json([
            'data' => new ChatActionResource($action),
        ]);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $action = $this->findActionForUser($request, $id);

        if ($action === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Action not found.', 404);
        }

        return response()->json([
            'data' => [
                'id' => $action->id,
                'status' => $action->status,
                'is_pending' => $action->isPending(),
                'is_executing' => $action->isExecuting(),
                'is_terminal' => $action->isTerminal(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'confirmed_at' => $action->confirmed_at?->toIso8601String(),
                'executed_at' => $action->executed_at?->toIso8601String(),
                'error' => $action->error,
            ],
        ]);
    }

    public function confirm(Request $request, string $id, AuditLogger $auditLogger): JsonResponse
    {
        $action = $this->findActionForUser($request, $id);

        if ($action === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Action not found.', 404);
        }

        if (! $action->isPending()) {
            return ErrorEnvelope::make(
                'ACTION_STATE_CONFLICT',
                'Action cannot be confirmed because it is not in pending state.',
                409,
                ['current_status' => $action->status]
            );
        }

        if (! $action->requires_confirmation) {
            return ErrorEnvelope::make(
                'CONFIRMATION_NOT_REQUIRED',
                'This action does not require confirmation.',
                400
            );
        }

        if ($action->confirmed_at !== null) {
            return ErrorEnvelope::make(
                'ALREADY_CONFIRMED',
                'This action has already been confirmed.',
                400,
                ['confirmed_at' => $action->confirmed_at->toIso8601String()]
            );
        }

        $before = ['confirmed_at' => null, 'status' => $action->status];

        $action->update([
            'confirmed_at' => now(),
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'chat_action.confirm',
            targetType: 'chat_action',
            targetId: $action->id,
            ownerUserId: $this->getOwnerUserId($action),
            changedFields: ['confirmed_at'],
            before: $before,
            after: ['confirmed_at' => $action->confirmed_at->toIso8601String(), 'status' => $action->status],
        );

        return response()->json([
            'data' => new ChatActionResource($action->fresh()),
        ]);
    }

    public function cancel(Request $request, string $id, AuditLogger $auditLogger): JsonResponse
    {
        $action = $this->findActionForUser($request, $id);

        if ($action === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Action not found.', 404);
        }

        if ($action->isTerminal()) {
            return ErrorEnvelope::make(
                'ACTION_ALREADY_TERMINAL',
                'Action cannot be cancelled because it is already in a terminal state.',
                409,
                ['current_status' => $action->status]
            );
        }

        if ($action->isExecuting()) {
            return ErrorEnvelope::make(
                'ACTION_EXECUTING',
                'Action cannot be cancelled while it is executing.',
                409,
                ['current_status' => $action->status]
            );
        }

        $before = ['status' => $action->status];

        $action->update([
            'status' => ChatAction::STATUS_CANCELLED,
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'chat_action.cancel',
            targetType: 'chat_action',
            targetId: $action->id,
            ownerUserId: $this->getOwnerUserId($action),
            changedFields: ['status'],
            before: $before,
            after: ['status' => ChatAction::STATUS_CANCELLED],
        );

        return response()->json([
            'data' => new ChatActionResource($action->fresh()),
        ]);
    }

    private function findActionForUser(Request $request, string $id): ?ChatAction
    {
        $userId = $request->user()->id;

        $sessionIds = ChatSession::query()
            ->where('user_id', $userId)
            ->pluck('id');

        $messageIds = \App\Models\ChatMessage::query()
            ->whereIn('chat_session_id', $sessionIds)
            ->pluck('id');

        return ChatAction::query()
            ->whereIn('chat_message_id', $messageIds)
            ->where('id', $id)
            ->first();
    }

    private function getOwnerUserId(ChatAction $action): ?int
    {
        $session = $action->message?->session;

        return $session?->user_id;
    }
}
