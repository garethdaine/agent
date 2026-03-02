<?php

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\User;
use App\Support\Org\OrgCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgCouncilServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrgCouncilService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgCouncilService::class);
    }

    public function test_creates_council_template(): void
    {
        $user = User::factory()->create();
        $agent1 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent2 = OrgAgentProfile::factory()->forUser($user)->create();

        $template = $this->service->create($user, [
            'name' => 'Architecture Review Council',
            'member_list' => [
                ['agent_id' => $agent1->id, 'perspective' => 'security'],
                ['agent_id' => $agent2->id, 'perspective' => 'scalability'],
            ],
            'synthesis_mode' => 'majority',
            'evidence_payload_schema' => ['type' => 'object'],
            'member_response_schema' => ['type' => 'object'],
        ]);

        $this->assertInstanceOf(OrgCouncilTemplate::class, $template);
        $this->assertFalse($template->use_model_synthesis);
    }

    public function test_deterministic_synthesis_by_default(): void
    {
        $user = User::factory()->create();
        $template = OrgCouncilTemplate::factory()->for($user)->create([
            'synthesis_mode' => 'majority',
            'use_model_synthesis' => false,
        ]);

        $responses = collect([
            ['decision' => 'approve'],
            ['decision' => 'approve'],
            ['decision' => 'reject'],
        ]);

        $result = $this->service->synthesize($template, $responses);

        $this->assertEquals('approve', $result->decision);
    }

    public function test_weighted_synthesis_respects_weights(): void
    {
        $user = User::factory()->create();
        $agent1 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent2 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent3 = OrgAgentProfile::factory()->forUser($user)->create();

        $template = OrgCouncilTemplate::factory()->for($user)->create([
            'synthesis_mode' => 'weighted',
            'use_model_synthesis' => false,
            'member_list' => [
                ['agent_id' => $agent1->id, 'perspective' => 'security', 'weight' => 3],
                ['agent_id' => $agent2->id, 'perspective' => 'velocity', 'weight' => 1],
                ['agent_id' => $agent3->id, 'perspective' => 'quality', 'weight' => 1],
            ],
        ]);

        // Agent1 (weight 3) rejects, agents 2 and 3 (weight 1 each) approve
        // Weighted: 3 reject vs 2 approve = reject wins
        $responses = collect([
            ['agent_id' => $agent1->id, 'decision' => 'reject'],
            ['agent_id' => $agent2->id, 'decision' => 'approve'],
            ['agent_id' => $agent3->id, 'decision' => 'approve'],
        ]);

        $result = $this->service->synthesize($template, $responses);

        $this->assertEquals('reject', $result->decision);
    }

    public function test_chair_decides_uses_chair_vote(): void
    {
        $user = User::factory()->create();
        $chair = OrgAgentProfile::factory()->forUser($user)->create();
        $member = OrgAgentProfile::factory()->forUser($user)->create();

        $template = OrgCouncilTemplate::factory()->for($user)->create([
            'synthesis_mode' => 'chair_decides',
            'use_model_synthesis' => false,
            'member_list' => [
                ['agent_id' => $chair->id, 'perspective' => 'lead', 'is_chair' => true],
                ['agent_id' => $member->id, 'perspective' => 'review'],
            ],
        ]);

        // Member approves, but chair rejects - chair decides
        $responses = collect([
            ['agent_id' => $chair->id, 'decision' => 'reject'],
            ['agent_id' => $member->id, 'decision' => 'approve'],
        ]);

        $result = $this->service->synthesize($template, $responses);

        $this->assertEquals('reject', $result->decision);
    }

    public function test_update_council_template(): void
    {
        $user = User::factory()->create();
        $template = OrgCouncilTemplate::factory()->for($user)->create([
            'name' => 'Original Name',
        ]);

        $updated = $this->service->update($template, [
            'name' => 'Updated Name',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
    }

    public function test_archive_council_template(): void
    {
        $user = User::factory()->create();
        $template = OrgCouncilTemplate::factory()->for($user)->create();

        $this->service->archive($template);

        $this->assertNotNull($template->fresh()->archived_at);
    }

    public function test_restore_archived_council_template(): void
    {
        $user = User::factory()->create();
        $template = OrgCouncilTemplate::factory()->for($user)->archived()->create();

        $this->service->restore($template);

        $this->assertNull($template->fresh()->archived_at);
    }

    public function test_synthesis_generates_conflict_report(): void
    {
        $user = User::factory()->create();
        $template = OrgCouncilTemplate::factory()->for($user)->create([
            'synthesis_mode' => 'majority',
            'use_model_synthesis' => false,
        ]);

        $responses = collect([
            ['agent_id' => 'a1', 'perspective' => 'security', 'decision' => 'reject', 'reasoning' => 'Security concern'],
            ['agent_id' => 'a2', 'perspective' => 'velocity', 'decision' => 'approve', 'reasoning' => 'Time pressure'],
        ]);

        $result = $this->service->synthesize($template, $responses);

        $this->assertNotEmpty($result->conflicts);
    }

    public function test_validates_member_agents_belong_to_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAgent = OrgAgentProfile::factory()->forUser($otherUser)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All member agents must belong to the user');

        $this->service->create($user, [
            'name' => 'Invalid Council',
            'member_list' => [
                ['agent_id' => $otherAgent->id, 'perspective' => 'test'],
            ],
            'synthesis_mode' => 'majority',
            'evidence_payload_schema' => ['type' => 'object'],
            'member_response_schema' => ['type' => 'object'],
        ]);
    }
}
