<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationEventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_type_constants_exist(): void
    {
        $this->assertSame('discovery_activity', InterrogationEvent::TYPE_DISCOVERY_ACTIVITY);
        $this->assertSame('question', InterrogationEvent::TYPE_QUESTION);
        $this->assertSame('answer', InterrogationEvent::TYPE_ANSWER);
        $this->assertSame('phase_transition', InterrogationEvent::TYPE_PHASE_TRANSITION);
        $this->assertSame('summary', InterrogationEvent::TYPE_SUMMARY);
        $this->assertSame('plan', InterrogationEvent::TYPE_PLAN);
        $this->assertSame('error', InterrogationEvent::TYPE_ERROR);
        $this->assertSame('annotation', InterrogationEvent::TYPE_ANNOTATION);
        $this->assertSame('system', InterrogationEvent::TYPE_SYSTEM);
    }

    public function test_payload_casts_to_array(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);

        $event = InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q1',
                'question_text' => 'What is the purpose?',
                'answer_type' => 'freetext',
            ],
            'event_ts' => now(),
        ]);

        $event->refresh();

        $this->assertIsArray($event->payload);
        $this->assertSame('q1', $event->payload['question_id']);
    }

    public function test_session_relationship(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);

        $event = InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_SYSTEM,
            'sequence' => 1,
            'payload' => ['message' => 'test'],
            'event_ts' => now(),
        ]);

        $this->assertInstanceOf(InterrogationSession::class, $event->session);
        $this->assertSame($session->id, $event->session->id);
    }

    public function test_sequence_is_cast_to_integer(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'setup',
        ]);

        $event = InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_SYSTEM,
            'sequence' => 42,
            'payload' => ['message' => 'test'],
            'event_ts' => now(),
        ]);

        $event->refresh();

        $this->assertIsInt($event->sequence);
        $this->assertSame(42, $event->sequence);
    }

    public function test_sequence_uniqueness_per_session(): void
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
            'payload' => ['message' => 'first'],
            'event_ts' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_SYSTEM,
            'sequence' => 1,
            'payload' => ['message' => 'duplicate'],
            'event_ts' => now(),
        ]);
    }

    public function test_payload_setter_sanitizes_invalid_utf8_bytes(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'codex',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'discovering',
        ]);

        $event = InterrogationEvent::create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'sequence' => 1,
            'payload' => [
                "bad-key-\xC3\x28" => "bad-value-\xE2\x28\xA1",
                'nested' => ['detail' => "detail-\xB1\x31"],
            ],
            'event_ts' => now(),
        ])->refresh();

        $this->assertIsArray($event->payload);
        $this->assertTrue(mb_check_encoding(json_encode($event->payload) ?: '', 'UTF-8'));
    }
}
