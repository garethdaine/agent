<?php

namespace Tests\Unit\Support\Org\Synthesis;

use App\Support\Org\Synthesis\MajoritySynthesisStrategy;
use Tests\TestCase;

class MajoritySynthesisStrategyTest extends TestCase
{
    private MajoritySynthesisStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new MajoritySynthesisStrategy;
    }

    public function test_returns_majority_decision(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve', 'reasoning' => 'Good'],
            ['agent_id' => 'a2', 'decision' => 'approve', 'reasoning' => 'Acceptable'],
            ['agent_id' => 'a3', 'decision' => 'reject', 'reasoning' => 'Risk'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertEquals('approve', $result->decision);
        $this->assertEquals(2, $result->votes_for);
        $this->assertEquals(1, $result->votes_against);
    }

    public function test_handles_tie_with_no_majority(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'],
            ['agent_id' => 'a2', 'decision' => 'reject'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }

    public function test_generates_conflict_log(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'perspective' => 'security', 'decision' => 'reject', 'reasoning' => 'Security risk'],
            ['agent_id' => 'a2', 'perspective' => 'velocity', 'decision' => 'approve', 'reasoning' => 'Fast delivery'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertNotEmpty($result->conflicts);
        $this->assertCount(2, $result->conflicts);
    }

    public function test_handles_empty_responses(): void
    {
        $responses = [];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertNull($result->decision);
        $this->assertEquals(0, $result->votes_for);
        $this->assertEquals(0, $result->votes_against);
    }

    public function test_handles_single_response(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve', 'reasoning' => 'Looks good'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertEquals('approve', $result->decision);
        $this->assertEquals(1, $result->votes_for);
        $this->assertEquals(0, $result->votes_against);
        $this->assertFalse($result->is_tie);
    }

    public function test_handles_unanimous_rejection(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'reject', 'reasoning' => 'Too risky'],
            ['agent_id' => 'a2', 'decision' => 'reject', 'reasoning' => 'Not ready'],
            ['agent_id' => 'a3', 'decision' => 'reject', 'reasoning' => 'Needs work'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        $this->assertEquals('reject', $result->decision);
        $this->assertEquals(3, $result->votes_for);
        $this->assertEquals(0, $result->votes_against);
    }

    public function test_missing_decision_field_is_ignored(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'],
            ['agent_id' => 'a2'],  // No decision
            ['agent_id' => 'a3', 'decision' => 'reject'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        // With only 2 votes (1 approve, 1 reject), this is a tie
        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }

    public function test_custom_decision_field(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'vote' => 'yes'],
            ['agent_id' => 'a2', 'vote' => 'yes'],
            ['agent_id' => 'a3', 'vote' => 'no'],
        ];

        $result = $this->strategy->synthesize($responses, 'vote');

        $this->assertEquals('yes', $result->decision);
        $this->assertEquals(2, $result->votes_for);
        $this->assertEquals(1, $result->votes_against);
    }

    public function test_three_way_split_with_no_majority(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'],
            ['agent_id' => 'a2', 'decision' => 'reject'],
            ['agent_id' => 'a3', 'decision' => 'defer'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision');

        // No single decision has majority
        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }
}
