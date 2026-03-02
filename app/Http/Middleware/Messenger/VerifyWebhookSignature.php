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

        // WhatsApp GET requests are verification challenges that don't have signatures.
        // The verification is handled in the controller by checking verify_token.
        if ($provider === ConnectorAccount::PROVIDER_WHATSAPP && $request->isMethod('get')) {
            return $next($request);
        }

        $adapter = $this->connectorManager->resolve($provider);
        $account = $this->resolveAccount($provider, $request, $adapter);

        if (! $account) {
            Log::warning('Webhook signature verification failed: account not found', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            // Return 404 when account identifier was explicitly provided in route
            if ($request->route('account') || $request->route('accountKey')) {
                return $this->notFoundResponse('ACCOUNT_NOT_FOUND', 'Connector account not found.');
            }

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

        // Backward-compatible routes may pass connector account in path.
        // Support both UUID id and account_key lookups.
        $accountIdentifier = $request->route('account');
        if (is_string($accountIdentifier) && $accountIdentifier !== '') {
            return ConnectorAccount::query()
                ->forProvider($provider)
                ->where(function ($query) use ($accountIdentifier): void {
                    $query->where('id', $accountIdentifier)
                        ->orWhere('account_key', $accountIdentifier);
                })
                ->active()
                ->first();
        }

        // For Slack, extract team_id from payload
        if ($provider === ConnectorAccount::PROVIDER_SLACK) {
            $teamId = $request->input('team_id') ?? $request->input('event.team');
            if ($teamId) {
                $account = ConnectorAccount::query()
                    ->forProvider($provider)
                    ->where('account_key', $teamId)
                    ->active()
                    ->first();

                if ($account !== null) {
                    return $account;
                }
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

        $timestamp = $this->extractTimestampHeader($request, $account);

        if (! $timestamp) {
            return 'missing_timestamp';
        }

        if (! is_numeric($timestamp)) {
            return 'invalid_timestamp';
        }

        $requestTime = (int) $timestamp;
        $currentTime = time();

        if (abs($currentTime - $requestTime) > $windowSeconds) {
            return 'timestamp_expired';
        }

        return true;
    }

    private function extractTimestampHeader(Request $request, ConnectorAccount $account): ?string
    {
        $headers = match ($account->provider) {
            ConnectorAccount::PROVIDER_DISCORD => ['X-Signature-Timestamp'],
            ConnectorAccount::PROVIDER_SLACK => ['X-Slack-Request-Timestamp'],
            default => ['X-Slack-Request-Timestamp', 'X-Signature-Timestamp'],
        };

        foreach ($headers as $header) {
            $value = $request->header($header);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
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

    private function notFoundResponse(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 404);
    }
}
