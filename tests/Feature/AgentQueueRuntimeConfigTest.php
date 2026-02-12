<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgentQueueRuntimeConfigTest extends TestCase
{
    public function test_redis_retry_after_is_longer_than_agent_worker_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $agentWorkerTimeout = (int) data_get(config('horizon.defaults'), 'supervisor-1.timeout', 0);

        $this->assertTrue($retryAfter > $agentWorkerTimeout, sprintf(
            'Redis retry_after (%d) must be greater than Horizon agent timeout (%d) to prevent in-flight redelivery.',
            $retryAfter,
            $agentWorkerTimeout
        ));
    }
}

