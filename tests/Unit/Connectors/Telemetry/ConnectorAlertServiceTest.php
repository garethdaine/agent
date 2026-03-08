<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Telemetry;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use App\Support\Connectors\Telemetry\ConnectorAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConnectorAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAlertService $alertService;

    private SystemNotificationDispatcher $dispatcher;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = Mockery::mock(SystemNotificationDispatcher::class);
        $this->alertService = new ConnectorAlertService($this->dispatcher);
        $this->user = User::factory()->create();

        config()->set('connectors.health.thresholds', [
            'healthy' => 0.8,
            'degraded' => 0.5,
        ]);
    }

    public function test_credential_expiring_triggers_warning(): void
    {
        $connector = AgentConnector::factory()->create(['display_name' => 'Xero']);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'connected_by' => $this->user->id,
        ]);
        $credential = AgentConnectorCredential::factory()->create([
            'team_id' => $connection->team_id,
            'connector_id' => $connector->id,
            'token_expires_at' => now()->addDays(5),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);
        $connection->setRelation('credential', $credential);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_WARNING
                    && str_contains($payload->type, 'connector.credential_expiring');
            });

        $this->alertService->checkCredentialExpiry($connection, daysThreshold: 7);
    }

    public function test_credential_not_expiring_does_not_trigger_alert(): void
    {
        $connector = AgentConnector::factory()->create(['display_name' => 'Slack']);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'connected_by' => $this->user->id,
        ]);
        $credential = AgentConnectorCredential::factory()->create([
            'team_id' => $connection->team_id,
            'connector_id' => $connector->id,
            'token_expires_at' => now()->addDays(30),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);
        $connection->setRelation('credential', $credential);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldNotReceive('dispatch');

        $this->alertService->checkCredentialExpiry($connection, daysThreshold: 7);
    }

    public function test_rate_limit_80_percent_triggers_warning(): void
    {
        $connector = AgentConnector::factory()->create([
            'display_name' => 'Xero',
            'rate_limits' => ['daily_limit' => 5000],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'action_count_24h' => 4000,
            'connected_by' => $this->user->id,
        ]);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_WARNING
                    && str_contains($payload->type, 'connector.rate_limit_warning');
            });

        $this->alertService->checkRateLimitUtilization($connection);
    }

    public function test_rate_limit_exhausted_triggers_critical(): void
    {
        $connector = AgentConnector::factory()->create([
            'display_name' => 'Xero',
            'rate_limits' => ['daily_limit' => 5000],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'action_count_24h' => 5000,
            'connected_by' => $this->user->id,
        ]);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_ERROR
                    && str_contains($payload->type, 'connector.rate_limit_exhausted');
            });

        $this->alertService->checkRateLimitUtilization($connection);
    }

    public function test_connector_degraded_triggers_warning(): void
    {
        $connector = AgentConnector::factory()->create(['display_name' => 'HubSpot']);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'health_score' => 0.45,
            'connected_by' => $this->user->id,
        ]);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (SystemNotificationPayload $payload) {
                return $payload->severity === SystemNotificationPayload::SEVERITY_WARNING
                    && str_contains($payload->type, 'connector.health_degraded');
            });

        $this->alertService->checkHealthScore($connection);
    }

    public function test_healthy_connector_does_not_trigger_alert(): void
    {
        $connector = AgentConnector::factory()->create(['display_name' => 'Slack']);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'health_score' => 0.95,
            'connected_by' => $this->user->id,
        ]);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldNotReceive('dispatch');

        $this->alertService->checkHealthScore($connection);
    }

    public function test_rate_limit_below_threshold_does_not_trigger_alert(): void
    {
        $connector = AgentConnector::factory()->create([
            'display_name' => 'Stripe',
            'rate_limits' => ['daily_limit' => 5000],
        ]);
        $connection = AgentConnectorConnection::factory()->create([
            'connector_id' => $connector->id,
            'action_count_24h' => 2000,
            'connected_by' => $this->user->id,
        ]);
        $connection->setRelation('connector', $connector);

        $this->dispatcher->shouldNotReceive('dispatch');

        $this->alertService->checkRateLimitUtilization($connection);
    }
}
