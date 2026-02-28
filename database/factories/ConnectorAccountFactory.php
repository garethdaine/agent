<?php

namespace Database\Factories;

use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConnectorAccount>
 */
class ConnectorAccountFactory extends Factory
{
    protected $model = ConnectorAccount::class;

    public function definition(): array
    {
        $provider = $this->faker->randomElement([
            ConnectorAccount::PROVIDER_SLACK,
            ConnectorAccount::PROVIDER_TELEGRAM,
        ]);

        return [
            'id' => Str::uuid()->toString(),
            'provider' => $provider,
            'name' => $this->faker->company().' Workspace',
            'credentials' => $this->getDefaultCredentials($provider),
            'webhook_secret' => Str::random(32),
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'runtime_state' => WorkerHealthStatus::Disconnected,
            'config' => $this->getDefaultConfig($provider),
            'account_key' => Str::random(16),
        ];
    }

    public function slack(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'credentials' => [
                'bot_token' => 'xoxb-'.Str::random(24),
                'signing_secret' => Str::random(32),
            ],
            'config' => $this->getDefaultConfig(ConnectorAccount::PROVIDER_SLACK),
        ]);
    }

    public function telegram(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'credentials' => [
                'bot_token' => $this->faker->numberBetween(100000000, 999999999).':'.Str::random(35),
            ],
            'config' => $this->getDefaultConfig(ConnectorAccount::PROVIDER_TELEGRAM),
        ]);
    }

    public function discord(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'credentials' => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.'.Str::random(35),
                'application_id' => (string) $this->faker->numberBetween(1000000000000000000, 9999999999999999999),
                'public_key' => Str::random(64),
            ],
            'config' => $this->getDefaultConfig(ConnectorAccount::PROVIDER_DISCORD),
        ]);
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConnectorAccount::STATUS_DISCONNECTED,
        ]);
    }

    public function localMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
        ]);
    }

    public function webhookMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
        ]);
    }

    private function getDefaultCredentials(string $provider): array
    {
        return match ($provider) {
            ConnectorAccount::PROVIDER_SLACK => [
                'bot_token' => 'xoxb-'.Str::random(24),
                'signing_secret' => Str::random(32),
            ],
            ConnectorAccount::PROVIDER_TELEGRAM => [
                'bot_token' => $this->faker->numberBetween(100000000, 999999999).':'.Str::random(35),
            ],
            ConnectorAccount::PROVIDER_DISCORD => [
                'bot_token' => 'MTEyMzQ1Njc4OTAxMjM0NTY3OA.GxYmZc.'.Str::random(35),
                'application_id' => (string) $this->faker->numberBetween(1000000000000000000, 9999999999999999999),
            ],
            default => [],
        };
    }

    private function getDefaultConfig(string $provider): array
    {
        $baseConfig = [
            'confirmation_required' => true,
            'session_history_limit' => 20,
            'default_verbosity' => 'summary',
        ];

        return match ($provider) {
            ConnectorAccount::PROVIDER_SLACK => array_merge($baseConfig, [
                'signature_verification' => [
                    'scheme' => 'hmac_sha256',
                    'signing_secret' => Str::random(32),
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
                'threading_mode' => 'native',
                'threading_fallback' => 'edit',
            ]),
            ConnectorAccount::PROVIDER_TELEGRAM => array_merge($baseConfig, [
                'signature_verification' => [
                    'scheme' => 'token',
                ],
                'replay_protection' => [
                    'strategy' => 'event_id',
                    'dedupe_ttl_seconds' => 3600,
                ],
                'threading_mode' => 'reply_to',
                'threading_fallback' => 'quote',
            ]),
            ConnectorAccount::PROVIDER_DISCORD => array_merge($baseConfig, [
                'signature_verification' => [
                    'scheme' => 'ed25519',
                ],
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
                'threading_mode' => 'native',
                'threading_fallback' => 'edit',
            ]),
            default => $baseConfig,
        };
    }
}
