<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ConnectorAccount;
use App\Support\Messenger\ConnectorCredentialManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConnectorAccount
 */
class MessengerConnectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $credentialManager = app(ConnectorCredentialManager::class);
        $requiredCredentialKeys = $credentialManager->requiredCredentialKeys($this->provider, $this->connection_mode);
        $configuredCredentials = array_keys(array_filter($this->credentials ?? [], static fn ($value): bool => trim((string) $value) !== ''));
        $missingRequiredCredentials = array_values(array_diff($requiredCredentialKeys, $configuredCredentials));

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'connection_mode' => $this->connection_mode,
            'status' => $this->status,
            'config' => $this->getPublicConfig(),
            'credential_state' => [
                'required' => $requiredCredentialKeys,
                'configured' => $configuredCredentials,
                'missing' => $missingRequiredCredentials,
            ],
            'setup' => [
                'webhook_url' => $credentialManager->webhookUrl($this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sessions_count' => $this->whenCounted('sessions'),
            'is_connected' => $this->isConnected(),
            'is_local_mode' => $this->isLocalMode(),
            'runtime_state' => $this->runtime_state?->value ?? 'disconnected',
            'runtime_error_message' => $this->runtime_error_message,
            'last_health_check_at' => $this->last_health_check_at?->toIso8601String(),
        ];
    }

    /**
     * Return config without sensitive fields.
     *
     * @return array<string, mixed>
     */
    private function getPublicConfig(): array
    {
        $config = $this->config ?? [];

        $publicFields = [
            'approval_mode',
            'confirmation_required',
            'link_expiration_days',
            'runner_type',
            'session_history_limit',
            'session_history_window',
            'default_verbosity',
            'threading_mode',
            'threading_fallback',
            'channel_restrictions',
            'max_file_size_mb',
            'allowed_mime_types',
        ];

        return array_intersect_key($config, array_flip($publicFields));
    }
}
