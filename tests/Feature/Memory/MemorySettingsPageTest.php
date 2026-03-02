<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\MemorySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Memory Settings Page Tests.
 *
 * Tests for the Vue.js Memory Settings page including:
 * - Page loads for authenticated users
 * - Provider keys display masked
 * - Connection test triggers API call
 * - Capabilities display current mode
 * - Settings save persists to database
 */
class MemorySettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Config::set('memory.enabled', true);
    }

    public function test_memory_settings_page_loads_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tools.memory.settings'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Memory/Settings')
        );
    }

    public function test_memory_settings_page_requires_authentication(): void
    {
        $response = $this->get(route('tools.memory.settings'));

        $response->assertRedirect(route('login'));
    }

    public function test_memory_index_page_loads_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tools.memory.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Memory/Index')
        );
    }

    public function test_provider_keys_display_masked_last_4_chars(): void
    {
        // Store an API key
        MemorySetting::setForUser($this->user->id, 'provider_key_openai', 'sk-1234567890abcdefghijklmnop');

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/memory/settings');

        $response->assertStatus(200);

        $settings = collect($response->json('data'));
        $apiKey = $settings->firstWhere('key', 'provider_key_openai');

        $this->assertNotNull($apiKey);
        // Verify key is masked but shows last 4 characters
        $this->assertStringContainsString('****', $apiKey['value']);
        $this->assertStringEndsWith('mnop', $apiKey['value']);
        // Verify full key is NOT exposed
        $this->assertStringNotContainsString('sk-1234567890', $apiKey['value']);
    }

    public function test_connection_test_triggers_api_call(): void
    {
        // Store an API key to test connection
        MemorySetting::setForUser($this->user->id, 'provider_key_openai', 'sk-test-key-for-connection');

        $response = $this->actingAs($this->user)
            ->postJson('/agent/api/v1/memory/settings/test-connection', [
                'provider' => 'openai',
            ]);

        // Should return success or failure, but always include expected structure
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'data' => [
                'provider',
                'success',
            ],
        ]);
        $response->assertJsonPath('data.provider', 'openai');
    }

    public function test_capabilities_display_current_mode(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/memory/settings/capabilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'mode',
                    'core_memory',
                    'working_memory',
                    'keyword_search',
                    'embeddings',
                    'entity_extraction',
                    'semantic_search',
                    'graph_search',
                    'configured_providers',
                ],
            ]);

        // In no-API mode (no provider keys), embeddings should be false
        $data = $response->json('data');
        $this->assertIsBool($data['core_memory']);
        $this->assertIsBool($data['working_memory']);
        $this->assertContains($data['mode'], ['no-api', 'api', 'degraded']);
    }

    public function test_settings_save_persists_to_database(): void
    {
        // Save settings via API
        $response = $this->actingAs($this->user)
            ->putJson('/agent/api/v1/memory/settings', [
                'settings' => [
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                    'embeddings_model' => 'text-embedding-3-small',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 3);

        // Verify settings were persisted
        $this->assertEquals(
            'gpt-4o-mini',
            MemorySetting::getForUser($this->user->id, 'extraction_model')
        );
        $this->assertEquals(
            'gpt-4.1-nano',
            MemorySetting::getForUser($this->user->id, 'summarization_model')
        );
        $this->assertEquals(
            'text-embedding-3-small',
            MemorySetting::getForUser($this->user->id, 'embeddings_model')
        );
    }

    public function test_settings_save_encrypts_api_keys(): void
    {
        $apiKey = 'sk-secret-api-key-12345';

        $response = $this->actingAs($this->user)
            ->putJson('/agent/api/v1/memory/settings', [
                'settings' => [
                    'provider_key_openai' => $apiKey,
                ],
            ]);

        $response->assertStatus(200);

        // Verify the raw database value is NOT the plaintext key
        $rawSetting = MemorySetting::where('user_id', $this->user->id)
            ->where('key', 'provider_key_openai')
            ->first();

        $this->assertNotNull($rawSetting);
        // The raw value should be encrypted (not match plaintext)
        $this->assertNotEquals($apiKey, $rawSetting->getRawOriginal('value'));

        // But decrypted via model should match
        $this->assertEquals($apiKey, MemorySetting::getForUser($this->user->id, 'provider_key_openai'));
    }

    public function test_tools_index_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tools.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tools/Index')
        );
    }
}
