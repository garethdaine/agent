<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Models\RepoAnalysisEvent;
use App\Models\RepoAnalysisSession;
use App\Models\User;
use App\Support\RepoAnalysis\EventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_assigns_monotonic_sequence_per_session(): void
    {
        $session = $this->createSession();

        $writer = new EventWriter($session);

        $first = $writer->append('snapshot.progress', ['message' => 'first']);
        $second = $writer->append('snapshot.progress', ['message' => 'second']);

        $this->assertSame(1, (int) $first->sequence);
        $this->assertSame(2, (int) $second->sequence);

        $otherSession = $this->createSession();
        $otherWriter = new EventWriter($otherSession);
        $otherFirst = $otherWriter->append('snapshot.progress', ['message' => 'other']);

        $this->assertSame(1, (int) $otherFirst->sequence);
    }

    public function test_append_recomputes_sequence_when_writer_counter_is_stale(): void
    {
        $session = $this->createSession();

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'event_type' => 'seed.one',
            'sequence' => 1,
            'payload_json' => ['message' => 'seed one'],
            'event_ts' => now('UTC'),
        ]);

        $writer = new EventWriter($session);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'event_type' => 'seed.two',
            'sequence' => 2,
            'payload_json' => ['message' => 'seed two'],
            'event_ts' => now('UTC'),
        ]);

        $event = $writer->append('snapshot.progress', ['message' => 'writer append']);

        $this->assertSame(3, (int) $event->sequence);
    }

    public function test_append_normalizes_invalid_utf8_and_redacts_nested_secrets(): void
    {
        $session = $this->createSession();
        $writer = new EventWriter($session);

        $event = $writer->append('task.failed', [
            'message' => "rollout failure: \xC3\x28 token=super-secret",
            'nested' => [
                "bad-key-\xC3\x28" => [
                    'api_token' => 'should-not-leak',
                    'detail' => 'Bearer abc123xyz',
                    'stderr' => "bad-bytes-\xB1\x31",
                ],
            ],
        ]);

        $payload = $event->payload_json ?? [];
        $nested = (array) ($payload['nested'] ?? []);
        $nestedKey = is_array($nested) ? array_key_first($nested) : null;
        $nestedPayload = is_string($nestedKey) ? (array) ($nested[$nestedKey] ?? []) : [];

        $this->assertIsArray($payload);
        $this->assertTrue(mb_check_encoding(json_encode($payload) ?: '', 'UTF-8'));
        $this->assertStringContainsString('[REDACTED]', (string) data_get($payload, 'message', ''));
        $this->assertNotNull($nestedKey);
        $this->assertSame('[REDACTED]', $nestedPayload['api_token'] ?? null);
        $this->assertStringContainsString('[REDACTED_BEARER_TOKEN]', (string) ($nestedPayload['detail'] ?? ''));
    }

    public function test_append_invokes_broadcast_hook_with_storage_consistent_payload(): void
    {
        $session = $this->createSession();
        $captured = null;

        $writer = new EventWriter($session, function (array $envelope, RepoAnalysisEvent $event) use (&$captured): void {
            $captured = [
                'envelope' => $envelope,
                'event_id' => (int) $event->id,
                'event_sequence' => (int) $event->sequence,
            ];
        });

        $event = $writer->append('coverage.gate', [
            'token' => 'top-secret',
        ], [
            'phase' => 4,
            'status' => 'validating',
        ]);

        $this->assertNotNull($captured);
        $this->assertSame((int) $event->id, $captured['event_id']);
        $this->assertSame((int) $event->sequence, $captured['event_sequence']);
        $this->assertSame((int) $session->id, $captured['envelope']['session_id']);
        $this->assertSame((int) $event->sequence, $captured['envelope']['sequence']);
        $this->assertSame($event->event_type, $captured['envelope']['event_type']);
        $this->assertSame($event->payload_json, $captured['envelope']['payload']);
        $this->assertSame((int) $event->phase, $captured['envelope']['phase']);
        $this->assertSame($event->status, $captured['envelope']['status']);
    }

    public function test_read_since_sequence_returns_strict_order_and_empty_incremental_fetch(): void
    {
        $session = $this->createSession();
        $otherSession = $this->createSession();

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'event_type' => 'event.one',
            'sequence' => 1,
            'payload_json' => ['idx' => 1],
            'event_ts' => now('UTC'),
        ]);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $otherSession->id,
            'event_type' => 'other.event',
            'sequence' => 1,
            'payload_json' => ['idx' => 999],
            'event_ts' => now('UTC'),
        ]);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'event_type' => 'event.two',
            'sequence' => 2,
            'payload_json' => ['idx' => 2],
            'event_ts' => now('UTC'),
        ]);

        RepoAnalysisEvent::query()->create([
            'repo_analysis_session_id' => $session->id,
            'event_type' => 'event.three',
            'sequence' => 3,
            'payload_json' => ['idx' => 3],
            'event_ts' => now('UTC'),
        ]);

        $events = EventWriter::readSinceSequence((int) $session->id, 1, 50);

        $this->assertCount(2, $events);
        $this->assertSame([2, 3], $events->pluck('sequence')->map(fn ($value): int => (int) $value)->all());

        $empty = EventWriter::readSinceSequence((int) $session->id, 3, 50);

        $this->assertCount(0, $empty);
    }

    private function createSession(): RepoAnalysisSession
    {
        $user = User::factory()->create();

        return RepoAnalysisSession::query()->create([
            'user_id' => $user->id,
            'project_directory' => '/tmp/repo-analysis-test',
            'status' => 'setup',
            'phase' => 0,
        ]);
    }
}
