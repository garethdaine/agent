<?php

declare(strict_types=1);

namespace App\Jobs\Connectors;

use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshConnectorCredentialsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('connector-credentials');
    }

    public function handle(ConnectorVaultEncrypter $encrypter): void
    {
        $credentials = AgentConnectorCredential::where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->whereIn('auth_type', ['oauth2_authorization_code', 'oauth2_client_credentials'])
            ->where('token_expires_at', '<=', now()->addMinutes(5))
            ->where('token_expires_at', '>', now())
            ->with(['connector', 'team'])
            ->get();

        foreach ($credentials as $credential) {
            $this->refreshCredential($credential, $encrypter);
        }
    }

    private function refreshCredential(AgentConnectorCredential $credential, ConnectorVaultEncrypter $encrypter): void
    {
        try {
            $encryptedData = $credential->encrypted_data;
            if (is_resource($encryptedData)) {
                $encryptedData = stream_get_contents($encryptedData);
            }

            $decrypted = json_decode(
                $encrypter->decrypt($credential->team, (string) $encryptedData),
                true,
            );

            $refreshToken = $decrypted['refresh_token'] ?? null;
            if (! $refreshToken) {
                return;
            }

            $authConfig = $credential->connector->auth_config ?? [];
            $tokenUrl = $authConfig['token_url'] ?? null;

            if (! $tokenUrl) {
                return;
            }

            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $authConfig['client_id'] ?? '',
                'client_secret' => $authConfig['client_secret'] ?? '',
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $decrypted['access_token'] = $tokenData['access_token'];

                if (isset($tokenData['refresh_token'])) {
                    $decrypted['refresh_token'] = $tokenData['refresh_token'];
                }

                $credential->update([
                    'encrypted_data' => $encrypter->encrypt($credential->team, json_encode($decrypted)),
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                    'last_refreshed_at' => now(),
                    'refresh_failure_count' => 0,
                ]);

                $this->logEvent($credential, 'refreshed');
            } else {
                $this->handleRefreshFailure($credential);
            }
        } catch (\Throwable $e) {
            Log::error('Credential refresh failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            $this->handleRefreshFailure($credential);
        }
    }

    private function handleRefreshFailure(AgentConnectorCredential $credential): void
    {
        $failureCount = $credential->refresh_failure_count + 1;
        $updates = ['refresh_failure_count' => $failureCount];

        if ($failureCount >= 3) {
            $updates['status'] = AgentConnectorCredential::STATUS_DEGRADED;
        }

        $credential->update($updates);

        $this->logEvent($credential, 'refresh_failed', [
            'failure_count' => $failureCount,
        ]);
    }

    private function logEvent(AgentConnectorCredential $credential, string $eventType, array $details = []): void
    {
        AgentConnectorCredentialEvent::create([
            'credential_id' => $credential->id,
            'event_type' => $eventType,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
