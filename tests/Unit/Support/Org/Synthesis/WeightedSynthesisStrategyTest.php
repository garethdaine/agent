<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org\Synthesis;

use App\Support\Org\Synthesis\WeightedSynthesisStrategy;
use Tests\TestCase;

class WeightedSynthesisStrategyTest extends TestCase
{
    private WeightedSynthesisStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new WeightedSynthesisStrategy;
    }

    public function test_returns_decision_with_highest_weight(): void
    {
        $memberList = [
            ['agent_id' => 'a1', 'weight' => 3],
            ['agent_id' => 'a2', 'weight' => 1],
            ['agent_id' => 'a3', 'weight' => 1],
        ];

        $responses = [
            ['agent_id' => 'a1', 'decision' => 'reject'],  // weight 3
            ['agent_id' => 'a2', 'decision' => 'approve'], // weight 1
            ['agent_id' => 'a3', 'decision' => 'approve'], // weight 1
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertEquals('reject', $result->decision);
        $this->assertEquals(3.0, $result->weighted_score_for);
        $this->assertEquals(2.0, $result->weighted_score_against);
    }

    public function test_handles_weighted_tie(): void
    {
        $memberList = [
            ['agent_id' => 'a1', 'weight' => 2],
            ['agent_id' => 'a2', 'weight' => 2],
        ];

        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'],
            ['agent_id' => 'a2', 'decision' => 'reject'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }

    public function test_uses_default_weight_when_not_specified(): void
    {
        $memberList = [
            ['agent_id' => 'a1', 'weight' => 5],
            ['agent_id' => 'a2'], // No weight specified
        ];

        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'], // weight 5
            ['agent_id' => 'a2', 'decision' => 'reject'],  // default weight 1
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertEquals('approve', $result->decision);
        $this->assertEquals(5.0, $result->weighted_score_for);
        $this->assertEquals(1.0, $result->weighted_score_against);
    }

    public function test_handles_empty_responses(): void
    {
        $responses = [];

        $result = $this->strategy->synthesize($responses, 'decision', []);

        $this->assertNull($result->decision);
        $this->assertEquals(0, $result->votes_for);
        $this->assertEquals(0, $result->votes_against);
    }

    public function test_generates_conflict_log(): void
    {
        $responses = [
            ['agent_id' => 'a1', 'perspective' => 'security', 'decision' => 'reject', 'reasoning' => 'Too risky'],
            ['agent_id' => 'a2', 'perspective' => 'velocity', 'decision' => 'approve', 'reasoning' => 'Ship it'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', []);

        $this->assertNotEmpty($result->conflicts);
        $this->assertCount(2, $result->conflicts);
    }

    public function test_vote_breakdown_includes_weights(): void
    {
        $memberList = [
            ['agent_id' => 'a1', 'weight' => 3],
        ];

        $responses = [
            ['agent_id' => 'a1', 'decision' => 'approve'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertCount(1, $result->vote_breakdown);
        $this->assertEquals(3.0, $result->vote_breakdown[0]['weight']);
    }
}
