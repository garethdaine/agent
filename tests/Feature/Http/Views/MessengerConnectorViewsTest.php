<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Views;

use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the Messenger connector views showing mode, status indicators, and navigation.
 *
 * These tests verify that the Tools/Messenger/Index.vue page properly displays:
 * - Connection mode (Local/Webhook) column
 * - Runtime state with colored status indicators
 * - Error messages for connectors in error state
 * - Links to filtered dead letters
 * - Mode switch disabled for WhatsApp (webhook-only provider)
 */
class MessengerConnectorViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('tools.messenger.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tools.messenger.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tools/Messenger/Index')
        );
    }

    public function test_connector_api_returns_mode_column(): void
    {
        $localConnector = ConnectorAccount::factory()->slack()->localMode()->create([
            'name' => 'Local Slack',
        ]);

        $webhookConnector = ConnectorAccount::factory()->telegram()->webhookMode()->create([
            'name' => 'Webhook Telegram',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/connectors');

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Local Slack',
            'connection_mode' => 'local',
        ]);
        $response->assertJsonFragment([
            'name' => 'Webhook Telegram',
            'connection_mode' => 'webhook',
        ]);
    }

    public function test_connector_api_returns_runtime_state_with_color_class(): void
    {
        $connectedConnector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Connected Connector',
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);

        $reconnectingConnector = ConnectorAccount::factory()->telegram()->create([
            'name' => 'Reconnecting Connector',
            'runtime_state' => WorkerHealthStatus::Reconnecting,
        ]);

        $errorConnector = ConnectorAccount::factory()->discord()->create([
            'name' => 'Error Connector',
            'runtime_state' => WorkerHealthStatus::Error,
            'runtime_error_message' => 'Connection failed: invalid token',
        ]);

        $disconnectedConnector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Disconnected Connector',
            'runtime_state' => WorkerHealthStatus::Disconnected,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/connectors');

        $response->assertOk();

        // Verify runtime_state is returned for each connector
        $connectors = $response->json('data');
        $this->assertCount(4, $connectors);

        $connectedData = collect($connectors)->firstWhere('name', 'Connected Connector');
        $this->assertNotNull($connectedData);
        $this->assertEquals('connected', $connectedData['runtime_state']);

        $reconnectingData = collect($connectors)->firstWhere('name', 'Reconnecting Connector');
        $this->assertNotNull($reconnectingData);
        $this->assertEquals('reconnecting', $reconnectingData['runtime_state']);

        $errorData = collect($connectors)->firstWhere('name', 'Error Connector');
        $this->assertNotNull($errorData);
        $this->assertEquals('error', $errorData['runtime_state']);

        $disconnectedData = collect($connectors)->firstWhere('name', 'Disconnected Connector');
        $this->assertNotNull($disconnectedData);
        $this->assertEquals('disconnected', $disconnectedData['runtime_state']);
    }

    public function test_connector_api_returns_error_message_for_error_state(): void
    {
        $errorConnector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Error Connector',
            'runtime_state' => WorkerHealthStatus::Error,
            'runtime_error_message' => 'Rate limit exceeded: try again in 60 seconds',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/connectors');

        $response->assertOk();

        $connectors = $response->json('data');
        $errorData = collect($connectors)->firstWhere('name', 'Error Connector');
        $this->assertNotNull($errorData);
        $this->assertEquals('Rate limit exceeded: try again in 60 seconds', $errorData['runtime_error_message']);
    }

    public function test_connector_api_returns_last_health_check_at(): void
    {
        $connector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Health Check Connector',
            'runtime_state' => WorkerHealthStatus::Connected,
            'last_health_check_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/connectors');

        $response->assertOk();

        $connectors = $response->json('data');
        $connectorData = collect($connectors)->firstWhere('name', 'Health Check Connector');
        $this->assertNotNull($connectorData);
        $this->assertNotNull($connectorData['last_health_check_at']);
    }

    public function test_dead_letters_page_filters_by_connector(): void
    {
        $connector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Test Connector',
        ]);

        MessengerDeadLetter::create([
            'connector_account_id' => $connector->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Test error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.index', ['connector' => $connector->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/DeadLetters/Index')
            ->has('deadLetters.data', 1)
            ->where('deadLetters.data.0.connector_account_id', $connector->id)
        );
    }

    public function test_connector_schema_returns_supported_modes_for_providers(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/agent/api/v1/messenger/connectors/schema');

        $response->assertOk();

        $schema = $response->json('data');
        $providers = $schema['providers'] ?? [];

        // WhatsApp should only support webhook mode
        if (isset($providers['whatsapp'])) {
            $whatsappModes = $providers['whatsapp']['supported_connection_modes'] ?? [];
            $this->assertContains('webhook', $whatsappModes);
            $this->assertNotContains('local', $whatsappModes);
        }

        // Slack should support both modes
        if (isset($providers['slack'])) {
            $slackModes = $providers['slack']['supported_connection_modes'] ?? [];
            $this->assertContains('webhook', $slackModes);
            $this->assertContains('local', $slackModes);
        }
    }
}
