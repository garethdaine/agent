<?php

namespace Tests\Feature;

use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_session(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        $this->assertTrue($user->can('view', $session));
    }

    public function test_non_owner_cannot_view_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $owner->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        $this->assertFalse($other->can('view', $session));
    }

    public function test_owner_can_update_session(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);

        $this->assertTrue($user->can('update', $session));
    }

    public function test_non_owner_cannot_update_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $owner->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);

        $this->assertFalse($other->can('update', $session));
    }

    public function test_owner_can_delete_session(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'completed',
        ]);

        $this->assertTrue($user->can('delete', $session));
    }

    public function test_non_owner_cannot_delete_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $owner->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'completed',
        ]);

        $this->assertFalse($other->can('delete', $session));
    }

    public function test_user_can_create_session_under_limit(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('create', InterrogationSession::class));
    }

    public function test_user_can_create_when_at_active_limit_and_validation_handles_quota(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            InterrogationSession::create([
                'user_id' => $user->id,
                'runner_type' => 'claude',
                'project_directory' => "/tmp/test-{$i}",
                'interrogation_type' => 'general',
                'status' => 'interrogating',
            ]);
        }

        $this->assertTrue($user->can('create', InterrogationSession::class));
    }

    public function test_completed_sessions_do_not_count_toward_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            InterrogationSession::create([
                'user_id' => $user->id,
                'runner_type' => 'claude',
                'project_directory' => "/tmp/test-{$i}",
                'interrogation_type' => 'general',
                'status' => 'completed',
            ]);
        }

        $this->assertTrue($user->can('create', InterrogationSession::class));
    }

    public function test_paused_sessions_do_not_count_toward_active_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            InterrogationSession::create([
                'user_id' => $user->id,
                'runner_type' => 'claude',
                'project_directory' => "/tmp/test-{$i}",
                'interrogation_type' => 'general',
                'status' => 'paused',
            ]);
        }

        $this->assertTrue($user->can('create', InterrogationSession::class));
    }

    public function test_owner_can_restore_soft_deleted_session(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'completed',
        ]);

        $session->delete();

        $this->assertTrue($user->can('restore', $session));
    }
}
