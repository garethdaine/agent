<?php

namespace Tests\Unit\Support\Org\Synthesis;

use App\Support\Org\Synthesis\ChairDecidesSynthesisStrategy;
use Tests\TestCase;

class ChairDecidesSynthesisStrategyTest extends TestCase
{
    private ChairDecidesSynthesisStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ChairDecidesSynthesisStrategy;
    }

    public function test_returns_chair_decision(): void
    {
        $memberList = [
            ['agent_id' => 'chair', 'is_chair' => true],
            ['agent_id' => 'member1'],
            ['agent_id' => 'member2'],
        ];

        $responses = [
            ['agent_id' => 'chair', 'decision' => 'reject'],
            ['agent_id' => 'member1', 'decision' => 'approve'],
            ['agent_id' => 'member2', 'decision' => 'approve'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertEquals('reject', $result->decision);
        $this->assertEquals(1, $result->votes_for);    // Only chair voted reject
        $this->assertEquals(2, $result->votes_against); // Two voted approve
    }

    public function test_generates_conflicts_when_members_disagree_with_chair(): void
    {
        $memberList = [
            ['agent_id' => 'chair', 'is_chair' => true],
            ['agent_id' => 'member1'],
        ];

        $responses = [
            ['agent_id' => 'chair', 'decision' => 'reject', 'perspective' => 'lead'],
            ['agent_id' => 'member1', 'decision' => 'approve', 'perspective' => 'dev', 'reasoning' => 'Looks good'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertCount(1, $result->conflicts);
        $this->assertEquals('member1', $result->conflicts[0]['agent_id']);
        $this->assertEquals('approve', $result->conflicts[0]['decision']);
        $this->assertEquals('chair', $result->conflicts[0]['conflict_with']);
    }

    public function test_returns_null_when_chair_not_found(): void
    {
        $memberList = [
            ['agent_id' => 'member1'],  // No chair designated
            ['agent_id' => 'member2'],
        ];

        $responses = [
            ['agent_id' => 'member1', 'decision' => 'approve'],
            ['agent_id' => 'member2', 'decision' => 'reject'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }

    public function test_returns_null_when_chair_has_no_response(): void
    {
        $memberList = [
            ['agent_id' => 'chair', 'is_chair' => true],
            ['agent_id' => 'member1'],
        ];

        $responses = [
            // Chair didn't respond
            ['agent_id' => 'member1', 'decision' => 'approve'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertNull($result->decision);
        $this->assertTrue($result->is_tie);
    }

    public function test_handles_empty_responses(): void
    {
        $responses = [];

        $result = $this->strategy->synthesize($responses, 'decision', []);

        $this->assertNull($result->decision);
        $this->assertEquals(0, $result->votes_for);
        $this->assertEquals(0, $result->votes_against);
    }

    public function test_vote_breakdown_marks_chair(): void
    {
        $memberList = [
            ['agent_id' => 'chair', 'is_chair' => true],
            ['agent_id' => 'member1'],
        ];

        $responses = [
            ['agent_id' => 'chair', 'decision' => 'approve'],
            ['agent_id' => 'member1', 'decision' => 'approve'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $chairVote = collect($result->vote_breakdown)->firstWhere('agent_id', 'chair');
        $memberVote = collect($result->vote_breakdown)->firstWhere('agent_id', 'member1');

        $this->assertTrue($chairVote['is_chair']);
        $this->assertFalse($memberVote['is_chair']);
    }

    public function test_no_conflicts_when_all_agree(): void
    {
        $memberList = [
            ['agent_id' => 'chair', 'is_chair' => true],
            ['agent_id' => 'member1'],
            ['agent_id' => 'member2'],
        ];

        $responses = [
            ['agent_id' => 'chair', 'decision' => 'approve'],
            ['agent_id' => 'member1', 'decision' => 'approve'],
            ['agent_id' => 'member2', 'decision' => 'approve'],
        ];

        $result = $this->strategy->synthesize($responses, 'decision', $memberList);

        $this->assertEquals('approve', $result->decision);
        $this->assertEmpty($result->conflicts);
    }
}
