<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\ConnectorNotConnectedException;
use App\Support\Connectors\Exceptions\ConnectorUnhealthyException;
use App\Support\Connectors\Pipeline\ConnectorResolver;
use Tests\TestCase;

class ConnectorResolverTest extends TestCase
{
    private ConnectorResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ConnectorResolver;
    }

    public function test_resolves_connected_healthy_connector(): void
    {
        config()->set('connectors.enabled', true);

        $connector = new AgentConnector;
        $connector->name = 'test-connector';

        $connection = new AgentConnectorConnection;
        $connection->status = AgentConnectorConnection::STATUS_CONNECTED;
        $connection->health_score = 0.9;

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $called = false;
        $this->resolver->handle($request, function () use (&$called) {
            $called = true;

            return 'passed';
        });

        $this->assertTrue($called);
    }

    public function test_rejects_disconnected_connector(): void
    {
        config()->set('connectors.enabled', true);

        $connector = new AgentConnector;
        $connector->name = 'test-connector';

        $connection = new AgentConnectorConnection;
        $connection->status = AgentConnectorConnection::STATUS_DISCONNECTED;
        $connection->health_score = 1.0;

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->expectException(ConnectorNotConnectedException::class);

        $this->resolver->handle($request, fn () => null);
    }

    public function test_rejects_unhealthy_connector(): void
    {
        config()->set('connectors.enabled', true);

        $connector = new AgentConnector;
        $connector->name = 'test-connector';

        $connection = new AgentConnectorConnection;
        $connection->status = AgentConnectorConnection::STATUS_CONNECTED;
        $connection->health_score = 0.3;

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->expectException(ConnectorUnhealthyException::class);

        $this->resolver->handle($request, fn () => null);
    }

    public function test_rejects_when_feature_flag_disabled(): void
    {
        config()->set('connectors.enabled', false);

        $connector = new AgentConnector;
        $connector->name = 'test-connector';

        $connection = new AgentConnectorConnection;
        $connection->status = AgentConnectorConnection::STATUS_CONNECTED;
        $connection->health_score = 1.0;

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->expectException(ConnectorNotConnectedException::class);
        $this->expectExceptionMessage('disabled');

        $this->resolver->handle($request, fn () => null);
    }
}
