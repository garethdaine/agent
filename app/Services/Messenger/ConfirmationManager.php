<?php

namespace App\Services\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\OutboundPayload;
use App\Enums\Messenger\ChatActionType;
use App\Models\ChatAction;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\PendingConfirmation;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConfirmationManager
{
    private const CONFIRMATION_TTL_MINUTES = 5;

    public function __construct(
        private ConnectorManager $connectorManager,
    ) {}

    /**
     * Create a pending confirmation for an action.
     */
    public function createPendingConfirmation(
        ChatAction $action,
        ChatSession $session,
        ConnectorAccount $account
    ): PendingConfirmation {
        $token = Str::random(32);

        return PendingConfirmation::create([
            'chat_action_id' => $action->id,
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
            'confirmation_token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::CONFIRMATION_TTL_MINUTES),
        ]);
    }

    /**
     * Send a confirmation prompt to the user.
     */
    public function promptForConfirmation(
        ChatAction $action,
        PendingConfirmation $confirmation,
        ConnectorAccount $account,
        ChatSession $session
    ): void {
        $adapter = $this->connectorManager->resolve($account->provider);
        $actionType = ChatActionType::tryFrom($action->action_type);

        $promptMessage = $this->buildConfirmationPrompt($action, $actionType);

        $payload = new OutboundPayload(
            content: $promptMessage,
            channelId: $session->channel_id,
            threadId: $session->thread_id,
        );

        try {
            $response = $adapter->sendMessage($session, $payload);

            // Store provider message ID for button callback routing
            if ($response->success && $response->providerMessageId !== null) {
                $confirmation->update([
                    'provider_message_id' => $response->providerMessageId,
                    'callback_data' => [
                        'action_id' => $action->id,
                        'confirmation_id' => $confirmation->id,
                    ],
                ]);
            }

            Log::info('ConfirmationManager: Confirmation prompt sent', [
                'action_id' => $action->id,
                'confirmation_id' => $confirmation->id,
                'provider_message_id' => $response->providerMessageId,
            ]);

        } catch (\Throwable $e) {
            Log::error('ConfirmationManager: Failed to send confirmation prompt', [
                'action_id' => $action->id,
                'confirmation_id' => $confirmation->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process a confirmation response (yes/confirm/no/cancel).
     */
    public function processConfirmationResponse(
        string $responseText,
        ChatSession $session,
        ConnectorAccount $account
    ): ?ChatAction {
        $normalized = strtolower(trim($responseText));

        // Find the most recent pending confirmation for this session
        $pending = PendingConfirmation::query()
            ->where('chat_session_id', $session->id)
            ->where('connector_account_id', $account->id)
            ->pending()
            ->orderByDesc('created_at')
            ->first();

        if ($pending === null) {
            return null; // No pending confirmation
        }

        $isConfirmed = $this->isConfirmationResponse($normalized);
        $isCancelled = $this->isCancellationResponse($normalized);

        if (! $isConfirmed && ! $isCancelled) {
            return null; // Not a confirmation response
        }

        if ($isConfirmed) {
            return $this->handleConfirmation($pending);
        }

        $this->handleCancellation($pending);

        return null;
    }

    /**
     * Process a button callback confirmation (for interactive buttons).
     */
    public function processCallbackConfirmation(
        string $confirmationId,
        bool $isConfirmed,
        ConnectorAccount $account
    ): ?ChatAction {
        $pending = PendingConfirmation::query()
            ->where('connector_account_id', $account->id)
            ->where('id', $confirmationId)
            ->pending()
            ->first();

        if ($pending === null) {
            return null;
        }

        if ($isConfirmed) {
            return $this->handleConfirmation($pending);
        }

        $this->handleCancellation($pending);

        return null;
    }

    /**
     * Find pending confirmation by provider message ID.
     */
    public function findByProviderMessageId(
        string $providerMessageId,
        ConnectorAccount $account
    ): ?PendingConfirmation {
        return PendingConfirmation::query()
            ->where('connector_account_id', $account->id)
            ->where('provider_message_id', $providerMessageId)
            ->pending()
            ->first();
    }

    /**
     * Handle confirmed action.
     */
    private function handleConfirmation(PendingConfirmation $pending): ChatAction
    {
        $pending->confirm();

        $action = $pending->action;
        $action->update(['confirmed_at' => now()]);

        Log::info('ConfirmationManager: Action confirmed', [
            'action_id' => $action->id,
            'confirmation_id' => $pending->id,
        ]);

        return $action;
    }

    /**
     * Handle cancelled action.
     */
    private function handleCancellation(PendingConfirmation $pending): void
    {
        $pending->cancel();

        $action = $pending->action;
        $action->update(['status' => ChatAction::STATUS_CANCELLED]);

        Log::info('ConfirmationManager: Action cancelled', [
            'action_id' => $action->id,
            'confirmation_id' => $pending->id,
        ]);
    }

    /**
     * Check if a response is a confirmation.
     */
    private function isConfirmationResponse(string $response): bool
    {
        $confirmationPhrases = [
            'yes',
            'y',
            'confirm',
            'ok',
            'okay',
            'sure',
            'proceed',
            'do it',
            'go ahead',
            'approved',
            'approve',
        ];

        return in_array($response, $confirmationPhrases, true);
    }

    /**
     * Check if a response is a cancellation.
     */
    private function isCancellationResponse(string $response): bool
    {
        $cancellationPhrases = [
            'no',
            'n',
            'cancel',
            'stop',
            'abort',
            'nevermind',
            'never mind',
            'nope',
            'deny',
            'denied',
            'reject',
        ];

        return in_array($response, $cancellationPhrases, true);
    }

    /**
     * Build the confirmation prompt message.
     */
    private function buildConfirmationPrompt(
        ChatAction $action,
        ?ChatActionType $actionType
    ): string {
        $actionLabel = $actionType?->label() ?? $action->action_type;
        $actionDescription = $actionType?->description() ?? 'Perform action';

        $params = $action->parameters ?? [];
        $paramSummary = $this->formatParameterSummary($params, $actionType);

        $message = "**Confirmation Required**\n\n";
        $message .= "Action: {$actionLabel}\n";

        if ($paramSummary !== '') {
            $message .= "Details: {$paramSummary}\n";
        }

        $message .= "\nThis action requires confirmation. Reply with **yes** to proceed or **no** to cancel.";
        $message .= "\n\n_This confirmation will expire in ".self::CONFIRMATION_TTL_MINUTES." minutes._";

        return $message;
    }

    /**
     * Format parameters for display in confirmation prompt.
     *
     * @param  array<string, mixed>  $params
     */
    private function formatParameterSummary(array $params, ?ChatActionType $actionType): string
    {
        if (empty($params)) {
            return '';
        }

        $parts = [];

        if (isset($params['run_id'])) {
            $parts[] = "Run: {$params['run_id']}";
        }

        if (isset($params['job_id'])) {
            $parts[] = "Job: {$params['job_id']}";
        }

        if (isset($params['name'])) {
            $parts[] = "Name: {$params['name']}";
        }

        if (isset($params['reason'])) {
            $parts[] = "Reason: {$params['reason']}";
        }

        return implode(', ', $parts);
    }

    /**
     * Cleanup expired confirmations and timeout their actions.
     */
    public function cleanupExpired(): int
    {
        $expired = PendingConfirmation::query()
            ->expired()
            ->with('action')
            ->get();

        $count = 0;

        foreach ($expired as $confirmation) {
            $confirmation->update(['cancelled_at' => now()]);

            if ($confirmation->action && $confirmation->action->isPending()) {
                $confirmation->action->update(['status' => ChatAction::STATUS_TIMEOUT]);
            }

            $count++;
        }

        if ($count > 0) {
            Log::info('ConfirmationManager: Cleaned up expired confirmations', [
                'count' => $count,
            ]);
        }

        return $count;
    }
}
