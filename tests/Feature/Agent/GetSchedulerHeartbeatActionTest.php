<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Actions\Agent\GetSchedulerHeartbeatAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetSchedulerHeartbeatActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetSchedulerHeartbeatAction;
        $this->assertInstanceOf(GetSchedulerHeartbeatAction::class, $action);
    }

    public function test_execute_returns_null_when_no_heartbeats_exist(): void
    {
        $action = new GetSchedulerHeartbeatAction;

        $result = $action->execute();

        $this->assertNull($result);
    }
}
