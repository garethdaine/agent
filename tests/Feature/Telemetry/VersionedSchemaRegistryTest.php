<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\VersionedSchemaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionedSchemaRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(VersionedSchemaRegistry::class);
        $this->assertInstanceOf(VersionedSchemaRegistry::class, $service);
    }
}
