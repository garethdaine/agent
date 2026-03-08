<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use App\Support\Connectors\Pipeline\PiiRedactor;
use Tests\TestCase;

class PiiRedactorTest extends TestCase
{
    private PiiRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new PiiRedactor;

        config()->set('connectors.pii_redaction', [
            'enabled' => true,
            'replacement' => '[REDACTED]',
        ]);
    }

    public function test_redacts_declared_pii_fields(): void
    {
        $connector = new AgentConnector;
        $connector->actions = [[
            'name' => 'get_contact',
            'method' => 'GET',
            'pii_fields' => ['EmailAddress'],
        ]];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_action_response', new ActionResponse(
            status: 200,
            data: [
                'Name' => 'John Doe',
                'EmailAddress' => 'john@example.com',
            ],
        ));

        $request = new ActionRequest(
            connector: $connector,
            action: 'get_contact',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->redactor->handle($request, fn ($r) => $r);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals('[REDACTED]', $response->data['EmailAddress']);
        $this->assertEquals('John Doe', $response->data['Name']);
    }

    public function test_handles_nested_field_paths(): void
    {
        $connector = new AgentConnector;
        $connector->actions = [[
            'name' => 'get_contact',
            'method' => 'GET',
            'pii_fields' => ['contact.email', 'contact.phone'],
        ]];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_action_response', new ActionResponse(
            status: 200,
            data: [
                'contact' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                    'phone' => '+44123456',
                ],
            ],
        ));

        $request = new ActionRequest(
            connector: $connector,
            action: 'get_contact',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->redactor->handle($request, fn ($r) => $r);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals('[REDACTED]', $response->data['contact']['email']);
        $this->assertEquals('[REDACTED]', $response->data['contact']['phone']);
        $this->assertEquals('Jane Doe', $response->data['contact']['name']);
    }

    public function test_no_op_when_no_pii_fields_declared(): void
    {
        $connector = new AgentConnector;
        $connector->actions = [[
            'name' => 'list_products',
            'method' => 'GET',
        ]];

        $originalData = ['id' => '1', 'name' => 'Product A', 'price' => 9.99];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_action_response', new ActionResponse(
            status: 200,
            data: $originalData,
        ));

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_products',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->redactor->handle($request, fn ($r) => $r);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals($originalData, $response->data);
    }
}
