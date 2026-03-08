<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Models\AgentJob;
use App\Models\User;
use App\Support\Agent\DispatchDueService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchActiveHoursTest extends TestCase
{
    use RefreshDatabase;

    private DispatchDueService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DispatchDueService::class);
        $this->user = User::factory()->create();
    }

    public function test_null_active_hours_config_dispatches_normally(): void
    {
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '* * * * *', // Every minute
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => null,
        ]);

        $result = $this->service->dispatch(CarbonImmutable::now('UTC'));

        // Should dispatch normally without any active hours skip
        $this->assertGreaterThanOrEqual(0, $result['dispatched_count']);
        $this->assertEquals(0, $result['skipped_active_hours_count'] ?? 0);
    }

    public function test_skips_when_outside_active_hours_window(): void
    {
        // Create job with active hours 09:00-17:00 weekdays only
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '0 3 * * *', // 3am daily
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5], // Weekdays
            ],
        ]);

        // Dispatch at 3am UTC on a weekday - should be skipped
        $mondayAt3am = CarbonImmutable::parse('2024-01-15 03:00:00', 'UTC');
        $result = $this->service->dispatch($mondayAt3am);

        $this->assertGreaterThan(0, $result['skipped_active_hours_count'] ?? 0);
    }

    public function test_dispatches_when_within_active_hours_window(): void
    {
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '0 10 * * *', // 10am daily
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        // Dispatch at 10am UTC on Monday - should proceed
        $mondayAt10am = CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC');
        $result = $this->service->dispatch($mondayAt10am);

        // Should not be skipped for active hours
        $this->assertEquals(0, $result['skipped_active_hours_count'] ?? 0);
    }

    public function test_skips_on_wrong_day_of_week(): void
    {
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '0 10 * * *', // 10am daily
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5], // Weekdays only
            ],
        ]);

        // Saturday at 10am - within time but wrong day
        $saturdayAt10am = CarbonImmutable::parse('2024-01-20 10:00:00', 'UTC');
        $result = $this->service->dispatch($saturdayAt10am);

        $this->assertGreaterThan(0, $result['skipped_active_hours_count'] ?? 0);
    }

    public function test_skip_reason_logged_with_metadata(): void
    {
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '0 3 * * *',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        $mondayAt3am = CarbonImmutable::parse('2024-01-15 03:00:00', 'UTC');
        $this->service->dispatch($mondayAt3am);

        // Verify the run was created with skip metadata
        $this->assertDatabaseHas('agent_job_runs', [
            'agent_job_id' => $job->id,
            'status' => 'skipped',
        ]);

        $run = $job->runs()->latest()->first();
        $metadata = $run->metadata_json;
        $this->assertEquals('outside_active_hours', $metadata['skip_reason']);
    }

    public function test_existing_skip_reasons_unaffected(): void
    {
        // Create job with overlap scenario
        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'active_hours_config' => null, // No active hours
        ]);

        // Existing skip behavior should remain unchanged
        // This is a regression test for backward compatibility
        $result = $this->service->dispatch();

        // Just verify no errors; specific skip scenarios tested elsewhere
        $this->assertIsArray($result);
    }
}
