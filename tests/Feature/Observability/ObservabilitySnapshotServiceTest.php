<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Services\Observability\ObservabilitySnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservabilitySnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ObservabilitySnapshotService::class);
        $this->assertInstanceOf(ObservabilitySnapshotService::class, $service);
    }

    public function test_snapshot_returns_array(): void
    {
        $service = app(ObservabilitySnapshotService::class);
        $result = $service->snapshot();
        $this->assertIsArray($result);
    }
}
