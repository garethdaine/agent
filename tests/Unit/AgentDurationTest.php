<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Agent\Duration;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AgentDurationTest extends TestCase
{
    public function test_milliseconds_between_returns_int_and_non_negative(): void
    {
        $startedAt = CarbonImmutable::parse('2026-02-12T10:20:00.123456Z');
        $finishedAt = CarbonImmutable::parse('2026-02-12T10:20:49.605123Z');

        $duration = Duration::millisecondsBetween($startedAt, $finishedAt);

        $this->assertIsInt($duration);
        $this->assertGreaterThanOrEqual(0, $duration);
    }

    public function test_milliseconds_between_returns_zero_for_invalid_start(): void
    {
        $duration = Duration::millisecondsBetween('not-a-date', CarbonImmutable::now('UTC'));

        $this->assertSame(0, $duration);
    }
}
