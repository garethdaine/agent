<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['agent.interrogation.max_active_sessions' => 50]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_create_session_with_git_settings_stores_in_metadata(): void
    {
        $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Git Test Session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test git settings.',
            'git' => [
                'commit_enabled' => true,
                'conventional_commits' => true,
                'branching_enabled' => true,
                'branch_prefix' => 'feat/',
            ],
        ])->assertStatus(202);

        $sessionId = $response->json('data.id');
        $session = InterrogationSession::find($sessionId);

        $gitSettings = $session->gitSettings();
        $this->assertTrue($gitSettings['commit_enabled']);
        $this->assertTrue($gitSettings['conventional_commits']);
        $this->assertTrue($gitSettings['branching_enabled']);
        $this->assertSame('feat/', $gitSettings['branch_prefix']);
        $this->assertNull($gitSettings['target_branch']);
        $this->assertFalse($gitSettings['worktree_enabled']);

        // Also returned in response
        $this->assertTrue($response->json('data.git_settings.commit_enabled'));
        $this->assertTrue($response->json('data.git_settings.conventional_commits'));
    }

    public function test_create_session_without_git_settings_has_no_git_key(): void
    {
        $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'No Git Session',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test no git.',
        ])->assertStatus(202);

        $sessionId = $response->json('data.id');
        $session = InterrogationSession::find($sessionId);

        // Git key should not be present in metadata
        $metadata = $session->metadata_json;
        $this->assertArrayNotHasKey('git', $metadata);

        // But gitSettings() accessor returns safe defaults
        $gitSettings = $session->gitSettings();
        $this->assertFalse($gitSettings['commit_enabled']);
        $this->assertFalse($gitSettings['conventional_commits']);
        $this->assertFalse($gitSettings['worktree_enabled']);
        $this->assertFalse($gitSettings['branching_enabled']);
        $this->assertNull($gitSettings['branch_prefix']);
        $this->assertNull($gitSettings['target_branch']);
    }

    public function test_update_session_git_settings_merges_partial_update(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->create();

        $this->patchJson("/agent/api/v1/interrogation/sessions/{$session->id}", [
            'git' => [
                'conventional_commits' => true,
                'branching_enabled' => true,
            ],
        ])->assertOk();

        $session->refresh();
        $gitSettings = $session->gitSettings();

        // Original setting preserved
        $this->assertTrue($gitSettings['commit_enabled']);
        // New settings applied
        $this->assertTrue($gitSettings['conventional_commits']);
        $this->assertTrue($gitSettings['branching_enabled']);
        // Untouched settings stay default
        $this->assertFalse($gitSettings['worktree_enabled']);
    }

    public function test_git_settings_returned_in_session_show_response(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings([
                'commit_enabled' => true,
                'worktree_enabled' => true,
                'target_branch' => 'develop',
            ])
            ->create();

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}")
            ->assertOk();

        $this->assertTrue($response->json('data.git_settings.commit_enabled'));
        $this->assertTrue($response->json('data.git_settings.worktree_enabled'));
        $this->assertSame('develop', $response->json('data.git_settings.target_branch'));
        $this->assertFalse($response->json('data.git_settings.branching_enabled'));
    }

    public function test_git_settings_all_default_to_false(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create(['metadata_json' => ['source' => 'ui']]);

        $gitSettings = $session->gitSettings();

        $this->assertFalse($gitSettings['commit_enabled']);
        $this->assertFalse($gitSettings['conventional_commits']);
        $this->assertFalse($gitSettings['worktree_enabled']);
        $this->assertFalse($gitSettings['branching_enabled']);
        $this->assertNull($gitSettings['branch_prefix']);
        $this->assertNull($gitSettings['target_branch']);
    }

    public function test_branch_prefix_validation_rejects_invalid_characters(): void
    {
        $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Invalid Prefix',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'git' => [
                'branch_prefix' => 'feat branch!@#',
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson(['error' => ['details' => ['git.branch_prefix' => []]]]);
    }

    public function test_branch_prefix_validation_accepts_valid_patterns(): void
    {
        $validPrefixes = ['feat/', 'feature/my-team/', 'fix_', 'release-v1.0/'];

        foreach ($validPrefixes as $prefix) {
            $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
                'name' => "Prefix: {$prefix}",
                'runner_type' => 'claude',
                'project_directory' => base_path(),
                'interrogation_type' => 'feature',
                'feature_brief' => 'Test.',
                'git' => [
                    'branch_prefix' => $prefix,
                ],
            ]);

            $response->assertStatus(202, "Prefix '{$prefix}' should be valid");
        }
    }

    public function test_branch_prefix_max_length_enforced(): void
    {
        $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Long Prefix',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'git' => [
                'branch_prefix' => str_repeat('a', 51),
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_target_branch_rejected_when_branching_enabled(): void
    {
        $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Conflict',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'git' => [
                'branching_enabled' => true,
                'target_branch' => 'main',
            ],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson(['error' => ['details' => ['git.target_branch' => []]]]);
    }

    public function test_target_branch_accepted_when_branching_disabled(): void
    {
        $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Trunk Mode',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'git' => [
                'branching_enabled' => false,
                'target_branch' => 'develop',
            ],
        ])->assertStatus(202);

        $sessionId = $response->json('data.id');
        $session = InterrogationSession::find($sessionId);
        $this->assertSame('develop', $session->gitSettings()['target_branch']);
    }

    public function test_update_git_settings_audit_logged(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create();

        $this->patchJson("/agent/api/v1/interrogation/sessions/{$session->id}", [
            'git' => [
                'commit_enabled' => true,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('agent_audit_logs', [
            'action' => 'interrogation.session.update',
            'target_type' => 'interrogation_session',
            'target_id' => $session->id,
        ]);
    }
}
