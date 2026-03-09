<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\IngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(IngestionService::class);
        $this->assertInstanceOf(IngestionService::class, $service);
    }
}
