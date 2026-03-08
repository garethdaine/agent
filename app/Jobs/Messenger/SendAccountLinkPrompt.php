<?php

declare(strict_types=1);

namespace App\Jobs\Messenger;

use App\DTOs\Messenger\OutboundPayload;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Services\Messenger\AccountLinkTokenService;
use App\Support\Messenger\ConnectorManager;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAccountLinkPrompt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public string $connectorAccountId,
        public string $providerUserId,
        public string $channelId,
        public ?string $threadId = null,
        public bool $isReauth = false
    ) {
        $this->onQueue('messenger-default');
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(15);
    }

    public function handle(
        ConnectorManager $connectorManager,
        AccountLinkTokenService $tokenService
    ): void {
        $account = ConnectorAccount::find($this->connectorAccountId);

        if (! $account) {
            Log::channel('messenger')->warning('SendAccountLinkPrompt: Account not found', [
                'account_id' => $this->connectorAccountId,
            ]);

            return;
        }

        if (! $account->isConnected()) {
            Log::channel('messenger')->warning('SendAccountLinkPrompt: Account not connected', [
                'account_id' => $account->id,
                'status' => $account->status,
            ]);

            return;
        }

        // Generate account link token
        $token = $tokenService->generate(
            provider: $account->provider,
            providerUserId: $this->providerUserId,
            connectorAccountId: $account->id
        );

        // Build the link URL
        $linkUrl = $this->buildLinkUrl($token);

        // Build the message content
        $content = $this->buildMessageContent($account, $linkUrl);

        // Create outbound payload
        $payload = new OutboundPayload(
            content: $content,
            channelId: $this->channelId,
            threadId: $this->threadId,
        );

        // Send via adapter
        $adapter = $connectorManager->resolve($account->provider);

        try {
            $response = $adapter->sendMessage(
                $this->createTemporarySession($account),
                $payload
            );

            if (! $response->success) {
                Log::channel('messenger')->error('SendAccountLinkPrompt: Failed to send prompt', [
                    'account_id' => $account->id,
                    'provider_user_id' => $this->providerUserId,
                    'channel_id' => $this->channelId,
                    'error' => $response->error,
                ]);

                throw new \RuntimeException($response->error ?? 'Failed to send account link prompt');
            }

            Log::channel('messenger')->info('SendAccountLinkPrompt: Prompt sent successfully', [
                'account_id' => $account->id,
                'provider_user_id' => $this->providerUserId,
                'channel_id' => $this->channelId,
                'is_reauth' => $this->isReauth,
            ]);
        } catch (\Throwable $e) {
            Log::channel('messenger')->error('SendAccountLinkPrompt: Exception while sending prompt', [
                'account_id' => $account->id,
                'provider_user_id' => $this->providerUserId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildLinkUrl(string $token): string
    {
        return route('messenger.link.show', ['token' => $token]);
    }

    private function buildMessageContent(ConnectorAccount $account, string $linkUrl): string
    {
        $appName = config('app.name', 'Agent');

        if ($this->isReauth) {
            return sprintf(
                "Your session has expired. Please re-authenticate to continue using %s:\n\n%s\n\nThis link expires in 10 minutes.",
                $appName,
                $linkUrl
            );
        }

        return sprintf(
            "Welcome to %s! To get started, please link your account:\n\n%s\n\nThis link expires in 10 minutes.",
            $appName,
            $linkUrl
        );
    }

    /**
     * Create a temporary session object for sending the prompt.
     *
     * Since we don't have a real session yet (user is not linked),
     * we create a minimal object that the adapter can use.
     */
    private function createTemporarySession(ConnectorAccount $account): ChatSession
    {
        // Build an unsaved ChatSession instance just for adapter delivery.
        $session = new ChatSession([
            'connector_account_id' => $account->id,
            'channel_id' => $this->channelId,
            'thread_id' => $this->threadId,
            'status' => ChatSession::STATUS_ACTIVE,
        ]);

        $session->setRelation('connectorAccount', $account);

        return $session;
    }

    public function tags(): array
    {
        return [
            'messenger',
            'account-link',
            'account:'.$this->connectorAccountId,
            'provider_user:'.$this->providerUserId,
        ];
    }
}
