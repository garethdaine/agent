<?php

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Services\Delegation\PermissionAttenuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionAttenuationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(PermissionAttenuationService::class);
        $this->assertInstanceOf(PermissionAttenuationService::class, $service);
    }

    public function test_attenuate_with_empty_arrays_returns_array(): void
    {
        $service = app(PermissionAttenuationService::class);
        $result = $service->attenuate([], []);

        $this->assertIsArray($result);
    }
}
