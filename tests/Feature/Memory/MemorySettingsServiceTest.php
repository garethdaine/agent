<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\MemorySetting;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use App\Support\Memory\MemorySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for MemorySettingsService encrypted settings management.
 *
 * Verifies:
 * - set() encrypts value
 * - get() decrypts value
 * - getAll() masks API keys (show last 4 chars)
 * - Connection testing functionality
 */
class MemorySettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemorySettingsService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable memory for these tests
        config(['memory.enabled' => true]);

        $this->user = User::factory()->create();
        $this->service = app(MemorySettingsService::class);
    }

    /**
     * Test that set() stores encrypted value.
     */
    public function test_set_encrypts_value(): void
    {
        $apiKey = 'sk-test-1234567890abcdef';

        $this->service->set($this->user->id, 'provider_key_openai', $apiKey);

        // Verify the value is stored encrypted (not plain text in DB)
        // Use raw DB query to bypass Eloquent's encrypted cast
        $rawValue = \DB::table('memory_settings')
            ->where('user_id', $this->user->id)
            ->where('key', 'provider_key_openai')
            ->value('value');

        // The raw value should be encrypted (not equal to original)
        $this->assertNotEquals($apiKey, $rawValue);
        // Encrypted values are longer and contain different characters
        $this->assertNotEmpty($rawValue);
    }

    /**
     * Test that get() decrypts value.
     */
    public function test_get_decrypts_value(): void
    {
        $apiKey = 'sk-test-1234567890abcdef';

        $this->service->set($this->user->id, 'provider_key_openai', $apiKey);
        $retrieved = $this->service->get($this->user->id, 'provider_key_openai');

        $this->assertEquals($apiKey, $retrieved);
    }

    /**
     * Test that get() returns default for non-existent key.
     */
    public function test_get_returns_default_for_non_existent_key(): void
    {
        $result = $this->service->get($this->user->id, 'non_existent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    /**
     * Test that getAll() returns settings without masking (API keys now in credential_vault).
     */
    public function test_get_all_returns_settings_unmasked(): void
    {
        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o-mini');
        $this->service->set($this->user->id, 'extraction_provider', 'openai');

        $all = $this->service->getAll($this->user->id);

        // All settings returned as-is (no API keys in memory_settings anymore)
        $this->assertEquals('gpt-4o-mini', $all['extraction_model']);
        $this->assertEquals('openai', $all['extraction_provider']);
    }

    /**
     * Test that API keys are no longer stored in memory_settings.
     */
    public function test_api_keys_not_in_memory_settings(): void
    {
        // API keys should be managed via CredentialsManager, not memory_settings
        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o-mini');

        $all = $this->service->getAll($this->user->id);

        $this->assertArrayNotHasKey('provider_key_openai', $all);
        $this->assertArrayNotHasKey('provider_key_anthropic', $all);
    }

    /**
     * Test that settings are scoped to user.
     */
    public function test_settings_are_scoped_to_user(): void
    {
        $otherUser = User::factory()->create();

        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o-mini');
        $this->service->set($otherUser->id, 'extraction_model', 'claude-3-haiku');

        $userModel = $this->service->get($this->user->id, 'extraction_model');
        $otherUserModel = $this->service->get($otherUser->id, 'extraction_model');

        $this->assertEquals('gpt-4o-mini', $userModel);
        $this->assertEquals('claude-3-haiku', $otherUserModel);
    }

    /**
     * Test that set() updates existing value.
     */
    public function test_set_updates_existing_value(): void
    {
        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o-mini');
        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o');

        $value = $this->service->get($this->user->id, 'extraction_model');

        $this->assertEquals('gpt-4o', $value);

        // Should not create duplicate entries
        $count = MemorySetting::query()
            ->where('user_id', $this->user->id)
            ->where('key', 'extraction_model')
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test that delete() removes setting.
     */
    public function test_delete_removes_setting(): void
    {
        $this->service->set($this->user->id, 'extraction_model', 'gpt-4o-mini');

        $result = $this->service->delete($this->user->id, 'extraction_model');

        $this->assertTrue($result);
        $this->assertNull($this->service->get($this->user->id, 'extraction_model'));
    }

    /**
     * Test that delete() returns false for non-existent key.
     */
    public function test_delete_returns_false_for_non_existent_key(): void
    {
        $result = $this->service->delete($this->user->id, 'non_existent_key');

        $this->assertFalse($result);
    }

    /**
     * Test that isProviderKeyConfigured() checks credential_vault for non-empty key.
     */
    public function test_is_provider_key_configured_checks_for_non_empty_key(): void
    {
        $this->assertFalse($this->service->isProviderKeyConfigured($this->user->id, 'openai'));

        // Store key via CredentialsManager (the canonical source)
        $credentialsManager = app(CredentialsManager::class);
        $credentialsManager->store($this->user, 'openai', 'api_key', 'sk-test-key');

        $this->assertTrue($this->service->isProviderKeyConfigured($this->user->id, 'openai'));
    }

    /**
     * Test that isProviderKeyConfigured() returns false for empty string.
     */
    public function test_is_provider_key_configured_returns_false_for_empty_string(): void
    {
        $credentialsManager = app(CredentialsManager::class);
        $credentialsManager->store($this->user, 'openai', 'api_key', '');

        $this->assertFalse($this->service->isProviderKeyConfigured($this->user->id, 'openai'));
    }

    /**
     * Test that getConfiguredProviders() returns list of configured providers.
     */
    public function test_get_configured_providers_returns_list_of_configured_providers(): void
    {
        $credentialsManager = app(CredentialsManager::class);
        $credentialsManager->store($this->user, 'openai', 'api_key', 'sk-test-key');

        $providers = $this->service->getConfiguredProviders($this->user->id);

        $this->assertContains('openai', $providers);
        $this->assertNotContains('anthropic', $providers);
    }

    /**
     * Test that getAll() returns empty array when no settings exist.
     */
    public function test_get_all_returns_empty_array_when_no_settings_exist(): void
    {
        $all = $this->service->getAll($this->user->id);

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    /**
     * Test that non-key settings are returned unmasked.
     */
    public function test_non_key_settings_are_returned_unmasked(): void
    {
        $this->service->set($this->user->id, 'memory_enabled', 'true');
        $this->service->set($this->user->id, 'api_features_enabled', 'true');
        $this->service->set($this->user->id, 'extraction_provider', 'openai');

        $all = $this->service->getAll($this->user->id);

        $this->assertEquals('true', $all['memory_enabled']);
        $this->assertEquals('true', $all['api_features_enabled']);
        $this->assertEquals('openai', $all['extraction_provider']);
    }

    /**
     * Test that getEffectiveSettings() merges user settings with config defaults.
     */
    public function test_get_effective_settings_merges_with_config_defaults(): void
    {
        $this->service->set($this->user->id, 'extraction_model', 'custom-model');

        $effective = $this->service->getEffectiveSettings($this->user->id);

        // User-set value should override default
        $this->assertEquals('custom-model', $effective['extraction_model']);

        // Config defaults should be present
        $this->assertArrayHasKey('embeddings_model', $effective);
    }
}
