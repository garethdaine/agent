<?php

namespace App\Jobs\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\Enums\Messenger\ChatActionType;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Jobs\Runtime\ProcessRuntimeTurnJob;
use App\Services\Messenger\AgentRouter;
use App\Services\Messenger\ChatActionExecutor;
use App\Services\Messenger\ChatIntentParser;
use App\Services\Messenger\ChatResponseFormatter;
use App\Services\Messenger\CommandRouter;
use App\Services\Messenger\ConfirmationManager;
use App\Services\Messenger\StreamingResponseWriter;
use App\Services\Runtime\RuntimeSessionManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessChatIntent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public string $messageId,
        public string $sessionId,
        public int $userId
    ) {
        $this->onQueue('messenger-default');
    }

    public function handle(
        ChatIntentParser $intentParser,
        ChatActionExecutor $actionExecutor,
        ConfirmationManager $confirmationManager,
        ChatResponseFormatter $responseFormatter,
        ConnectorManager $connectorManager,
        CommandRouter $commandRouter
    ): void {
        $message = ChatMessage::find($this->messageId);
        $session = ChatSession::find($this->sessionId);
        $user = User::find($this->userId);

        if ($message === null || $session === null || $user === null) {
            Log::warning('ProcessChatIntent: Required entities not found', [
                'message_id' => $this->messageId,
                'session_id' => $this->sessionId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $account = $session->connectorAccount;

        if ($account === null) {
            Log::warning('ProcessChatIntent: Connector account not found', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $adapter = $connectorManager->resolve($account->provider);

        // Acknowledge receipt with eyes emoji so the user knows we received it
        if ($adapter->supportsReactions() && $message->provider_message_id) {
            try {
                $adapter->addReaction($session, $message->provider_message_id, '👀');
            } catch (\Throwable $e) {
                Log::debug('ProcessChatIntent: Failed to add acknowledgement reaction', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1. Slash commands take highest priority (deterministic routing)
        if ($commandRouter->isCommand($message->content)) {
            [$cmdName, $cmdArgs] = $commandRouter->parseCommand($message->content);

            if ($cmdName === 'ask' && $cmdArgs !== []) {
                $question = trim(implode(' ', $cmdArgs));
                if ($question !== '') {
                    $runtimeSession = app(RuntimeSessionManager::class)->getOrCreateSessionForChat(
                        $user,
                        $session->id,
                        $account,
                        []
                    );
                    ProcessRuntimeTurnJob::dispatch(
                        $runtimeSession->id,
                        $question,
                        $session->id,
                        $account->id
                    );
                    $this->sendResponse(
                        "Running that in the runtime. I'll reply when done.",
                        $session,
                        $account,
                        $adapter
                    );

                    return;
                }
            }

            $commandResult = $commandRouter->route($message->content, $user, $session->id, $account->id);
            if ($commandResult !== null) {
                $this->sendResponse($commandResult->message, $session, $account, $adapter);

                return;
            }
        }

        // 2a. If there is an active runtime session for this chat, route to runtime
        $agentRouter = app(AgentRouter::class);
        if ($agentRouter->shouldRouteToRuntime($user, $session->id)) {
            $runtimeSession = $agentRouter->getActiveSessionForChat($user, $session->id);
            if ($runtimeSession !== null) {
                ProcessRuntimeTurnJob::dispatch(
                    $runtimeSession->id,
                    $message->content,
                    $session->id,
                    $account->id
                );
                $placeholder = $adapter->supportsMessageEditing()
                    ? $this->sendPlaceholder($session, $account, $adapter)
                    : null;
                if ($placeholder?->providerMessageId) {
                    $adapter->editMessage($session, $placeholder->providerMessageId, '⏳ Processing in runtime…');
                } else {
                    $this->sendResponse('⏳ Processing in runtime. I\'ll reply when done.', $session, $account, $adapter);
                }

                return;
            }
        }

        // 3. Check for pending confirmation responses
        $confirmedAction = $confirmationManager->processConfirmationResponse(
            $message->content,
            $session,
            $account
        );

        if ($confirmedAction !== null) {
            $this->executeAction($confirmedAction, $user, $session, $account, $actionExecutor, $responseFormatter, $connectorManager);

            return;
        }

        // 4. Intent parsing (structured actions + general task fallback)
        $parsedAction = $intentParser->parse($message, $session);

        if ($parsedAction === null) {
            Log::debug('ProcessChatIntent: Could not parse intent', [
                'message_id' => $message->id,
                'content' => $message->content,
            ]);

            $this->sendResponse(
                "I couldn't understand that. You can try:\n\n".
                "**Commands:** `/status`, `/runs`, `/stop`, `/mode`\n".
                "**Jobs:** \"list jobs\", \"show job 123\", \"create a job\"\n".
                "**Runs:** \"show run history\", \"did job 199 complete?\"\n\n".
                "Or just ask me anything and I'll do my best to help!",
                $session,
                $account,
                $adapter
            );

            return;
        }

        if ($parsedAction->needsClarificationResponse()) {
            $this->sendResponse(
                $parsedAction->clarificationNeeded ?? 'Could you please clarify what you want to do?',
                $session,
                $account,
                $adapter
            );

            return;
        }

        // Inject soul config into general task parameters
        if ($parsedAction->type === ChatActionType::GENERAL_TASK) {
            $soulConfig = $account->config['soul'] ?? null;
            if ($soulConfig !== null) {
                $parsedAction = $parsedAction->withMergedParameters(['_soul' => $soulConfig]);
            }
        }

        $action = ChatAction::create([
            'chat_message_id' => $message->id,
            'action_type' => $parsedAction->type->value,
            'parameters' => $parsedAction->parameters,
            'status' => ChatAction::STATUS_PENDING,
            'requires_confirmation' => $parsedAction->requiresConfirmation,
        ]);

        Log::info('ProcessChatIntent: Action created', [
            'action_id' => $action->id,
            'action_type' => $action->action_type,
            'requires_confirmation' => $action->requires_confirmation,
        ]);

        if ($parsedAction->requiresConfirmation) {
            $pending = $confirmationManager->createPendingConfirmation(
                $action,
                $session,
                $account
            );

            $confirmationManager->promptForConfirmation(
                $action,
                $pending,
                $account,
                $session
            );

            return;
        }

        $this->executeAction($action, $user, $session, $account, $actionExecutor, $responseFormatter, $connectorManager);
    }

    private function executeAction(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account,
        ChatActionExecutor $actionExecutor,
        ChatResponseFormatter $responseFormatter,
        ConnectorManager $connectorManager
    ): void {
        $adapter = $connectorManager->resolve($account->provider);
        $actionType = ChatActionType::tryFrom($action->action_type);

        if ($actionType === ChatActionType::GENERAL_TASK && $adapter->supportsMessageEditing()) {
            $this->executeStreamingAction($action, $user, $session, $account, $actionExecutor, $adapter);

            return;
        }

        if ($actionType?->isQuery() && ! $adapter->supportsMessageEditing()) {
            $this->executeSyncAction($action, $user, $session, $account, $actionExecutor, $responseFormatter, $adapter);

            return;
        }

        $placeholderResponse = $this->sendPlaceholder($session, $account, $adapter);

        $this->executeSyncAction(
            $action,
            $user,
            $session,
            $account,
            $actionExecutor,
            $responseFormatter,
            $adapter,
            $placeholderResponse?->providerMessageId
        );
    }

    private function executeStreamingAction(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account,
        ChatActionExecutor $actionExecutor,
        ConnectorAdapterInterface $adapter,
    ): void {
        $action->update(['status' => ChatAction::STATUS_EXECUTING]);

        $placeholderResponse = $this->sendPlaceholder($session, $account, $adapter);
        $messageId = $placeholderResponse?->providerMessageId;

        if ($messageId === null) {
            $this->executeSyncAction(
                $action, $user, $session, $account,
                $actionExecutor, app(ChatResponseFormatter::class), $adapter
            );

            return;
        }

        $writer = new StreamingResponseWriter($adapter, $session, $messageId);

        try {
            $result = $actionExecutor->executeStreaming(
                $action,
                $user,
                fn (string $chunk) => $writer->append($chunk)
            );

            if ($result->success) {
                $writer->finalize();
            } else {
                $writer->forceWrite($result->error ?? 'Something went wrong.');
            }

            $action->update([
                'status' => $result->success ? ChatAction::STATUS_COMPLETED : ChatAction::STATUS_FAILED,
                'result' => $result->toArray(),
                'error' => $result->error,
                'executed_at' => now(),
            ]);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => ChatMessage::DIRECTION_OUTBOUND,
                'content' => $writer->getContent(),
                'idempotency_key' => hash('sha256', Str::uuid()->toString()),
                'provider_message_id' => $messageId,
                'provider_timestamp' => now(),
            ]);

            Log::info('ProcessChatIntent: Streaming action completed', [
                'action_id' => $action->id,
                'success' => $result->success,
                'edit_count' => $writer->getEditCount(),
            ]);

        } catch (\Throwable $e) {
            $writer->forceWrite('Something went wrong processing your request. Please try again.');

            $action->update([
                'status' => ChatAction::STATUS_FAILED,
                'error' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            Log::error('ProcessChatIntent: Streaming action failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function executeSyncAction(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account,
        ChatActionExecutor $actionExecutor,
        ChatResponseFormatter $responseFormatter,
        ConnectorAdapterInterface $adapter,
        ?string $placeholderMessageId = null
    ): void {
        $action->update(['status' => ChatAction::STATUS_EXECUTING]);

        try {
            $result = $actionExecutor->execute($action, $user);

            $action->update([
                'status' => $result->success ? ChatAction::STATUS_COMPLETED : ChatAction::STATUS_FAILED,
                'result' => $result->toArray(),
                'error' => $result->error,
                'executed_at' => now(),
            ]);

            $formattedResponse = $responseFormatter->format($result, $action, $account);
            $this->sendOrEditResponse($formattedResponse, $session, $account, $adapter, $placeholderMessageId);

            Log::info('ProcessChatIntent: Action executed', [
                'action_id' => $action->id,
                'success' => $result->success,
            ]);

        } catch (\Throwable $e) {
            $action->update([
                'status' => ChatAction::STATUS_FAILED,
                'error' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            $errorResponse = $responseFormatter->formatError($e, $action, $account);
            $this->sendOrEditResponse($errorResponse, $session, $account, $adapter, $placeholderMessageId);

            Log::error('ProcessChatIntent: Action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendPlaceholder(
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): ?ProviderResponse {
        if (! $adapter->supportsMessageEditing()) {
            return null;
        }

        $payload = new OutboundPayload(
            content: '⏳ Thinking…',
            channelId: $session->channel_id,
            threadId: $session->thread_id,
        );

        try {
            return $adapter->sendMessage($session, $payload);
        } catch (\Throwable $e) {
            Log::warning('ProcessChatIntent: Failed to send placeholder', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sendOrEditResponse(
        string $content,
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter,
        ?string $placeholderMessageId = null
    ): void {
        try {
            if ($placeholderMessageId !== null && $adapter->supportsMessageEditing()) {
                $response = $adapter->editMessage($session, $placeholderMessageId, $content);

                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'connector_account_id' => $account->id,
                    'direction' => ChatMessage::DIRECTION_OUTBOUND,
                    'content' => $content,
                    'idempotency_key' => hash('sha256', Str::uuid()->toString()),
                    'provider_message_id' => $placeholderMessageId,
                    'provider_timestamp' => now(),
                ]);

                return;
            }

            $this->sendResponse($content, $session, $account, $adapter);
        } catch (\Throwable $e) {
            Log::error('ProcessChatIntent: Failed to send/edit response', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendResponse(
        string $content,
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorAdapterInterface $adapter
    ): void {
        $payload = new OutboundPayload(
            content: $content,
            channelId: $session->channel_id,
            threadId: $session->thread_id,
        );

        try {
            $response = $adapter->sendMessage($session, $payload);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'connector_account_id' => $account->id,
                'direction' => ChatMessage::DIRECTION_OUTBOUND,
                'content' => $content,
                'idempotency_key' => hash('sha256', Str::uuid()->toString()),
                'provider_message_id' => $response->providerMessageId,
                'provider_timestamp' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessChatIntent: Failed to send response', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'messenger',
            'intent',
            'message:'.$this->messageId,
            'session:'.$this->sessionId,
            'user:'.$this->userId,
        ];
    }
}
