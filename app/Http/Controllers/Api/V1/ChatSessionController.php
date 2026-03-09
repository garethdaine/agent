<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Chat\CreateChatMessageAction;
use App\Actions\Chat\CreateChatSessionAction;
use App\Actions\Chat\FindConnectorAccountAction;
use App\Actions\Chat\FindUserChatSessionAction;
use App\Actions\Chat\ListConnectedAccountsAction;
use App\Actions\Chat\ListSessionActionsAction;
use App\Actions\Chat\ListSessionMessagesAction;
use App\Actions\Chat\ListUserChatSessionsAction;
use App\Actions\Chat\UpdateChatMessageAction;
use App\Actions\Chat\UpdateChatSessionAction;
use App\DTOs\Messenger\OutboundPayload;
use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessChatIntent;
use App\Models\ChatSession;
use App\Services\Messenger\CommandRouter;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatSessionController extends Controller
{
    public function __construct(
        private readonly FindConnectorAccountAction $findConnectorAccount,
        private readonly ListConnectedAccountsAction $listConnectedAccounts,
        private readonly FindUserChatSessionAction $findUserChatSession,
        private readonly ListUserChatSessionsAction $listUserChatSessions,
        private readonly CreateChatSessionAction $createChatSession,
        private readonly UpdateChatSessionAction $updateChatSession,
        private readonly CreateChatMessageAction $createChatMessage,
        private readonly UpdateChatMessageAction $updateChatMessage,
        private readonly ListSessionMessagesAction $listSessionMessages,
        private readonly ListSessionActionsAction $listSessionActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->listUserChatSessions->execute(
            $request->user()->id,
            $request->only(['status', 'provider', 'connector_id', 'per_page']),
        );

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

        $connectorAccount = $this->findConnectorAccount->execute($validated['connector_account_id']);

        $session = $this->createChatSession->execute([
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
        $accounts = $this->listConnectedAccounts->execute();

        return response()->json(['data' => $accounts]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $session = $this->findUserChatSession->execute(
            $request->user()->id,
            $id,
            with: ['connectorAccount:id,name,provider'],
            withMessageCount: true,
        );

        return response()->json(['data' => $session]);
    }

    public function send(Request $request, string $id, ConnectorManager $connectorManager): JsonResponse
    {
        $session = $this->findUserChatSession->execute(
            $request->user()->id,
            $id,
            with: ['connectorAccount'],
        );

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $connector = $session->connectorAccount;
        if (! $connector) {
            return response()->json(['error' => 'No connector account for this session.'], 422);
        }

        $message = $this->createChatMessage->execute([
            'chat_session_id' => $session->id,
            'connector_account_id' => $connector->id,
            'direction' => \App\Models\ChatMessage::DIRECTION_INBOUND,
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
            $this->updateChatMessage->execute($message, ['provider_message_id' => $providerResponse->providerMessageId]);
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
        $session = $this->findUserChatSession->execute($request->user()->id, $id);

        $messages = $this->listSessionMessages->execute(
            $session,
            (int) $request->input('per_page', 30),
        );

        return response()->json([
            'data' => $messages->reverse()->values(),
            'meta' => ['session_id' => $session->id],
        ]);
    }

    public function actions(Request $request, string $id): JsonResponse
    {
        $session = $this->findUserChatSession->execute($request->user()->id, $id);

        $actions = $this->listSessionActions->execute(
            $session,
            (int) $request->input('per_page', 30),
        );

        return response()->json([
            'data' => $actions,
            'meta' => ['session_id' => $session->id],
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $session = $this->findUserChatSession->execute($request->user()->id, $id);
        $this->updateChatSession->execute($session, ['status' => ChatSession::STATUS_ARCHIVED]);

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
