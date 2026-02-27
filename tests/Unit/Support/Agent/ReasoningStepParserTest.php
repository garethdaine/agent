<?php

namespace Tests\Unit\Support\Agent;

use App\Support\Agent\ReasoningStepParser;
use Tests\TestCase;

class ReasoningStepParserTest extends TestCase
{
    private ReasoningStepParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ReasoningStepParser();
    }

    public function test_parses_situation_header(): void
    {
        $step = $this->parser->parse("### SITUATION\nThe codebase has...");
        $this->assertEquals('situation', $step);
    }

    public function test_parses_all_four_steps_in_sequence(): void
    {
        $this->assertEquals('situation', $this->parser->parse("### SITUATION\n"));
        $this->assertEquals('situation', $this->parser->parse("Current state is...\n"));
        $this->assertEquals('task', $this->parser->parse("### TASK\n"));
        $this->assertEquals('action', $this->parser->parse("### ACTION\n"));
        $this->assertEquals('result', $this->parser->parse("### RESULT\n"));
    }

    public function test_handles_missing_steps_gracefully(): void
    {
        $this->assertEquals('situation', $this->parser->parse("### SITUATION\n"));
        $this->assertEquals('result', $this->parser->parse("### RESULT\n")); // Skipped TASK/ACTION

        $summary = $this->parser->getSummary();
        $this->assertTrue($summary['steps']['situation']['completed']);
        $this->assertFalse($summary['steps']['task']['completed']);
    }

    public function test_handles_malformed_headers(): void
    {
        $step = $this->parser->parse("## SITUATION\n"); // Wrong heading level
        $this->assertNull($step);
    }

    public function test_summary_contains_truncated_content(): void
    {
        $this->parser->parse("### SITUATION\n");
        $this->parser->parse(str_repeat('A', 3000)); // Exceeds 2000 char limit

        $summary = $this->parser->getSummary();
        $this->assertLessThanOrEqual(2000, strlen($summary['steps']['situation']['content']));
    }

    public function test_reset_clears_state(): void
    {
        $this->parser->parse("### SITUATION\nTest");
        $this->parser->reset();

        $summary = $this->parser->getSummary();
        $this->assertFalse($summary['steps']['situation']['completed']);
    }
}
