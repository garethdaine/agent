<?php

namespace App\Http\Middleware\Messenger;

use App\Models\ConnectorAccount;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ReplayProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ConnectorAccount|null $account */
        $account = $request->attributes->get('connector_account');

        if (! $account) {
            // Account should be set by VerifyWebhookSignature middleware
            return $next($request);
        }

        $eventId = $this->extractEventId($request, $account);

        if (! $eventId) {
            // No event ID available, proceed without deduplication
            return $next($request);
        }

        $cacheKey = $this->getCacheKey($account, $eventId);
        $ttlSeconds = $this->getTtlSeconds($account);

        // Check if this event was already processed
        if (Cache::has($cacheKey)) {
            Log::debug('Duplicate webhook event detected', [
                'provider' => $account->provider,
                'account_id' => $account->id,
                'event_id' => $eventId,
            ]);

            return $this->duplicateResponse();
        }

        // Mark event as processed
        Cache::put($cacheKey, true, $ttlSeconds);

        return $next($request);
    }

    private function extractEventId(Request $request, ConnectorAccount $account): ?string
    {
        return match ($account->provider) {
            ConnectorAccount::PROVIDER_SLACK => $request->input('event_id'),
            ConnectorAccount::PROVIDER_TELEGRAM => (string) $request->input('update_id'),
            ConnectorAccount::PROVIDER_DISCORD => $request->input('id'),
            ConnectorAccount::PROVIDER_WHATSAPP => $this->extractWhatsAppEventId($request),
            default => null,
        };
    }

    private function extractWhatsAppEventId(Request $request): ?string
    {
        // WhatsApp sends messages in entry[].changes[].value.messages[].id
        $messages = $request->input('entry.0.changes.0.value.messages', []);
        if (! empty($messages) && isset($messages[0]['id'])) {
            return $messages[0]['id'];
        }

        return null;
    }

    private function getCacheKey(ConnectorAccount $account, string $eventId): string
    {
        return "messenger:event_dedupe:{$account->id}:{$eventId}";
    }

    private function getTtlSeconds(ConnectorAccount $account): int
    {
        $strategy = $account->config['replay_protection']['strategy'] ?? 'timestamp';

        return match ($strategy) {
            'event_id' => $account->config['replay_protection']['dedupe_ttl_seconds'] ?? 3600,
            'timestamp' => $account->config['replay_protection']['window_seconds'] ?? 300,
            default => 3600,
        };
    }

    private function duplicateResponse(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'duplicate' => true,
        ], 200);
    }
}
