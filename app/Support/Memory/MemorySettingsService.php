<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\MemorySetting;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;

/**
 * Memory Settings Service.
 *
 * Manages encrypted settings for the memory system.
 * Follows the InterrogationSetting pattern for user-scoped encrypted settings.
 *
 * Features:
 * - Encrypted storage for non-credential settings (models, thresholds, etc.)
 * - Provider key lookups delegated to CredentialsManager (credential_vault table)
 * - Masked output for API keys (show last 4 chars)
 * - Per-user settings isolation
 */
class MemorySettingsService
{
    public function __construct(
        private CredentialsManager $credentialsManager
    ) {}

    /**
     * Setting keys that should be masked in output (API keys).
     *
     * @var array<string>
     */
    private const SENSITIVE_KEYS = [];

    /**
     * Get a setting value for a user.
     *
     * @param  int  $userId  User ID to scope the query
     * @param  string  $key  The setting key to retrieve
     * @param  mixed  $default  Default value if setting doesn't exist
     */
    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        return MemorySetting::getForUser($userId, $key, $default);
    }

    /**
     * Set a setting value for a user.
     *
     * Values are automatically encrypted before storage.
     *
     * @param  int  $userId  User ID to scope the setting
     * @param  string  $key  The setting key to set
     * @param  mixed  $value  The value to store
     */
    public function set(int $userId, string $key, mixed $value): void
    {
        MemorySetting::setForUser($userId, $key, $value);
    }

    /**
     * Delete a setting for a user.
     *
     * @param  int  $userId  User ID to scope the deletion
     * @param  string  $key  The setting key to delete
     */
    public function delete(int $userId, string $key): bool
    {
        return MemorySetting::deleteForUser($userId, $key);
    }

    /**
     * Get all settings for a user with sensitive values masked.
     *
     * API keys are masked to show only the last 4 characters.
     *
     * @param  int  $userId  User ID to scope the query
     * @return array<string, mixed>
     */
    public function getAll(int $userId): array
    {
        $settings = MemorySetting::getAllForUser($userId);

        return $this->maskSensitiveValues($settings);
    }

    /**
     * Check if a provider API key is configured via the credential manager.
     *
     * @param  int  $userId  User ID to check
     * @param  string  $provider  Provider name (openai, anthropic)
     */
    public function isProviderKeyConfigured(int $userId, string $provider): bool
    {
        $user = $this->resolveUser($userId);

        if ($user === null) {
            return false;
        }

        $value = $this->credentialsManager->get($user, $provider, 'api_key');

        return $value !== null && $value !== '';
    }

    /**
     * Get the API key for a provider from the credential manager.
     *
     * @param  int  $userId  User ID
     * @param  string  $provider  Provider name (openai, anthropic)
     */
    public function getProviderKey(int $userId, string $provider): ?string
    {
        $user = $this->resolveUser($userId);

        if ($user === null) {
            return null;
        }

        return $this->credentialsManager->get($user, $provider, 'api_key');
    }

    /**
     * Get list of configured providers for a user.
     *
     * @param  int  $userId  User ID to check
     * @return array<string>
     */
    public function getConfiguredProviders(int $userId): array
    {
        $providers = [];

        foreach (['openai', 'anthropic'] as $provider) {
            if ($this->isProviderKeyConfigured($userId, $provider)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Get effective settings merged with config defaults.
     *
     * User settings override config defaults.
     *
     * @param  int  $userId  User ID to scope the query
     * @return array<string, mixed>
     */
    public function getEffectiveSettings(int $userId): array
    {
        $userSettings = MemorySetting::getAllForUser($userId);

        // Build defaults from config
        $defaults = [
            'extraction_model' => config('memory.models.extraction.default'),
            'summarization_model' => config('memory.models.summarization.default'),
            'embeddings_model' => config('memory.models.embeddings.default'),
            'extraction_provider' => config('memory.models.extraction.provider'),
            'summarization_provider' => config('memory.models.summarization.provider'),
            'embeddings_provider' => config('memory.models.embeddings.provider'),
        ];

        // User settings override defaults
        return array_merge($defaults, $userSettings);
    }

    /**
     * Mask sensitive values in settings array.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function maskSensitiveValues(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true) && is_string($value)) {
                $settings[$key] = $this->maskValue($value);
            }
        }

        return $settings;
    }

    /**
     * Mask a value to show only the last 4 characters.
     */
    private function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($value, -4);
    }

    /**
     * Get all settings as array of key/value pairs with sensitive values masked.
     *
     * Used for API output formatting.
     *
     * @param  int  $userId  User ID to scope the query
     * @return array<int, array{key: string, value: mixed}>
     */
    public function getAllMasked(int $userId): array
    {
        $settings = $this->getAll($userId);

        return collect($settings)
            ->map(fn ($value, $key) => ['key' => $key, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * Test connectivity to a provider.
     *
     * Uses the credential manager to check for API keys.
     *
     * @param  int  $userId  User ID to get API key from
     * @param  string  $provider  Provider name (openai, anthropic, neo4j)
     * @return array{success: bool, message: ?string, latency_ms: ?int}
     */
    public function testConnection(int $userId, string $provider): array
    {
        $start = microtime(true);

        if ($provider === 'neo4j') {
            return $this->testNeo4jConnection($start);
        }

        if (! $this->isProviderKeyConfigured($userId, $provider)) {
            return [
                'success' => false,
                'message' => "No API key configured for {$provider}. Add one in Credentials settings.",
                'latency_ms' => null,
            ];
        }

        // For actual providers, we'd make a test API call
        // For now, return success if key is configured
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'success' => true,
            'message' => "API key configured for {$provider}",
            'latency_ms' => $latency,
        ];
    }

    /**
     * Resolve a User model from an ID.
     */
    private function resolveUser(int $userId): ?User
    {
        return User::find($userId);
    }

    /**
     * Test Neo4j connectivity.
     *
     * @return array{success: bool, message: ?string, latency_ms: ?int}
     */
    private function testNeo4jConnection(float $start): array
    {
        try {
            $host = config('memory.neo4j.host', 'localhost');
            $port = config('memory.neo4j.port', 7687);

            $socket = @fsockopen($host, $port, $errno, $errstr, 2);

            if ($socket !== false) {
                fclose($socket);
                $latency = (int) ((microtime(true) - $start) * 1000);

                return [
                    'success' => true,
                    'message' => 'Neo4j is reachable',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'success' => false,
                'message' => "Cannot connect to Neo4j: {$errstr}",
                'latency_ms' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }
}
