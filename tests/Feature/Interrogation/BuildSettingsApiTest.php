<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BuildSettingsApiTest extends TestCase
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

    public function test_create_session_with_build_settings_stores_in_metadata(): void
    {
        $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Build Settings Test',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test build settings.',
            'build_settings' => [
                'auto_advance_tasks' => false,
            ],
        ])->assertStatus(202);

        $sessionId = $response->json('data.id');
        $session = InterrogationSession::find($sessionId);

        $buildSettings = $session->buildSettings();
        $this->assertFalse($buildSettings['auto_advance_tasks']);
        $this->assertNotNull($buildSettings['updated_at']);

        // Also returned in response
        $this->assertFalse($response->json('data.build_settings.auto_advance_tasks'));
    }

    public function test_create_session_without_build_settings_defaults_to_auto_advance(): void
    {
        $response = $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'No Build Settings',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'No build settings provided.',
        ])->assertStatus(202);

        $sessionId = $response->json('data.id');
        $session = InterrogationSession::find($sessionId);

        $buildSettings = $session->buildSettings();
        $this->assertTrue($buildSettings['auto_advance_tasks']);

        // Response still includes defaults
        $this->assertTrue($response->json('data.build_settings.auto_advance_tasks'));
    }

    public function test_update_session_build_settings_via_patch(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings(['auto_advance_tasks' => true])
            ->create();

        $this->patchJson("/agent/api/v1/interrogation/sessions/{$session->id}", [
            'build_settings' => [
                'auto_advance_tasks' => false,
            ],
        ])->assertOk();

        $session->refresh();
        $buildSettings = $session->buildSettings();
        $this->assertFalse($buildSettings['auto_advance_tasks']);
        $this->assertNotNull($buildSettings['updated_at']);
    }

    public function test_update_session_build_settings_preserves_other_metadata(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withGitSettings(['commit_enabled' => true])
            ->withBuildSettings(['auto_advance_tasks' => true])
            ->create();

        $this->patchJson("/agent/api/v1/interrogation/sessions/{$session->id}", [
            'build_settings' => [
                'auto_advance_tasks' => false,
            ],
        ])->assertOk();

        $session->refresh();

        // Build settings updated
        $this->assertFalse($session->buildSettings()['auto_advance_tasks']);

        // Git settings preserved
        $this->assertTrue($session->gitSettings()['commit_enabled']);
    }

    public function test_get_session_includes_build_settings_in_response(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings(['auto_advance_tasks' => false])
            ->create();

        $response = $this->getJson("/agent/api/v1/interrogation/sessions/{$session->id}")
            ->assertOk();

        $this->assertFalse($response->json('data.build_settings.auto_advance_tasks'));
    }

    public function test_build_settings_validation_rejects_non_boolean_auto_advance(): void
    {
        $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Invalid Build Settings',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'build_settings' => [
                'auto_advance_tasks' => 'not-a-boolean',
            ],
        ])->assertStatus(422);
    }

    public function test_build_settings_validation_rejects_non_array(): void
    {
        $this->postJson('/agent/api/v1/interrogation/sessions', [
            'name' => 'Invalid Build Settings',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => 'feature',
            'feature_brief' => 'Test.',
            'build_settings' => 'not-an-array',
        ])->assertStatus(422);
    }

    public function test_factory_with_build_settings_state(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings(['auto_advance_tasks' => false])
            ->create();

        $this->assertFalse($session->buildSettings()['auto_advance_tasks']);
    }

    public function test_factory_with_build_settings_defaults(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->withBuildSettings()
            ->create();

        $this->assertTrue($session->buildSettings()['auto_advance_tasks']);
    }

    public function test_build_settings_accessor_returns_defaults_when_no_metadata(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create();

        $buildSettings = $session->buildSettings();
        $this->assertTrue($buildSettings['auto_advance_tasks']);
        $this->assertNull($buildSettings['updated_at']);
    }
}
