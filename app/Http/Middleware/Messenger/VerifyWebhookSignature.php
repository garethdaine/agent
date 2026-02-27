<?php

namespace App\Http\Middleware\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\Models\ConnectorAccount;
use App\Support\Messenger\ConnectorManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(
        private readonly ConnectorManager $connectorManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');

        if (! $provider || ! $this->connectorManager->isSupported($provider)) {
            return $this->unauthorizedResponse('PROVIDER_INVALID', 'Unsupported provider.');
        }

        $adapter = $this->connectorManager->resolve($provider);
        $account = $this->resolveAccount($provider, $request, $adapter);

        if (! $account) {
            Log::warning('Webhook signature verification failed: account not found', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return $this->unauthorizedResponse('ACCOUNT_NOT_FOUND', 'Connector account not found.');
        }

        // Check timestamp for timestamp-based replay protection (Slack)
        if ($this->usesTimestampReplayProtection($account)) {
            $timestampResult = $this->verifyTimestamp($request, $account);
            if ($timestampResult !== true) {
                Log::warning('Webhook timestamp verification failed', [
                    'provider' => $provider,
                    'account_id' => $account->id,
                    'reason' => $timestampResult,
                    'ip' => $request->ip(),
                ]);

                return $this->unauthorizedResponse('TIMESTAMP_EXPIRED', 'Webhook timestamp is too old.');
            }
        }

        // Verify the webhook signature using the adapter
        $request->attributes->set('connector_account', $account);

        if (! $adapter->verifyWebhookSignature($request)) {
            Log::warning('Webhook signature verification failed', [
                'provider' => $provider,
                'account_id' => $account->id,
                'ip' => $request->ip(),
            ]);

            return $this->unauthorizedResponse('SIGNATURE_INVALID', 'Invalid webhook signature.');
        }

        return $next($request);
    }

    private function resolveAccount(
        string $provider,
        Request $request,
        ConnectorAdapterInterface $adapter
    ): ?ConnectorAccount {
        // For providers with account key in route (e.g., Telegram)
        $accountKey = $request->route('accountKey');
        if ($accountKey) {
            return ConnectorAccount::query()
                ->forProvider($provider)
                ->where('account_key', $accountKey)
                ->active()
                ->first();
        }

        // For Slack, extract team_id from payload
        if ($provider === ConnectorAccount::PROVIDER_SLACK) {
            $teamId = $request->input('team_id') ?? $request->input('event.team');
            if ($teamId) {
                return ConnectorAccount::query()
                    ->forProvider($provider)
                    ->where('account_key', $teamId)
                    ->active()
                    ->first();
            }
        }

        // Fallback: verify signature against each active account for this provider.
        // This supports multi-account routing even when account key extraction fails.
        $accounts = ConnectorAccount::query()
            ->forProvider($provider)
            ->active()
            ->get();

        foreach ($accounts as $candidate) {
            $request->attributes->set('connector_account', $candidate);

            if ($adapter->verifyWebhookSignature($request)) {
                return $candidate;
            }
        }

        $request->attributes->remove('connector_account');

        return null;
    }

    private function usesTimestampReplayProtection(ConnectorAccount $account): bool
    {
        $strategy = $account->config['replay_protection']['strategy'] ?? null;

        return $strategy === 'timestamp';
    }

    private function verifyTimestamp(Request $request, ConnectorAccount $account): bool|string
    {
        $windowSeconds = $account->config['replay_protection']['window_seconds'] ?? 300;

        // Slack uses X-Slack-Request-Timestamp
        $timestamp = $request->header('X-Slack-Request-Timestamp');

        if (! $timestamp) {
            return 'missing_timestamp';
        }

        $requestTime = (int) $timestamp;
        $currentTime = time();

        if (abs($currentTime - $requestTime) > $windowSeconds) {
            return 'timestamp_expired';
        }

        return true;
    }

    private function unauthorizedResponse(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 401);
    }
}
