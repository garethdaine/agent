<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Pipeline;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\Exceptions\RateLimitExceededException;
use App\Support\Connectors\Pipeline\ConnectorRateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ConnectorRateLimiterTest extends TestCase
{
    private ConnectorRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new ConnectorRateLimiter;
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_allows_request_within_limit(): void
    {
        $connector = new AgentConnector;
        $connector->rate_limits = ['requests_per_minute' => 60];

        $connection = new AgentConnectorConnection;
        $connection->id = 'test-conn-rate-1';

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        $called = false;
        $this->limiter->handle($request, function () use (&$called) {
            $called = true;

            return 'passed';
        });

        $this->assertTrue($called);
    }

    public function test_blocks_request_at_limit(): void
    {
        $connector = new AgentConnector;
        $connector->rate_limits = ['requests_per_minute' => 2];

        $connection = new AgentConnectorConnection;
        $connection->id = 'test-conn-rate-2';

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        // Exhaust the limit
        $this->limiter->handle($request, fn () => null);
        $this->limiter->handle($request, fn () => null);

        $this->expectException(RateLimitExceededException::class);
        $this->limiter->handle($request, fn () => null);
    }

    public function test_tokens_replenish_over_time(): void
    {
        $connector = new AgentConnector;
        $connector->rate_limits = ['requests_per_minute' => 1];

        $connection = new AgentConnectorConnection;
        $connection->id = 'test-conn-rate-3';

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        // Use the one allowed request
        $this->limiter->handle($request, fn () => null);

        // Clear the minute key to simulate time passing
        Redis::del('connector_rate:test-conn-rate-3:minute');

        // Should now allow again
        $called = false;
        $this->limiter->handle($request, function () use (&$called) {
            $called = true;

            return 'passed';
        });

        $this->assertTrue($called);
    }

    public function test_respects_concurrent_limit(): void
    {
        $connector = new AgentConnector;
        $connector->rate_limits = ['requests_per_minute' => 100, 'concurrent_limit' => 1];

        $connection = new AgentConnectorConnection;
        $connection->id = 'test-conn-rate-4';

        $request = new ActionRequest(
            connector: $connector,
            action: 'list_contacts',
            parameters: [],
            connection: $connection,
            team: new Team,
        );

        // Simulate a concurrent request in progress
        Redis::set('connector_rate:test-conn-rate-4:concurrent', 1);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Concurrent');

        $this->limiter->handle($request, fn () => null);
    }
}
