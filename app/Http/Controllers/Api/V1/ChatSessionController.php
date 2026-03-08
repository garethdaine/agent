<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Messenger\OutboundPayload;
use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessChatIntent;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Services\Messenger\CommandRouter;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connector_account_id' => ['required', 'uuid', 'exists:connector_accounts,id'],
            'channel_id' => ['nullable', 'string', 'max:255'],
        ]);

        $connectorAccount = ConnectorAccount::findOrFail($validated['connector_account_id']);

        $session = ChatSession::create([
            'user_id' => $request->user()->id,
            'connector_account_id' => $connectorAccount->id,
            'provider' => $connectorAccount->provider,
            'channel_id' => $validated['channel_id'] ?? 'manual-'.Str::random(12),
            'session_key' => 'session-'.Str::random(8),
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $session->load('connectorAccount:id,name,provider');
        $session->loadCount('messages');

        return response()->json(['data' => $session], 201);
    }

    public function connectors(): JsonResponse
    {
        $accounts = ConnectorAccount::where('status', ConnectorAccount::STATUS_CONNECTED)
            ->orderBy('provider')
            ->get(['id', 'name', 'provider', 'status']);

        return response()->json(['data' => $accounts]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::with('connectorAccount:id,name,provider')
            ->where('user_id', $request->user()->id)
            ->withCount('messages')
            ->findOrFail($id);

        return response()->json(['data' => $session]);
    }

    public function send(Request $request, string $id, ConnectorManager $connectorManager): JsonResponse
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

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => $validated['content'],
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
        ]);

        $user = $request->user();
        $displayName = $user->name ?? 'User';
        $payload = new OutboundPayload(
            content: 'Message Received from User ('.$displayName.'): '.$validated['content'],
            channelId: $session->channel_id ?? '',
            threadId: $session->thread_id,
            senderUsername: $user->name ?? 'User',
            senderAvatarUrl: $user->profile_photo_url ?? null,
        );
        $adapter = $connectorManager->resolve($connector->provider);
        $providerResponse = $adapter->sendMessage($session, $payload);

        if ($providerResponse->success && $providerResponse->providerMessageId) {
            $message->update(['provider_message_id' => $providerResponse->providerMessageId]);
        }

        ProcessChatIntent::dispatch(
            messageId: $message->id,
            sessionId: $session->id,
            userId: $request->user()->id
        );

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'provider_message_id' => $providerResponse->providerMessageId,
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

    public function commands(CommandRouter $router): JsonResponse
    {
        $descriptions = [
            'jobs' => 'Manage agent jobs (list, show, create, delete, run, enable, disable)',
            'runs' => 'Manage agent runs (active, history, show, stop, retry, steer)',
            'status' => 'Overall system status',
            'sessions' => 'Manage runtime sessions (list, stop)',
            'mode' => 'View or change execution mode (safe, standard, full)',
            'approve' => 'Approve a pending tool call',
            'deny' => 'Deny a pending tool call',
            'browser' => 'Browser sidecar (start, stop, reset, status)',
            'ask' => 'Ask a question or run a task',
            'context' => 'Context usage (messages, tokens)',
            'new' => 'Start a new runtime session',
            'help' => 'List available commands',
            'commands' => 'Detailed command list',
            'whoami' => 'Show your user and connector ID',
            'compact' => 'Run compaction on conversation',
            'subagents' => 'Manage sub-agents (list, spawn, kill)',
            'progress' => 'Show live progress for current turn',
        ];

        $commands = collect($router->getAvailableCommands())->map(function (string $name) use ($descriptions) {
            return [
                'name' => $name,
                'description' => $descriptions[$name] ?? '',
            ];
        })->values();

        return response()->json(['data' => $commands]);
    }
}
