<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class NlParseConfigTest extends TestCase
{
    public function test_nl_parse_config_has_required_keys(): void
    {
        $this->assertEquals(0.75, config('agent.nl_parse.confidence_threshold'));
        $this->assertEquals(30, config('agent.nl_parse.llm_timeout_seconds'));
        $this->assertEquals(60, config('agent.nl_parse.idempotency_window_seconds'));
        $this->assertEquals(10, config('agent.nl_parse.rate_limit_per_minute'));
        $this->assertEquals(60, config('agent.nl_parse.rate_limit_per_hour'));
        $this->assertEquals(200, config('agent.nl_parse.max_input_length'));
        $this->assertEquals(1, config('agent.nl_parse.min_interval_minutes'));
        $this->assertEquals(90, config('agent.nl_parse.retention_days'));
    }
}
