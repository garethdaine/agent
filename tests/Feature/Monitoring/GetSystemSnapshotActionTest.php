<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Actions\Monitoring\GetSystemSnapshotAction;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetSystemSnapshotActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetSystemSnapshotAction;
        $this->assertInstanceOf(GetSystemSnapshotAction::class, $action);
    }

    public function test_execute_returns_array(): void
    {
        $action = new GetSystemSnapshotAction;

        $buildStateService = $this->app->make(ActiveProjectionBuildStateService::class);
        $freshnessService = $this->app->make(ActiveBuildFreshnessService::class);

        $result = $action->execute($buildStateService, $freshnessService);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('activeProjectionBuildId', $result);
        $this->assertArrayHasKey('rebuildingBuildId', $result);
        $this->assertArrayHasKey('freshness', $result);
        $this->assertArrayHasKey('scheduler', $result);
        $this->assertArrayHasKey('signals', $result);
        $this->assertArrayHasKey('reasonState', $result);
    }
}
