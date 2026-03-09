<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\TerminalizationGapProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalizationGapProjectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(TerminalizationGapProjector::class);
        $this->assertInstanceOf(TerminalizationGapProjector::class, $service);
    }
}
