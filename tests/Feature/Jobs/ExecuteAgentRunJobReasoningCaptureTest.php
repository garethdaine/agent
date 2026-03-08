<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Support\Agent\ReasoningStepParser;
use Tests\TestCase;

class ExecuteAgentRunJobReasoningCaptureTest extends TestCase
{
    public function test_reasoning_step_events_tagged_correctly(): void
    {
        $parser = new ReasoningStepParser;

        $step = $parser->parse("### SITUATION\nThe codebase is...");
        $this->assertEquals('situation', $step);

        $step = $parser->parse("### TASK\nEnsure tests pass");
        $this->assertEquals('task', $step);
    }

    public function test_reasoning_summary_extracted_from_parser(): void
    {
        $parser = new ReasoningStepParser;
        $parser->parse("### SITUATION\nState");
        $parser->parse("### TASK\nGoal");
        $parser->parse("### ACTION\nSteps");
        $parser->parse("### RESULT\nVerify");

        $summary = $parser->getSummary();

        $this->assertTrue($summary['all_completed']);
        $this->assertArrayHasKey('situation', $summary['steps']);
        $this->assertTrue($summary['steps']['situation']['completed']);
    }
}
