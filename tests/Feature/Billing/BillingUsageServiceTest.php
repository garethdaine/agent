<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Services\Billing\BillingUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(BillingUsageService::class);
        $this->assertInstanceOf(BillingUsageService::class, $service);
    }

    public function test_report_run_completed_does_not_throw(): void
    {
        $service = app(BillingUsageService::class);
        $user = User::factory()->create();

        $service->reportRunCompleted($user);

        $this->assertTrue(true);
    }
}
