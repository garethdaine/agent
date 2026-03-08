<?php

declare(strict_types=1);

namespace Tests\Unit\NlSchedule;

use App\Support\NlSchedule\ActiveHoursEvaluator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ActiveHoursEvaluatorTest extends TestCase
{
    private ActiveHoursEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ActiveHoursEvaluator;
    }

    public function test_null_config_returns_true(): void
    {
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 14:00:00', 'UTC'),
            null,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_within_time_window_returns_true(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        // Monday 10am
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'), // Monday
            $config,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_outside_time_window_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        // Monday 8am (before start)
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 08:00:00', 'UTC'),
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_wrong_day_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]]; // weekdays
        // Saturday 10am
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-20 10:00:00', 'UTC'), // Saturday
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_iso8601_day_indexing(): void
    {
        // Monday = 1 in ISO-8601
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1]];
        $monday = CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'); // Known Monday

        $result = $this->evaluator->isWithinActiveHours($monday, $config, 'UTC');
        $this->assertTrue($result);
    }

    public function test_respects_timezone(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        // 14:00 UTC = 09:00 EST (within window for EST)
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 14:00:00', 'UTC'),
            $config,
            'America/New_York'
        );
        $this->assertTrue($result);
    }

    // Additional edge case tests

    public function test_at_exact_start_time_returns_true(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 09:00:00', 'UTC'), // Monday
            $config,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_at_exact_end_time_returns_true(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 17:00:00', 'UTC'), // Monday
            $config,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_one_minute_after_end_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 17:01:00', 'UTC'), // Monday
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_one_minute_before_start_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 08:59:00', 'UTC'), // Monday
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_sunday_with_iso8601_day_7(): void
    {
        // Sunday = 7 in ISO-8601
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [7]];
        $sunday = CarbonImmutable::parse('2024-01-21 10:00:00', 'UTC'); // Known Sunday

        $result = $this->evaluator->isWithinActiveHours($sunday, $config, 'UTC');
        $this->assertTrue($result);
    }

    public function test_all_weekdays(): void
    {
        $config = ['start' => '00:00', 'end' => '23:59', 'days' => [1, 2, 3, 4, 5]];

        // Test each weekday
        $monday = CarbonImmutable::parse('2024-01-15 12:00:00', 'UTC');
        $tuesday = CarbonImmutable::parse('2024-01-16 12:00:00', 'UTC');
        $wednesday = CarbonImmutable::parse('2024-01-17 12:00:00', 'UTC');
        $thursday = CarbonImmutable::parse('2024-01-18 12:00:00', 'UTC');
        $friday = CarbonImmutable::parse('2024-01-19 12:00:00', 'UTC');

        $this->assertTrue($this->evaluator->isWithinActiveHours($monday, $config, 'UTC'));
        $this->assertTrue($this->evaluator->isWithinActiveHours($tuesday, $config, 'UTC'));
        $this->assertTrue($this->evaluator->isWithinActiveHours($wednesday, $config, 'UTC'));
        $this->assertTrue($this->evaluator->isWithinActiveHours($thursday, $config, 'UTC'));
        $this->assertTrue($this->evaluator->isWithinActiveHours($friday, $config, 'UTC'));
    }

    public function test_weekend_days(): void
    {
        $config = ['start' => '00:00', 'end' => '23:59', 'days' => [6, 7]]; // Saturday, Sunday ISO-8601

        $saturday = CarbonImmutable::parse('2024-01-20 12:00:00', 'UTC');
        $sunday = CarbonImmutable::parse('2024-01-21 12:00:00', 'UTC');
        $monday = CarbonImmutable::parse('2024-01-15 12:00:00', 'UTC');

        $this->assertTrue($this->evaluator->isWithinActiveHours($saturday, $config, 'UTC'));
        $this->assertTrue($this->evaluator->isWithinActiveHours($sunday, $config, 'UTC'));
        $this->assertFalse($this->evaluator->isWithinActiveHours($monday, $config, 'UTC'));
    }

    public function test_empty_days_array_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => []];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'),
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_missing_days_key_returns_false(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00'];
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 10:00:00', 'UTC'),
            $config,
            'UTC'
        );
        $this->assertFalse($result);
    }

    public function test_config_with_only_start_uses_2359_as_end(): void
    {
        $config = ['start' => '09:00', 'days' => [1, 2, 3, 4, 5]];
        // Monday 10pm - should be within if end defaults to 23:59
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 22:00:00', 'UTC'),
            $config,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_config_with_only_end_uses_0000_as_start(): void
    {
        $config = ['end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        // Monday 1am - should be within if start defaults to 00:00
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 01:00:00', 'UTC'),
            $config,
            'UTC'
        );
        $this->assertTrue($result);
    }

    public function test_get_skip_metadata_returns_expected_structure(): void
    {
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [1, 2, 3, 4, 5]];
        $timestamp = CarbonImmutable::parse('2024-01-15 08:00:00', 'UTC');
        $jobId = 'test-job-uuid';

        $metadata = $this->evaluator->getSkipMetadata($timestamp, $config, $jobId);

        $this->assertEquals('outside_active_hours', $metadata['skip_reason']);
        $this->assertEquals($jobId, $metadata['job_id']);
        $this->assertEquals($timestamp->toIso8601String(), $metadata['scheduled_time']);
        $this->assertEquals($config, $metadata['active_hours_config']);
    }

    public function test_timezone_conversion_near_midnight(): void
    {
        // 11pm UTC on Sunday = 6pm EST on Sunday (winter time)
        // With active hours 09:00-17:00, this should be outside for EST
        $config = ['start' => '09:00', 'end' => '17:00', 'days' => [7]]; // Sunday
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-21 23:00:00', 'UTC'),
            $config,
            'America/New_York'
        );
        $this->assertFalse($result); // 6pm EST is past 5pm end time
    }

    public function test_timezone_changes_day_of_week(): void
    {
        // 1am Monday UTC = 8pm Sunday EST (winter time)
        // If active hours are weekdays only, this should be outside for EST
        $config = ['start' => '00:00', 'end' => '23:59', 'days' => [1, 2, 3, 4, 5]]; // Weekdays only
        $result = $this->evaluator->isWithinActiveHours(
            CarbonImmutable::parse('2024-01-15 01:00:00', 'UTC'), // Monday 1am UTC
            $config,
            'America/New_York' // 8pm Sunday EST
        );
        // At 8pm Sunday EST, it's still Sunday (day 7), not a weekday
        $this->assertFalse($result);
    }
}
