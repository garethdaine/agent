<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgCouncilControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.org.enabled' => true]);
    }

    public function test_index_returns_user_councils(): void
    {
        $user = User::factory()->create();
        OrgCouncilTemplate::factory()->count(3)->for($user)->create();
        OrgCouncilTemplate::factory()->create(); // Another user's council

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/councils');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_excludes_archived_by_default(): void
    {
        $user = User::factory()->create();
        OrgCouncilTemplate::factory()->for($user)->create();
        OrgCouncilTemplate::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/councils');

        $response->assertJsonCount(1, 'data');
    }

    public function test_index_includes_archived_when_requested(): void
    {
        $user = User::factory()->create();
        OrgCouncilTemplate::factory()->for($user)->create();
        OrgCouncilTemplate::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/councils?include_archived=true');

        $response->assertJsonCount(2, 'data');
    }

    public function test_store_creates_council(): void
    {
        $user = User::factory()->create();
        $agent1 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent2 = OrgAgentProfile::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/councils', [
                'name' => 'Architecture Review Council',
                'description' => 'Reviews architecture decisions',
                'member_list' => [
                    ['agent_id' => $agent1->id, 'perspective' => 'security'],
                    ['agent_id' => $agent2->id, 'perspective' => 'scalability'],
                ],
                'synthesis_mode' => 'majority',
                'evidence_payload_schema' => ['type' => 'object'],
                'member_response_schema' => ['type' => 'object'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Architecture Review Council')
            ->assertJsonPath('data.synthesis_mode', 'majority')
            ->assertJsonPath('data.use_model_synthesis', false);
    }

    public function test_store_validates_synthesis_mode(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/councils', [
                'name' => 'Test Council',
                'member_list' => [
                    ['agent_id' => $agent->id, 'perspective' => 'test'],
                ],
                'synthesis_mode' => 'invalid_mode',
            ]);

        $response->assertStatus(422);
        $this->assertTrue(
            isset($response->json()['errors']['synthesis_mode']) ||
            str_contains(json_encode($response->json()), 'synthesis_mode'),
            'Expected validation error for synthesis_mode'
        );
    }

    public function test_store_validates_chair_decides_has_chair(): void
    {
        $user = User::factory()->create();
        $agent1 = OrgAgentProfile::factory()->forUser($user)->create();
        $agent2 = OrgAgentProfile::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/councils', [
                'name' => 'Test Council',
                'member_list' => [
                    ['agent_id' => $agent1->id, 'perspective' => 'test'],
                    ['agent_id' => $agent2->id, 'perspective' => 'test2'],
                ],
                'synthesis_mode' => 'chair_decides',
            ]);

        $response->assertStatus(422);
        $this->assertTrue(
            isset($response->json()['errors']['member_list']) ||
            str_contains(json_encode($response->json()), 'chair'),
            'Expected validation error about chair member'
        );
    }

    public function test_store_validates_weighted_has_weights(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/councils', [
                'name' => 'Test Council',
                'member_list' => [
                    ['agent_id' => $agent->id, 'perspective' => 'test'],
                ],
                'synthesis_mode' => 'weighted',
            ]);

        $response->assertStatus(422);
        $this->assertTrue(
            isset($response->json()['errors']['member_list']) ||
            str_contains(json_encode($response->json()), 'weight'),
            'Expected validation error about weights'
        );
    }

    public function test_store_rejects_other_users_agents(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAgent = OrgAgentProfile::factory()->forUser($otherUser)->create();

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/councils', [
                'name' => 'Test Council',
                'member_list' => [
                    ['agent_id' => $otherAgent->id, 'perspective' => 'test'],
                ],
                'synthesis_mode' => 'majority',
            ]);

        $response->assertStatus(422);
    }

    public function test_show_returns_council(): void
    {
        $user = User::factory()->create();
        $council = OrgCouncilTemplate::factory()->for($user)->create([
            'name' => 'Test Council',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/org/councils/{$council->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test Council');
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/councils/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_update_modifies_council(): void
    {
        $user = User::factory()->create();
        $council = OrgCouncilTemplate::factory()->for($user)->create([
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/agent/api/v1/org/councils/{$council->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertEquals('Updated Name', $council->fresh()->name);
    }

    public function test_destroy_archives_council(): void
    {
        $user = User::factory()->create();
        $council = OrgCouncilTemplate::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/agent/api/v1/org/councils/{$council->id}");

        $response->assertStatus(200);
        $this->assertNotNull($council->fresh()->archived_at);
    }

    public function test_policy_denies_access_to_other_user_council(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $council = OrgCouncilTemplate::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/org/councils/{$council->id}");

        $response->assertStatus(403);
    }

    public function test_returns_404_when_feature_disabled(): void
    {
        config(['agent.org.enabled' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/councils');

        $response->assertStatus(404);
    }
}
