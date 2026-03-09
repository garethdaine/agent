<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Actions\Agent\GetDashboardMetricsAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDashboardMetricsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetDashboardMetricsAction;
        $this->assertInstanceOf(GetDashboardMetricsAction::class, $action);
    }

    public function test_execute_returns_array_with_expected_keys(): void
    {
        $action = new GetDashboardMetricsAction;
        $user = User::factory()->create();

        $result = $action->execute($user, 24);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('runs_today', $result);
        $this->assertArrayHasKey('success_rate_percent', $result);
        $this->assertArrayHasKey('average_duration_ms', $result);
        $this->assertArrayHasKey('backlog_count', $result);
        $this->assertArrayHasKey('oldest_queued_age_seconds', $result);
        $this->assertArrayHasKey('window_terminal_total', $result);
        $this->assertArrayHasKey('window_succeeded_total', $result);
    }
}
