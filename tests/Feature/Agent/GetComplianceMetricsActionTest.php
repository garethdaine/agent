<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Actions\Agent\GetComplianceMetricsAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetComplianceMetricsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetComplianceMetricsAction;
        $this->assertInstanceOf(GetComplianceMetricsAction::class, $action);
    }

    public function test_execute_returns_array(): void
    {
        $action = new GetComplianceMetricsAction;
        $user = User::factory()->create();

        $result = $action->execute($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_jobs', $result);
        $this->assertArrayHasKey('total_runs', $result);
        $this->assertArrayHasKey('success_count', $result);
        $this->assertArrayHasKey('fail_count', $result);
        $this->assertArrayHasKey('active_runs', $result);
    }
}
