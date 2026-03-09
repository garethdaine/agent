<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Services\Escalation\IncidentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(IncidentLifecycleService::class);
        $this->assertInstanceOf(IncidentLifecycleService::class, $service);
    }
}
