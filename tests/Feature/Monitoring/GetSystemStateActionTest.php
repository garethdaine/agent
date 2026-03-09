<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Actions\Monitoring\GetSystemStateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetSystemStateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetSystemStateAction;
        $this->assertInstanceOf(GetSystemStateAction::class, $action);
    }

    public function test_execute_returns_array_with_expected_keys(): void
    {
        $action = new GetSystemStateAction;

        $result = $action->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('scheduler_healthy', $result);
        $this->assertArrayHasKey('queue_lag_seconds', $result);
        $this->assertArrayHasKey('rate_limited', $result);
        $this->assertArrayHasKey('active_runs', $result);
        $this->assertArrayHasKey('runtime_mode', $result);
    }
}
