<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Services\Escalation\DailyAlertSuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAlertSuppressionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(DailyAlertSuppressionService::class);
        $this->assertInstanceOf(DailyAlertSuppressionService::class, $service);
    }
}
