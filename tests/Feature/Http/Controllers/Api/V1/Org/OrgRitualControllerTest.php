<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use Tests\TestCase;

class OrgRitualControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.org.enabled' => true]);
    }

    public function test_store_creates_ritual_template(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->for($user)->create(['role_slug' => 'dev']);

        $response = $this->actingAs($user)
            ->postJson('/agent/api/v1/org/rituals', [
                'name' => 'Daily Report',
                'cron_expression' => '0 9 * * *',
                'timezone' => 'UTC',
                'phase_graph' => [
                    ['id' => 'generate', 'name' => 'Generate Report', 'depends_on' => []],
                ],
                'phase_role_mappings' => ['generate' => 'dev'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Daily Report');
    }

    public function test_run_triggers_immediate_execution(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/org/rituals/{$template->id}/run");

        $response->assertStatus(201)
            ->assertJsonPath('data.state', 'queued');
    }

    public function test_pause_sets_template_paused(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create(['is_paused' => false]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/org/rituals/{$template->id}/pause");

        $response->assertStatus(200);
        $this->assertTrue($template->fresh()->is_paused);
    }

    public function test_index_returns_user_rituals(): void
    {
        $user = User::factory()->create();
        OrgRitualTemplate::factory()->count(3)->for($user)->create();
        OrgRitualTemplate::factory()->create(); // Another user's template

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/rituals');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_excludes_archived_by_default(): void
    {
        $user = User::factory()->create();
        OrgRitualTemplate::factory()->for($user)->create();
        OrgRitualTemplate::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/rituals');

        $response->assertJsonCount(1, 'data');
    }

    public function test_index_includes_archived_when_requested(): void
    {
        $user = User::factory()->create();
        OrgRitualTemplate::factory()->for($user)->create();
        OrgRitualTemplate::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)
            ->getJson('/agent/api/v1/org/rituals?include_archived=true');

        $response->assertJsonCount(2, 'data');
    }

    public function test_show_returns_template_details(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/org/rituals/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id);
    }

    public function test_update_modifies_template(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->putJson("/agent/api/v1/org/rituals/{$template->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_archives_template(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/agent/api/v1/org/rituals/{$template->id}");

        $response->assertStatus(200);
        $this->assertNotNull($template->fresh()->archived_at);
    }

    public function test_restore_unarchives_template(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->archived()->create();

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/org/rituals/{$template->id}/restore");

        $response->assertStatus(200);
        $this->assertNull($template->fresh()->archived_at);
    }

    public function test_resume_clears_paused_flag(): void
    {
        $user = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($user)->paused()->create();

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/org/rituals/{$template->id}/resume");

        $response->assertStatus(200);
        $this->assertFalse($template->fresh()->is_paused);
    }

    public function test_policy_denies_access_to_other_user_template(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $template = OrgRitualTemplate::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->getJson("/agent/api/v1/org/rituals/{$template->id}");

        $response->assertStatus(403);
    }
}
