<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use App\Support\Connectors\Pipeline\ConnectorHttpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectorHttpClientTest extends TestCase
{
    private ConnectorHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new ConnectorHttpClient;

        config()->set('connectors.retry', [
            'max_attempts' => 3,
            'backoff_seconds' => [0, 0, 0], // No actual delay in tests
        ]);
        config()->set('connectors.default_action_timeout_seconds', 30);
    }

    public function test_executes_get_request_with_oauth_bearer_token(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['contacts' => []], 200),
        ]);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', ['Authorization' => 'Bearer test-token-123']);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSent(function ($req) {
            return $req->hasHeader('Authorization', 'Bearer test-token-123')
                && $req->method() === 'GET';
        });

        $response = $connection->getAttribute('_action_response');
        $this->assertInstanceOf(ActionResponse::class, $response);
        $this->assertEquals(200, $response->status);
    }

    public function test_executes_post_request_with_api_key_header(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['id' => '123'], 201),
        ]);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'create_contact', 'method' => 'POST', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', ['X-API-Key' => 'my-api-key']);

        $request = new ActionRequest(
            connector: $connector,
            action: 'create_contact',
            parameters: ['name' => 'Test'],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSent(function ($req) {
            return $req->hasHeader('X-API-Key', 'my-api-key')
                && $req->method() === 'POST';
        });

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals(201, $response->status);
    }

    public function test_retries_on_500_with_exponential_backoff(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'Internal'], 500)
            ->push(['contacts' => []], 200);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', []);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSentCount(2);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals(200, $response->status);
        $this->assertEquals(1, $connection->getAttribute('_retry_count'));
    }

    public function test_retries_on_429_with_backoff(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'Too Many Requests'], 429)
            ->push(['data' => []], 200);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', []);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSentCount(2);
    }

    public function test_no_retry_on_401(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', []);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSentCount(1);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals(401, $response->status);
    }

    public function test_no_retry_on_404(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'get_contact', 'method' => 'GET', 'path' => '/contacts/1']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', []);

        $request = new ActionRequest(
            connector: $connector,
            action: 'get_contact',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        Http::assertSentCount(1);

        $response = $connection->getAttribute('_action_response');
        $this->assertEquals(404, $response->status);
    }

    public function test_timeout_produces_timeout_outcome(): void
    {
        Http::fake([
            'api.example.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $connector = new AgentConnector;
        $connector->base_url = 'https://api.example.com';
        $connector->actions = [['name' => 'list_contacts', 'method' => 'GET', 'path' => '/contacts']];

        $connection = new AgentConnectorConnection;
        $connection->setAttribute('_auth_headers', []);

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $this->client->handle($request, fn ($r) => $r);

        $response = $connection->getAttribute('_action_response');
        $this->assertInstanceOf(ActionResponse::class, $response);
        $this->assertEquals(0, $response->status);
    }
}
