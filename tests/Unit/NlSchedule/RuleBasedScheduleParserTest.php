<?php

namespace Tests\Unit\NlSchedule;

use App\Support\NlSchedule\RuleBasedScheduleParser;
use App\Support\NlSchedule\ParseResult;
use Tests\TestCase;

class RuleBasedScheduleParserTest extends TestCase
{
    private RuleBasedScheduleParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RuleBasedScheduleParser();
    }

    // High-confidence patterns
    public function test_every_x_minutes(): void
    {
        $result = $this->parser->parse('every 15 minutes', 'UTC');
        $this->assertEquals('*/15 * * * *', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        $this->assertFalse($result->ambiguous);
        $this->assertEquals('rule_based', $result->parserPath);
    }

    public function test_every_x_hours(): void
    {
        $result = $this->parser->parse('every 2 hours', 'UTC');
        $this->assertEquals('0 */2 * * *', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    public function test_daily_at_time_with_am_pm(): void
    {
        $result = $this->parser->parse('daily at 9am', 'America/New_York');
        $this->assertEquals('0 9 * * *', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        $this->assertEquals('America/New_York', $result->timezone);
    }

    public function test_weekdays_at_time(): void
    {
        $result = $this->parser->parse('weekdays at 10:30am', 'UTC');
        $this->assertEquals('30 10 * * 1-5', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    public function test_weekends_at_time(): void
    {
        $result = $this->parser->parse('weekends at 2pm', 'UTC');
        $this->assertEquals('0 14 * * 6,0', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    public function test_specific_day_at_time(): void
    {
        $result = $this->parser->parse('every Monday at 9am', 'UTC');
        $this->assertEquals('0 9 * * 1', $result->cronExpression);
    }

    public function test_hourly(): void
    {
        $result = $this->parser->parse('hourly', 'UTC');
        $this->assertEquals('0 * * * *', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    // Ambiguous patterns requiring confirmation
    public function test_twice_a_day_is_ambiguous(): void
    {
        $result = $this->parser->parse('twice a day', 'UTC');
        $this->assertTrue($result->ambiguous);
        $this->assertLessThan(0.75, $result->confidence);
        // Should suggest 9am and 5pm
        $this->assertStringContainsString('9', $result->explanation);
    }

    public function test_bare_time_without_am_pm_is_ambiguous(): void
    {
        $result = $this->parser->parse('daily at 5', 'UTC');
        $this->assertTrue($result->ambiguous);
        $this->assertLessThan(0.75, $result->confidence);
    }

    // Active hours patterns
    public function test_every_x_hours_starting_at_time_sets_active_hours(): void
    {
        $result = $this->parser->parse('every 2 hours starting at 9am', 'UTC');
        $this->assertNotNull($result->activeHours);
        $this->assertEquals('09:00', $result->activeHours['start']);
    }

    public function test_between_time_and_time_sets_active_hours(): void
    {
        $result = $this->parser->parse('every day between 9am and 5pm', 'UTC');
        $this->assertNotNull($result->activeHours);
        $this->assertEquals('09:00', $result->activeHours['start']);
        $this->assertEquals('17:00', $result->activeHours['end']);
    }

    // ISO-8601 day indexing
    public function test_day_indexing_is_iso8601(): void
    {
        $result = $this->parser->parse('every Monday at 9am', 'UTC');
        // ISO-8601: Monday = 1
        $this->assertStringContainsString('* 1', $result->cronExpression);
    }

    // Validation
    public function test_rejects_sub_minute_intervals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('every 30 seconds', 'UTC');
    }

    // ParseResult serialization
    public function test_parse_result_serializes_to_json(): void
    {
        $result = $this->parser->parse('daily at 9am', 'UTC');
        $json = $result->toArray();

        $this->assertArrayHasKey('cron_expression', $json);
        $this->assertArrayHasKey('timezone', $json);
        $this->assertArrayHasKey('explanation', $json);
        $this->assertArrayHasKey('confidence', $json);
        $this->assertArrayHasKey('parser_path', $json);
        $this->assertArrayHasKey('ambiguous', $json);
        $this->assertArrayHasKey('next_runs', $json);
    }

    // Additional edge case tests
    public function test_every_1_minute(): void
    {
        $result = $this->parser->parse('every 1 minute', 'UTC');
        $this->assertEquals('*/1 * * * *', $result->cronExpression);
        $this->assertGreaterThanOrEqual(0.75, $result->confidence);
    }

    public function test_daily_at_noon(): void
    {
        $result = $this->parser->parse('daily at 12pm', 'UTC');
        $this->assertEquals('0 12 * * *', $result->cronExpression);
        $this->assertFalse($result->ambiguous);
    }

    public function test_daily_at_midnight(): void
    {
        $result = $this->parser->parse('daily at 12am', 'UTC');
        $this->assertEquals('0 0 * * *', $result->cronExpression);
        $this->assertFalse($result->ambiguous);
    }

    public function test_specific_day_sunday(): void
    {
        $result = $this->parser->parse('every Sunday at 10am', 'UTC');
        // Sunday = 0 in cron
        $this->assertEquals('0 10 * * 0', $result->cronExpression);
    }

    public function test_specific_day_friday(): void
    {
        $result = $this->parser->parse('every Friday at 5pm', 'UTC');
        // Friday = 5 in cron
        $this->assertEquals('0 17 * * 5', $result->cronExpression);
    }

    public function test_daily_with_minutes(): void
    {
        $result = $this->parser->parse('daily at 9:30am', 'UTC');
        $this->assertEquals('30 9 * * *', $result->cronExpression);
    }

    public function test_next_runs_are_populated(): void
    {
        $result = $this->parser->parse('hourly', 'UTC');
        $this->assertCount(5, $result->nextRuns);
        $this->assertArrayHasKey('local', $result->nextRuns[0]);
        $this->assertArrayHasKey('utc', $result->nextRuns[0]);
    }

    public function test_unknown_pattern_returns_low_confidence(): void
    {
        $result = $this->parser->parse('every blue moon', 'UTC');
        $this->assertLessThan(0.5, $result->confidence);
        $this->assertTrue($result->ambiguous);
    }

    public function test_parse_result_json_serializable(): void
    {
        $result = $this->parser->parse('hourly', 'UTC');
        $json = json_encode($result);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('0 * * * *', $decoded['cron_expression']);
    }

    public function test_parse_result_confidence_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');
        new ParseResult(
            cronExpression: '0 * * * *',
            timezone: 'UTC',
            explanation: 'test',
            activeHours: null,
            nextRuns: [],
            confidence: 1.5, // Invalid
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    public function test_parse_result_parser_path_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parser path');
        new ParseResult(
            cronExpression: '0 * * * *',
            timezone: 'UTC',
            explanation: 'test',
            activeHours: null,
            nextRuns: [],
            confidence: 0.75,
            parserPath: 'invalid_path', // Invalid
            ambiguous: false,
        );
    }

    public function test_is_high_confidence_method(): void
    {
        $highConfidence = $this->parser->parse('hourly', 'UTC');
        $this->assertTrue($highConfidence->isHighConfidence());

        $lowConfidence = $this->parser->parse('twice a day', 'UTC');
        $this->assertFalse($lowConfidence->isHighConfidence());
    }
}
