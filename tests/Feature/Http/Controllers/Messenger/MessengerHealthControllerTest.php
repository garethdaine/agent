<?php

namespace Tests\Feature\Http\Controllers\Messenger;

use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerHealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_http_200_always(): void
    {
        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
    }

    public function test_response_includes_aggregate_summary(): void
    {
        ConnectorAccount::factory()->slack()->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'summary' => [
                'total_connectors',
                'connected',
                'reconnecting',
                'disconnected',
                'error',
            ],
            'connectors',
        ]);
    }

    public function test_response_includes_per_connector_breakdown(): void
    {
        $connector = ConnectorAccount::factory()->slack()->create([
            'name' => 'Test Slack Workspace',
            'runtime_state' => WorkerHealthStatus::Connected,
            'connection_mode' => ConnectorAccount::MODE_LOCAL,
            'last_health_check_at' => now(),
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJsonStructure([
            'connectors' => [
                '*' => [
                    'id',
                    'name',
                    'provider',
                    'mode',
                    'runtime_state',
                    'last_health_check_at',
                    'error_message',
                ],
            ],
        ]);
        $response->assertJsonFragment([
            'id' => $connector->id,
            'name' => 'Test Slack Workspace',
            'provider' => 'slack',
            'mode' => 'local',
            'runtime_state' => 'connected',
        ]);
    }

    public function test_connector_shows_runtime_state_from_database(): void
    {
        ConnectorAccount::factory()->slack()->create([
            'runtime_state' => WorkerHealthStatus::Reconnecting,
        ]);
        ConnectorAccount::factory()->telegram()->create([
            'runtime_state' => WorkerHealthStatus::Error,
            'runtime_error_message' => 'Connection refused',
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJsonFragment(['runtime_state' => 'reconnecting']);
        $response->assertJsonFragment([
            'runtime_state' => 'error',
            'error_message' => 'Connection refused',
        ]);
    }

    public function test_shows_correct_counts(): void
    {
        // Create connectors with various states
        ConnectorAccount::factory()->count(2)->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Reconnecting,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Disconnected,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Error,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson([
            'summary' => [
                'total_connectors' => 5,
                'connected' => 2,
                'reconnecting' => 1,
                'disconnected' => 1,
                'error' => 1,
            ],
        ]);
    }

    public function test_no_auth_required(): void
    {
        // Make request without authentication
        $response = $this->getJson(route('messenger.health'));

        // Should return 200, not 401 Unauthorized
        $response->assertOk();
    }

    public function test_status_is_healthy_when_all_connected(): void
    {
        ConnectorAccount::factory()->count(3)->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson(['status' => 'healthy']);
    }

    public function test_status_is_degraded_when_any_error(): void
    {
        ConnectorAccount::factory()->count(2)->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Error,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson(['status' => 'degraded']);
    }

    public function test_status_is_degraded_when_any_reconnecting(): void
    {
        ConnectorAccount::factory()->count(2)->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Reconnecting,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson(['status' => 'degraded']);
    }

    public function test_status_is_partial_when_some_disconnected_but_no_errors(): void
    {
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Connected,
        ]);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Disconnected,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson(['status' => 'partial']);
    }

    public function test_returns_empty_connectors_array_when_none_exist(): void
    {
        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'healthy',
            'summary' => [
                'total_connectors' => 0,
                'connected' => 0,
                'reconnecting' => 0,
                'disconnected' => 0,
                'error' => 0,
            ],
            'connectors' => [],
        ]);
    }

    public function test_last_health_check_at_is_iso8601_format(): void
    {
        $timestamp = now()->setMicroseconds(0);
        ConnectorAccount::factory()->create([
            'runtime_state' => WorkerHealthStatus::Connected,
            'last_health_check_at' => $timestamp,
        ]);

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        $connectorData = $response->json('connectors.0');
        $this->assertEquals($timestamp->toIso8601String(), $connectorData['last_health_check_at']);
    }

    public function test_handles_default_runtime_state(): void
    {
        // Create connector with default runtime_state (disconnected)
        // The database has a NOT NULL constraint with default 'disconnected'
        ConnectorAccount::factory()->create();

        $response = $this->getJson(route('messenger.health'));

        $response->assertOk();
        // Factory defaults to WorkerHealthStatus::Disconnected
        $response->assertJsonFragment(['runtime_state' => 'disconnected']);
    }
}
