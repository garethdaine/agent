<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\Http\Controllers\Controller;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatSession::with('connectorAccount:id,name,provider')
            ->where('user_id', $request->user()->id)
            ->withCount('messages');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        if ($request->filled('connector_id')) {
            $query->where('connector_account_id', $request->input('connector_id'));
        }

        $sessions = $query->orderByDesc('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::with('connectorAccount:id,name,provider')
            ->where('user_id', $request->user()->id)
            ->withCount('messages')
            ->findOrFail($id);

        return response()->json(['data' => $session]);
    }

    public function send(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::with('connectorAccount')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $connector = $session->connectorAccount;
        if (! $connector) {
            return response()->json(['error' => 'No connector account for this session.'], 422);
        }

        $adapter = app(ConnectorAdapterInterface::class, ['provider' => $connector->provider]);

        $providerResponse = $adapter->sendMessage($session, $validated['content']);

        if (! $providerResponse->success) {
            return response()->json([
                'error' => $providerResponse->error ?? 'Failed to send message.',
            ], 500);
        }

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
            'content' => $validated['content'],
            'provider_message_id' => $providerResponse->messageId,
        ]);

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'provider_message_id' => $providerResponse->messageId,
                'sent' => true,
            ],
        ]);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::where('user_id', $request->user()->id)->findOrFail($id);

        $messages = $session->messages()
            ->orderByDesc('created_at')
            ->limit(min((int) $request->input('per_page', 30), 200))
            ->get(['id', 'direction', 'content', 'provider_message_id', 'created_at']);

        return response()->json([
            'data' => $messages->reverse()->values(),
            'meta' => ['session_id' => $session->id],
        ]);
    }

    public function actions(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::where('user_id', $request->user()->id)->findOrFail($id);

        $messageIds = $session->messages()->pluck('id');

        $actions = ChatAction::whereIn('chat_message_id', $messageIds)
            ->orderByDesc('created_at')
            ->limit(min((int) $request->input('per_page', 30), 200))
            ->get(['id', 'action_type', 'status', 'chat_message_id', 'created_at']);

        return response()->json([
            'data' => $actions,
            'meta' => ['session_id' => $session->id],
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::where('user_id', $request->user()->id)->findOrFail($id);
        $session->update(['status' => ChatSession::STATUS_ARCHIVED]);

        return response()->json(['data' => ['archived' => true]]);
    }
}
