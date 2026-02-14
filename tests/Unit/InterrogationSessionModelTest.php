<?php

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationSessionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_exist(): void
    {
        $this->assertSame('setup', InterrogationSession::STATUS_SETUP);
        $this->assertSame('discovering', InterrogationSession::STATUS_DISCOVERING);
        $this->assertSame('interrogating', InterrogationSession::STATUS_INTERROGATING);
        $this->assertSame('summarizing', InterrogationSession::STATUS_SUMMARIZING);
        $this->assertSame('planning', InterrogationSession::STATUS_PLANNING);
        $this->assertSame('completed', InterrogationSession::STATUS_COMPLETED);
        $this->assertSame('failed', InterrogationSession::STATUS_FAILED);
        $this->assertSame('paused', InterrogationSession::STATUS_PAUSED);
    }

    public function test_active_and_terminal_statuses_are_disjoint(): void
    {
        $overlap = array_intersect(
            InterrogationSession::ACTIVE_STATUSES,
            InterrogationSession::TERMINAL_STATUSES
        );

        $this->assertEmpty($overlap);
    }

    public function test_active_statuses_cover_expected_values(): void
    {
        $this->assertContains('setup', InterrogationSession::ACTIVE_STATUSES);
        $this->assertContains('discovering', InterrogationSession::ACTIVE_STATUSES);
        $this->assertContains('interrogating', InterrogationSession::ACTIVE_STATUSES);
        $this->assertContains('summarizing', InterrogationSession::ACTIVE_STATUSES);
        $this->assertContains('planning', InterrogationSession::ACTIVE_STATUSES);
    }

    public function test_terminal_statuses_cover_expected_values(): void
    {
        $this->assertContains('completed', InterrogationSession::TERMINAL_STATUSES);
        $this->assertContains('failed', InterrogationSession::TERMINAL_STATUSES);
    }

    public function test_resumable_statuses_cover_expected_values(): void
    {
        $this->assertContains('paused', InterrogationSession::RESUMABLE_STATUSES);
        $this->assertContains('failed', InterrogationSession::RESUMABLE_STATUSES);
    }

    public function test_phase_constants_are_sequential(): void
    {
        $this->assertSame(0, InterrogationSession::PHASE_SETUP);
        $this->assertSame(1, InterrogationSession::PHASE_DISCOVERY);
        $this->assertSame(2, InterrogationSession::PHASE_INTERROGATION);
        $this->assertSame(3, InterrogationSession::PHASE_SUMMARY);
        $this->assertSame(4, InterrogationSession::PHASE_PLANNING);
    }

    public function test_type_constants_exist(): void
    {
        $this->assertSame('feature', InterrogationSession::TYPE_FEATURE);
        $this->assertSame('general', InterrogationSession::TYPE_GENERAL);
    }

    public function test_json_columns_cast_to_array(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'feature',
            'status' => 'setup',
            'summary_json' => ['purpose' => 'test'],
            'plan_json' => ['steps' => ['step1']],
            'annotations_json' => ['note' => 'important'],
            'metadata_json' => ['progress' => 50],
        ]);

        $session->refresh();

        $this->assertIsArray($session->summary_json);
        $this->assertIsArray($session->plan_json);
        $this->assertIsArray($session->annotations_json);
        $this->assertIsArray($session->metadata_json);
        $this->assertSame('test', $session->summary_json['purpose']);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        $this->assertInstanceOf(User::class, $session->user);
        $this->assertSame($user->id, $session->user->id);
    }

    public function test_events_relationship(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_SYSTEM,
            'sequence' => 1,
            'payload' => ['message' => 'started'],
            'event_ts' => now(),
        ]);

        $this->assertCount(1, $session->events);
    }

    public function test_active_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);

        InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test2',
            'interrogation_type' => 'general',
            'status' => 'completed',
        ]);

        $active = InterrogationSession::active()->get();

        $this->assertCount(1, $active);
        $this->assertSame('interrogating', $active->first()->status);
    }

    public function test_for_user_scope_filters_correctly(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        InterrogationSession::create([
            'user_id' => $user1->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        InterrogationSession::create([
            'user_id' => $user2->id,
            'runner_type' => 'codex',
            'project_directory' => '/tmp/test2',
            'interrogation_type' => 'feature',
            'status' => 'setup',
        ]);

        $user1Sessions = InterrogationSession::forUser($user1->id)->get();

        $this->assertCount(1, $user1Sessions);
        $this->assertSame($user1->id, $user1Sessions->first()->user_id);
    }

    public function test_soft_deletes_work(): void
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

        $this->assertSoftDeleted('interrogation_sessions', ['id' => $session->id]);
        $this->assertNull(InterrogationSession::find($session->id));
        $this->assertNotNull(InterrogationSession::withTrashed()->find($session->id));
    }
}
