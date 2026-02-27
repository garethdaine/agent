<?php

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Enums\Messenger\ChatActionType;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Messenger\ChatActionExecutor;
use App\Services\Messenger\ChatIntentParser;
use App\Services\Messenger\ChatResponseFormatter;
use App\Services\Messenger\ConfirmationManager;
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
        ConnectorManager $connectorManager
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

        // First check if this is a confirmation response for a pending action
        $confirmedAction = $confirmationManager->processConfirmationResponse(
            $message->content,
            $session,
            $account
        );

        if ($confirmedAction !== null) {
            // This was a confirmation - execute the confirmed action
            $this->executeAction($confirmedAction, $user, $session, $account, $actionExecutor, $responseFormatter, $connectorManager);

            return;
        }

        // Parse the intent
        $parsedAction = $intentParser->parse($message, $session);

        if ($parsedAction === null) {
            Log::debug('ProcessChatIntent: Could not parse intent', [
                'message_id' => $message->id,
                'content' => $message->content,
            ]);

            // Send a helpful response
            $this->sendResponse(
                "I couldn't understand that command. Try commands like:\n".
                "- \"list my jobs\"\n".
                "- \"show active runs\"\n".
                "- \"stop run [run-id]\"\n".
                '- "run job [job-id] now"',
                $session,
                $account,
                $connectorManager
            );

            return;
        }

        // Check if clarification is needed
        if ($parsedAction->needsClarificationResponse()) {
            $this->sendResponse(
                $parsedAction->clarificationNeeded ?? 'Could you please clarify what you want to do?',
                $session,
                $account,
                $connectorManager
            );

            return;
        }

        // Create the ChatAction record
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

        // Check if confirmation is required
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

        // Execute the action
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
        $actionType = ChatActionType::tryFrom($action->action_type);

        // For query actions, execute synchronously
        if ($actionType?->isQuery()) {
            $this->executeSyncAction($action, $user, $session, $account, $actionExecutor, $responseFormatter, $connectorManager);

            return;
        }

        // For mutation actions, send ack and execute
        $this->sendResponse(
            'Processing your request...',
            $session,
            $account,
            $connectorManager
        );

        $this->executeSyncAction($action, $user, $session, $account, $actionExecutor, $responseFormatter, $connectorManager);
    }

    private function executeSyncAction(
        ChatAction $action,
        User $user,
        ChatSession $session,
        ConnectorAccount $account,
        ChatActionExecutor $actionExecutor,
        ChatResponseFormatter $responseFormatter,
        ConnectorManager $connectorManager
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

            // Format and send response
            $formattedResponse = $responseFormatter->format($result, $action, $account);
            $this->sendResponse($formattedResponse, $session, $account, $connectorManager);

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
            $this->sendResponse($errorResponse, $session, $account, $connectorManager);

            Log::error('ProcessChatIntent: Action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendResponse(
        string $content,
        ChatSession $session,
        ConnectorAccount $account,
        ConnectorManager $connectorManager
    ): void {
        $adapter = $connectorManager->resolve($account->provider);

        $payload = new OutboundPayload(
            content: $content,
            channelId: $session->channel_id,
            threadId: $session->thread_id,
        );

        try {
            $response = $adapter->sendMessage($session, $payload);

            // Store outbound message
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
