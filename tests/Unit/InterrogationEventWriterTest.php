<?php

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\InterrogationEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationEventWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_discovery_activity_normalizes_invalid_utf8_and_redacts_secrets(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_DISCOVERING,
            'phase' => InterrogationSession::PHASE_DISCOVERY,
        ]);

        $writer = new InterrogationEventWriter($session);
        $event = $writer->appendDiscoveryActivity([
            'source' => 'claude',
            'message' => "\xC3\x28 token=super-secret",
        ]);

        $this->assertIsArray($event->payload);
        $this->assertTrue(mb_check_encoding((string) $event->payload['message'], 'UTF-8'));
        $this->assertStringContainsString('[REDACTED]', (string) $event->payload['message']);
    }

    public function test_append_recomputes_sequence_when_writer_counter_is_stale(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => InterrogationSession::TYPE_GENERAL,
            'status' => InterrogationSession::STATUS_DISCOVERING,
            'phase' => InterrogationSession::PHASE_DISCOVERY,
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'sequence' => 1,
            'payload' => ['message' => 'seed 1'],
            'event_ts' => now('UTC'),
        ]);

        $writer = new InterrogationEventWriter($session);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'sequence' => 2,
            'payload' => ['message' => 'seed 2'],
            'event_ts' => now('UTC'),
        ]);

        $event = $writer->appendDiscoveryActivity(['message' => 'writer append']);

        $this->assertSame(3, (int) $event->sequence);
    }
}
