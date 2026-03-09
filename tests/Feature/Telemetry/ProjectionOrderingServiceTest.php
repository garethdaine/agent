<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\ProjectionOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionOrderingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ProjectionOrderingService::class);
        $this->assertInstanceOf(ProjectionOrderingService::class, $service);
    }

    public function test_order_for_projection_with_empty_array_returns_array(): void
    {
        $service = app(ProjectionOrderingService::class);
        $result = $service->orderForProjection([]);
        $this->assertIsArray($result);
    }
}
