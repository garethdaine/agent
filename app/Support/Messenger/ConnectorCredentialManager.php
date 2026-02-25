<?php

namespace App\Support\Messenger;

use App\Models\ConnectorAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ConnectorCredentialManager
{
    /**
     * @return array<string, mixed>
     */
    public function schema(array $enabledProviders = []): array
    {
        $providers = [];

        foreach ($this->providerDefinitions() as $provider => $definition) {
            $providers[$provider] = [
                ...$definition,
                'enabled' => in_array($provider, $enabledProviders, true),
            ];
        }

        return [
            'providers' => $providers,
            'provider_order' => array_keys($providers),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function supportedProviders(): array
    {
        return array_keys($this->providerDefinitions());
    }

    public function supportsProvider(string $provider): bool
    {
        return array_key_exists($provider, $this->providerDefinitions());
    }

    public function normalizeConnectionMode(string $provider, ?string $mode): string
    {
        $definition = $this->providerDefinitions()[$provider] ?? null;
        if (! is_array($definition)) {
            return ConnectorAccount::MODE_WEBHOOK;
        }

        $requested = strtolower(trim((string) ($mode ?? '')));
        $supportedModes = Arr::wrap($definition['supported_connection_modes'] ?? [ConnectorAccount::MODE_WEBHOOK]);

        if (in_array($requested, $supportedModes, true)) {
            return $requested;
        }

        return (string) ($definition['default_connection_mode'] ?? ConnectorAccount::MODE_WEBHOOK);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public function normalizeCredentials(string $provider, array $credentials): array
    {
        $definition = $this->providerDefinitions()[$provider] ?? null;
        $fields = is_array($definition) ? Arr::wrap($definition['credential_fields'] ?? []) : [];

        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $credentials)) {
                continue;
            }

            $value = trim((string) ($credentials[$key] ?? ''));
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array<int, string>
     */
    public function missingRequiredCredentials(string $provider, array $credentials, string $connectionMode): array
    {
        $definition = $this->providerDefinitions()[$provider] ?? null;
        $fields = is_array($definition) ? Arr::wrap($definition['credential_fields'] ?? []) : [];
        $missing = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $required = (bool) ($field['required'] ?? false);
            $requiredModes = Arr::wrap($field['required_modes'] ?? []);

            if ($requiredModes !== [] && ! in_array($connectionMode, $requiredModes, true)) {
                $required = false;
            }

            if (! $required) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $value = trim((string) ($credentials[$key] ?? ''));
            if ($value === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function deriveAccountKey(string $provider, array $credentials): string
    {
        return match ($provider) {
            ConnectorAccount::PROVIDER_SLACK => $this->deriveSlackAccountKey($credentials),
            ConnectorAccount::PROVIDER_TELEGRAM => $this->deriveTelegramAccountKey($credentials),
            ConnectorAccount::PROVIDER_DISCORD => $this->deriveDiscordAccountKey($credentials),
            ConnectorAccount::PROVIDER_WHATSAPP => $this->deriveWhatsAppAccountKey($credentials),
            default => $this->hashAccountKey($provider, $credentials),
        };
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function inferWebhookSecret(string $provider, array $credentials): ?string
    {
        return match ($provider) {
            ConnectorAccount::PROVIDER_SLACK => $credentials['signing_secret'] ?? null,
            ConnectorAccount::PROVIDER_TELEGRAM => $credentials['secret_token'] ?? null,
            ConnectorAccount::PROVIDER_DISCORD => $credentials['public_key'] ?? null,
            ConnectorAccount::PROVIDER_WHATSAPP => $credentials['verify_token'] ?? null,
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $customConfig
     * @return array<string, mixed>
     */
    public function buildConfig(
        string $provider,
        array $credentials,
        array $customConfig = [],
        string $connectionMode = ConnectorAccount::MODE_WEBHOOK,
    ): array {
        $providerDefaults = config(sprintf('messenger.providers.%s', $provider), []);
        if (! is_array($providerDefaults)) {
            $providerDefaults = [];
        }

        $config = array_replace_recursive($providerDefaults, $customConfig);
        $config['connection_mode'] = $connectionMode;
        $config['configured_at'] = now()->toIso8601String();

        // Keep secrets in encrypted credentials/webhook_secret, not plaintext config.
        if (isset($config['signature_verification']) && is_array($config['signature_verification'])) {
            unset(
                $config['signature_verification']['signing_secret'],
                $config['signature_verification']['secret_token'],
                $config['signature_verification']['public_key'],
            );
        }

        return $config;
    }

    public function webhookUrl(ConnectorAccount $connector): ?string
    {
        return match ($connector->provider) {
            ConnectorAccount::PROVIDER_SLACK => route('agent.api.connectors.slack.webhook'),
            ConnectorAccount::PROVIDER_TELEGRAM => route('agent.api.connectors.telegram.webhook', ['accountKey' => $connector->account_key]),
            ConnectorAccount::PROVIDER_DISCORD => route('agent.api.connectors.discord.webhook'),
            ConnectorAccount::PROVIDER_WHATSAPP => route('agent.api.connectors.whatsapp.webhook'),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function requiredCredentialKeys(string $provider, string $connectionMode): array
    {
        $definition = $this->providerDefinitions()[$provider] ?? null;
        $fields = is_array($definition) ? Arr::wrap($definition['credential_fields'] ?? []) : [];
        $required = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $isRequired = (bool) ($field['required'] ?? false);
            $requiredModes = Arr::wrap($field['required_modes'] ?? []);

            if ($requiredModes !== [] && ! in_array($connectionMode, $requiredModes, true)) {
                $isRequired = false;
            }

            if (! $isRequired) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key !== '') {
                $required[] = $key;
            }
        }

        return $required;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function providerDefinitions(): array
    {
        return [
            ConnectorAccount::PROVIDER_SLACK => [
                'key' => ConnectorAccount::PROVIDER_SLACK,
                'label' => 'Slack',
                'description' => 'Connect a Slack app with bot token and signing secret.',
                'default_connection_mode' => ConnectorAccount::MODE_WEBHOOK,
                'supported_connection_modes' => [ConnectorAccount::MODE_WEBHOOK],
                'credential_fields' => [
                    [
                        'key' => 'bot_token',
                        'label' => 'Bot Token',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                        'placeholder' => 'xoxb-...',
                    ],
                    [
                        'key' => 'signing_secret',
                        'label' => 'Signing Secret',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                    [
                        'key' => 'team_id',
                        'label' => 'Team ID (optional)',
                        'type' => 'text',
                        'required' => false,
                        'secret' => false,
                        'placeholder' => 'T01234567',
                    ],
                    [
                        'key' => 'app_token',
                        'label' => 'App Token (optional)',
                        'type' => 'password',
                        'required' => false,
                        'secret' => true,
                        'placeholder' => 'xapp-...',
                    ],
                ],
            ],
            ConnectorAccount::PROVIDER_TELEGRAM => [
                'key' => ConnectorAccount::PROVIDER_TELEGRAM,
                'label' => 'Telegram',
                'description' => 'Connect a Telegram bot token and webhook secret token.',
                'default_connection_mode' => ConnectorAccount::MODE_WEBHOOK,
                'supported_connection_modes' => [ConnectorAccount::MODE_WEBHOOK],
                'credential_fields' => [
                    [
                        'key' => 'bot_token',
                        'label' => 'Bot Token',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                    [
                        'key' => 'secret_token',
                        'label' => 'Secret Token',
                        'type' => 'password',
                        'required' => true,
                        'required_modes' => [ConnectorAccount::MODE_WEBHOOK],
                        'secret' => true,
                    ],
                ],
            ],
            ConnectorAccount::PROVIDER_DISCORD => [
                'key' => ConnectorAccount::PROVIDER_DISCORD,
                'label' => 'Discord',
                'description' => 'Connect Discord interaction credentials (phase B provider).',
                'default_connection_mode' => ConnectorAccount::MODE_WEBHOOK,
                'supported_connection_modes' => [ConnectorAccount::MODE_WEBHOOK],
                'credential_fields' => [
                    [
                        'key' => 'application_id',
                        'label' => 'Application ID',
                        'type' => 'text',
                        'required' => true,
                        'secret' => false,
                    ],
                    [
                        'key' => 'bot_token',
                        'label' => 'Bot Token',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                    [
                        'key' => 'public_key',
                        'label' => 'Public Key',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                ],
            ],
            ConnectorAccount::PROVIDER_WHATSAPP => [
                'key' => ConnectorAccount::PROVIDER_WHATSAPP,
                'label' => 'WhatsApp',
                'description' => 'Connect WhatsApp Cloud API credentials.',
                'default_connection_mode' => ConnectorAccount::MODE_WEBHOOK,
                'supported_connection_modes' => [ConnectorAccount::MODE_WEBHOOK],
                'credential_fields' => [
                    [
                        'key' => 'phone_number_id',
                        'label' => 'Phone Number ID',
                        'type' => 'text',
                        'required' => true,
                        'secret' => false,
                    ],
                    [
                        'key' => 'access_token',
                        'label' => 'Access Token',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                    [
                        'key' => 'app_secret',
                        'label' => 'App Secret',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                    [
                        'key' => 'verify_token',
                        'label' => 'Verify Token',
                        'type' => 'password',
                        'required' => true,
                        'secret' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function deriveSlackAccountKey(array $credentials): string
    {
        $teamId = trim((string) ($credentials['team_id'] ?? ''));

        if ($teamId !== '') {
            return Str::limit($teamId, 64, '');
        }

        return $this->hashAccountKey(ConnectorAccount::PROVIDER_SLACK, $credentials);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function deriveTelegramAccountKey(array $credentials): string
    {
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));

        if ($botToken !== '' && str_contains($botToken, ':')) {
            $botId = trim((string) explode(':', $botToken, 2)[0]);
            if ($botId !== '') {
                return Str::limit($botId, 64, '');
            }
        }

        return $this->hashAccountKey(ConnectorAccount::PROVIDER_TELEGRAM, $credentials);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function deriveDiscordAccountKey(array $credentials): string
    {
        $applicationId = trim((string) ($credentials['application_id'] ?? ''));

        if ($applicationId !== '') {
            return Str::limit($applicationId, 64, '');
        }

        return $this->hashAccountKey(ConnectorAccount::PROVIDER_DISCORD, $credentials);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function deriveWhatsAppAccountKey(array $credentials): string
    {
        $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));

        if ($phoneNumberId !== '') {
            return Str::limit($phoneNumberId, 64, '');
        }

        return $this->hashAccountKey(ConnectorAccount::PROVIDER_WHATSAPP, $credentials);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function hashAccountKey(string $provider, array $credentials): string
    {
        ksort($credentials);
        $payload = json_encode($credentials);
        if (! is_string($payload)) {
            $payload = serialize($credentials);
        }

        return substr(hash('sha256', $provider.'|'.$payload), 0, 64);
    }
}
