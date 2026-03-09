<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Services\Agent\ConfigSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigSchemaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ConfigSchemaService::class);
        $this->assertInstanceOf(ConfigSchemaService::class, $service);
    }

    public function test_get_schema_returns_array_with_expected_keys(): void
    {
        $service = app(ConfigSchemaService::class);
        $schema = $service->getSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('runtime', $schema);
    }
}
