<?php

declare(strict_types=1);

namespace Tests\Unit\NlSchedule;

use App\Support\NlSchedule\NextRunsCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class NextRunsCalculatorTest extends TestCase
{
    private NextRunsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(NextRunsCalculator::class);
    }

    public function test_returns_five_runs_by_default(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *', // Daily at 9am
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );
        $this->assertCount(5, $runs);
    }

    public function test_includes_local_and_utc_timestamps(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *',
            'America/New_York',
            null,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );

        $this->assertArrayHasKey('local', $runs[0]);
        $this->assertArrayHasKey('utc', $runs[0]);
    }

    public function test_filters_by_active_hours(): void
    {
        $activeHours = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]]; // weekdays
        $runs = $this->calculator->calculate(
            '0 9 * * *', // Daily at 9am (includes weekends)
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC') // Monday
        );

        // All returned runs should be on weekdays
        foreach ($runs as $run) {
            $dayOfWeek = CarbonImmutable::parse($run['utc'])->dayOfWeekIso;
            $this->assertContains($dayOfWeek, [1, 2, 3, 4, 5]);
        }
    }

    public function test_shows_true_dispatch_times(): void
    {
        $activeHours = ['start' => '10:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $runs = $this->calculator->calculate(
            '0 9 * * *', // 9am is before active hours start
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );

        // Since 9am is before the 10am start time, no runs should be returned
        // This is the "true dispatch times" behavior - only times that would actually execute
        $this->assertCount(0, $runs, 'Jobs scheduled at 9am should be filtered out when active hours start at 10am');
    }

    public function test_shows_runs_within_active_hours(): void
    {
        $activeHours = ['start' => '08:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $runs = $this->calculator->calculate(
            '0 9 * * *', // 9am is within active hours (8am-5pm)
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );

        // 9am is within 8am-5pm, so runs should be returned
        $this->assertCount(5, $runs);

        // All returned runs should be at 9am
        foreach ($runs as $run) {
            $hour = CarbonImmutable::parse($run['utc'])->hour;
            $this->assertEquals(9, $hour);
        }
    }

    // Additional tests

    public function test_returns_specified_count(): void
    {
        $runs = $this->calculator->calculate(
            '0 * * * *', // Hourly
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC'),
            10
        );
        $this->assertCount(10, $runs);
    }

    public function test_utc_timestamps_are_iso8601(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *',
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );

        foreach ($runs as $run) {
            // ISO-8601 format should parse without error
            $this->assertNotFalse(strtotime($run['utc']));
            $this->assertNotFalse(strtotime($run['local']));
        }
    }

    public function test_local_and_utc_differ_for_non_utc_timezone(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *', // 9am local
            'America/New_York',
            null,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC')
        );

        // Local should show 9am, UTC should show 14:00 (EST is UTC-5 in winter)
        $local = CarbonImmutable::parse($runs[0]['local']);
        $utc = CarbonImmutable::parse($runs[0]['utc']);

        $this->assertEquals(9, $local->hour);
        $this->assertEquals(14, $utc->hour); // 9am EST = 2pm UTC
    }

    public function test_filters_out_weekend_runs(): void
    {
        $activeHours = ['start' => '00:00', 'end' => '23:59', 'days' => [1, 2, 3, 4, 5]];
        $runs = $this->calculator->calculate(
            '0 12 * * *', // Daily at noon
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-19 00:00:00', 'UTC'), // Friday
            7
        );

        // Starting Friday, next 7 weekday runs should skip Saturday and Sunday
        $days = array_map(
            fn ($run) => CarbonImmutable::parse($run['utc'])->dayOfWeekIso,
            $runs
        );

        $this->assertNotContains(6, $days); // No Saturday
        $this->assertNotContains(7, $days); // No Sunday
    }

    public function test_handles_hourly_with_active_hours(): void
    {
        $activeHours = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $runs = $this->calculator->calculate(
            '0 * * * *', // Every hour
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 06:00:00', 'UTC'), // Monday 6am
            5
        );

        // All runs should be between 9am and 5pm
        foreach ($runs as $run) {
            $hour = CarbonImmutable::parse($run['utc'])->hour;
            $this->assertGreaterThanOrEqual(9, $hour);
            $this->assertLessThanOrEqual(17, $hour);
        }
    }

    public function test_null_active_hours_returns_all_cron_runs(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *', // Daily at 9am including weekends
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-19 00:00:00', 'UTC'), // Friday
            5
        );

        // Should include both weekday and weekend runs
        $days = array_map(
            fn ($run) => CarbonImmutable::parse($run['utc'])->dayOfWeekIso,
            $runs
        );

        // Friday (5), Saturday (6), Sunday (7), Monday (1), Tuesday (2)
        $this->assertEquals([5, 6, 7, 1, 2], $days);
    }

    public function test_handles_every_minute_cron(): void
    {
        $runs = $this->calculator->calculate(
            '* * * * *', // Every minute
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'),
            5
        );

        $this->assertCount(5, $runs);

        // Verify minutes increment by 1
        $minutes = array_map(
            fn ($run) => CarbonImmutable::parse($run['utc'])->minute,
            $runs
        );

        $this->assertEquals([1, 2, 3, 4, 5], $minutes);
    }

    public function test_handles_complex_cron_expression(): void
    {
        // Every 15 minutes, weekdays only
        $runs = $this->calculator->calculate(
            '*/15 * * * 1-5',
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'), // Monday
            4
        );

        $this->assertCount(4, $runs);

        // All should be weekdays
        foreach ($runs as $run) {
            $dayOfWeek = CarbonImmutable::parse($run['utc'])->dayOfWeekIso;
            $this->assertLessThanOrEqual(5, $dayOfWeek);
        }
    }

    public function test_skips_past_runs_correctly(): void
    {
        $runs = $this->calculator->calculate(
            '0 9 * * *', // Daily at 9am
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'), // Already past 9am
            3
        );

        // First run should be next day (Jan 16)
        $firstRun = CarbonImmutable::parse($runs[0]['utc']);
        $this->assertEquals(16, $firstRun->day);
    }

    public function test_handles_end_of_month_transition(): void
    {
        $runs = $this->calculator->calculate(
            '0 12 * * *', // Daily at noon
            'UTC',
            null,
            CarbonImmutable::parse('2024-01-30 00:00:00', 'UTC'),
            5
        );

        $dates = array_map(
            fn ($run) => CarbonImmutable::parse($run['utc'])->format('Y-m-d'),
            $runs
        );

        $this->assertEquals([
            '2024-01-30',
            '2024-01-31',
            '2024-02-01',
            '2024-02-02',
            '2024-02-03',
        ], $dates);
    }

    public function test_handles_leap_year(): void
    {
        $runs = $this->calculator->calculate(
            '0 12 * * *', // Daily at noon
            'UTC',
            null,
            CarbonImmutable::parse('2024-02-28 00:00:00', 'UTC'),
            3
        );

        $dates = array_map(
            fn ($run) => CarbonImmutable::parse($run['utc'])->format('Y-m-d'),
            $runs
        );

        // 2024 is a leap year, so Feb 29 exists
        $this->assertEquals([
            '2024-02-28',
            '2024-02-29',
            '2024-03-01',
        ], $dates);
    }

    public function test_max_iterations_prevents_infinite_loop(): void
    {
        // Active hours that are impossible to satisfy (no days allowed)
        // This should return fewer results than requested
        $activeHours = ['start' => '09:00', 'end' => '17:00', 'days' => []];
        $runs = $this->calculator->calculate(
            '0 12 * * *',
            'UTC',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC'),
            5
        );

        // Should return empty since no days match
        $this->assertCount(0, $runs);
    }

    public function test_respects_timezone_for_active_hours_filtering(): void
    {
        // Active hours 09:00-17:00 EST on weekdays
        // Cron runs at 14:00 UTC = 09:00 EST (just within window)
        $activeHours = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $runs = $this->calculator->calculate(
            '0 14 * * *', // 2pm UTC = 9am EST
            'America/New_York',
            $activeHours,
            CarbonImmutable::parse('2024-01-15 00:00:00', 'UTC'),
            5
        );

        // Should have results since 9am EST is within 9am-5pm window
        $this->assertCount(5, $runs);
    }
}
