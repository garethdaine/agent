<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\ActiveProjectionBuildStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveProjectionBuildStateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ActiveProjectionBuildStateService::class);
        $this->assertInstanceOf(ActiveProjectionBuildStateService::class, $service);
    }

    public function test_active_projection_build_id_returns_null_or_string(): void
    {
        $service = app(ActiveProjectionBuildStateService::class);
        $result = $service->activeProjectionBuildId();
        $this->assertTrue(is_null($result) || is_string($result));
    }
}
