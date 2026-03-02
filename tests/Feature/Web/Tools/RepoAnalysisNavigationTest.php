<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Tools;

use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RepoAnalysisNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('repo_analysis.enabled', true);
    }

    public function test_tools_index_shows_repo_analysis_entry_for_authorized_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tools.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tools/Index')
                ->where('repoAnalysis.available', true)
                ->where('repoAnalysis.indexRoute', route('tools.repo-analysis.index'))
            );
    }

    public function test_repo_analysis_routes_are_reachable_and_session_list_links_to_wizard(): void
    {
        $owner = User::factory()->create();
        $session = $this->createSession($owner, [
            'name' => 'Navigation Session',
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'phase' => 0,
        ]);

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tools/RepoAnalysis/Index')
                ->where('sessions.0.id', $session->id)
                ->where('sessions.0.wizard_url', route('tools.repo-analysis.wizard', $session->id))
            );

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tools/RepoAnalysis/Create')
            );

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.wizard', $session->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tools/RepoAnalysis/Wizard')
                ->where('sessionId', $session->id)
            );

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.settings', $session->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tools/RepoAnalysis/Settings')
                ->where('sessionId', $session->id)
            );
    }

    public function test_wizard_action_visibility_changes_by_role_and_state(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        config()->set('agent.roles.admin_user_ids', [$admin->id]);

        $paused = $this->createSession($owner, [
            'status' => SessionStateTransitionService::STATUS_PAUSED,
            'phase' => 3,
        ]);

        $failed = $this->createSession($owner, [
            'status' => SessionStateTransitionService::STATUS_FAILED,
            'phase' => 3,
        ]);

        $running = $this->createSession($owner, [
            'status' => SessionStateTransitionService::STATUS_EXECUTING,
            'phase' => 3,
        ]);

        $completed = $this->createSession($owner, [
            'status' => SessionStateTransitionService::STATUS_COMPLETED,
            'phase' => 6,
        ]);

        RepoAnalysisReport::query()->create([
            'repo_analysis_session_id' => $completed->id,
            'report_version' => '1.0.0',
            'report_hash' => hash('sha256', 'completed-report'),
            'status' => 'generated',
            'payload_json' => ['summary' => 'done'],
            'generated_at' => now('UTC'),
        ]);

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.wizard', $paused->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('actionVisibility.pause', false)
                ->where('actionVisibility.resume', true)
                ->where('actionVisibility.retry', false)
                ->where('actionVisibility.restart', true)
            );

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.wizard', $failed->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('actionVisibility.pause', false)
                ->where('actionVisibility.resume', false)
                ->where('actionVisibility.retry', true)
                ->where('actionVisibility.restart', true)
            );

        $this->actingAs($owner)
            ->get(route('tools.repo-analysis.wizard', $completed->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('actionVisibility.export', true)
            );

        $this->actingAs($admin)
            ->get(route('tools.repo-analysis.wizard', $running->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('actionVisibility.pause', true)
                ->where('viewer.is_admin_override', true)
            );
    }

    public function test_non_owner_session_access_is_blocked_with_actionable_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = $this->createSession($owner);

        $this->actingAs($other)
            ->get(route('tools.repo-analysis.wizard', $session->id))
            ->assertStatus(403)
            ->assertSeeText('You do not have access to this Repo Analysis session.');
    }

    private function createSession(User $owner, array $overrides = []): RepoAnalysisSession
    {
        return RepoAnalysisSession::query()->create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Repo Analysis Web Session',
            'project_directory' => base_path(),
            'analyzer_profile' => 'default',
            'phase' => 0,
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'metadata_json' => [],
        ], $overrides));
    }
}
