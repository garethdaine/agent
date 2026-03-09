<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\ProjectionBuildManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionBuildManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ProjectionBuildManager::class);
        $this->assertInstanceOf(ProjectionBuildManager::class, $service);
    }
}
