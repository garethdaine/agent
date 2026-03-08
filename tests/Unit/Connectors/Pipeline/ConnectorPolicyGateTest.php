<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Pipeline\ConnectorPolicyGate;
use RuntimeException;
use Tests\TestCase;

class ConnectorPolicyGateTest extends TestCase
{
    private ConnectorPolicyGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new ConnectorPolicyGate;
    }

    public function test_allows_read_action_at_any_trust_score(): void
    {
        config()->set('connectors.write_actions', true);

        $connector = new AgentConnector;
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET']];

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: new AgentConnectorConnection,
            team: new Team,
            trustScore: 0.1,
        );

        $called = false;
        $this->gate->handle($request, function () use (&$called) {
            $called = true;

            return 'passed';
        });

        $this->assertTrue($called);
    }

    public function test_blocks_write_action_below_trust_threshold(): void
    {
        config()->set('connectors.write_actions', true);

        $connector = new AgentConnector;
        $connector->actions = [['name' => 'create_contact', 'method' => 'POST']];
        $connector->risk_level = 'standard';
        $connector->write_trust_threshold = 0.5;

        $request = new ActionRequest(
            connector: $connector,
            action: 'create_contact',
            parameters: [],
            connection: new AgentConnectorConnection,
            team: new Team,
            trustScore: 0.3,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient trust score');

        $this->gate->handle($request, fn () => null);
    }

    public function test_blocks_write_action_when_write_actions_flag_disabled(): void
    {
        config()->set('connectors.write_actions', false);

        $connector = new AgentConnector;
        $connector->actions = [['name' => 'create_contact', 'method' => 'POST']];

        $request = new ActionRequest(
            connector: $connector,
            action: 'create_contact',
            parameters: [],
            connection: new AgentConnectorConnection,
            team: new Team,
            trustScore: 1.0,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Write actions are disabled');

        $this->gate->handle($request, fn () => null);
    }

    public function test_allows_write_action_above_trust_threshold(): void
    {
        config()->set('connectors.write_actions', true);

        $connector = new AgentConnector;
        $connector->actions = [['name' => 'create_contact', 'method' => 'POST']];
        $connector->risk_level = 'standard';
        $connector->write_trust_threshold = 0.5;

        $request = new ActionRequest(
            connector: $connector,
            action: 'create_contact',
            parameters: [],
            connection: new AgentConnectorConnection,
            team: new Team,
            trustScore: 0.8,
        );

        $called = false;
        $this->gate->handle($request, function () use (&$called) {
            $called = true;

            return 'passed';
        });

        $this->assertTrue($called);
    }

    public function test_blocks_elevated_risk_connector_below_threshold(): void
    {
        config()->set('connectors.write_actions', true);

        $connector = new AgentConnector;
        $connector->actions = [['name' => 'delete_record', 'method' => 'DELETE']];
        $connector->risk_level = 'elevated';
        $connector->write_trust_threshold = 0.5;

        $request = new ActionRequest(
            connector: $connector,
            action: 'delete_record',
            parameters: [],
            connection: new AgentConnectorConnection,
            team: new Team,
            trustScore: 0.5,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient trust score');

        $this->gate->handle($request, fn () => null);
    }
}
