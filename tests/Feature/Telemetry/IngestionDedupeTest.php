<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\IngestionService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngestionDedupeTest extends TestCase
{
    use DatabaseTruncation;

    protected array $tablesToTruncate = [
        'telemetry_event_ledger',
    ];

    public function test_ingestion_is_idempotent_for_duplicate_event_and_attempt(): void
    {
        $service = app(IngestionService::class);
        $payload = $this->payload(eventId: 'evt-1', runAttemptId: 'attempt-1', sequence: 1);

        $first = $service->ingest($payload);
        $second = $service->ingest($payload);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, DB::table('telemetry_event_ledger')->count());
    }

    public function test_ingestion_pins_schema_metadata_and_marks_missing_provider_cost_as_estimated(): void
    {
        $service = app(IngestionService::class);

        $result = $service->ingest($this->payload(eventId: 'evt-2', runAttemptId: 'attempt-2', sequence: 1, payload: [
            'provider' => 'openai',
            // Missing provider_cost block should be accepted but estimated.
        ]));

        $this->assertFalse($result['duplicate']);

        $row = DB::table('telemetry_event_ledger')->where('id', $result['id'])->first();

        $this->assertNotNull($row);
        $this->assertNotNull($row->schema_hash);
        $this->assertNotNull($row->normalizer_version);
        $this->assertNotNull($row->registry_revision);
        $this->assertTrue((bool) $row->telemetry_estimated);
    }

    public function test_out_of_order_events_are_accepted_and_sequence_violation_reason_is_recorded(): void
    {
        $service = app(IngestionService::class);

        $first = $service->ingest($this->payload(eventId: 'evt-3', runAttemptId: 'attempt-3', sequence: 5));
        $second = $service->ingest($this->payload(eventId: 'evt-4', runAttemptId: 'attempt-3', sequence: 3));

        $this->assertFalse($first['duplicate']);
        $this->assertFalse($second['duplicate']);
        $this->assertSame(2, DB::table('telemetry_event_ledger')->where('run_attempt_id', 'attempt-3')->count());

        $rawPayload = DB::table('telemetry_event_ledger')
            ->where('id', $second['id'])
            ->value('payload_json');

        $this->assertIsString($rawPayload);
        $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($payload);
        $this->assertSame('decrease', data_get($payload, '_ingestion.sequence_violation_reason'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payload(string $eventId, string $runAttemptId, int $sequence, array $payload = []): array
    {
        return [
            'schema_name' => 'agent.telemetry.event',
            'schema_version' => '1.0.0',
            'event_id' => $eventId,
            'run_id' => 'run-'.$runAttemptId,
            'run_attempt_id' => $runAttemptId,
            'parent_run_id' => null,
            'workflow_key' => 'eng.repo-analysis.v1',
            'event_type' => 'run.progress',
            'event_sequence' => $sequence,
            'event_at' => now('UTC')->toIso8601String(),
            'payload' => $payload,
            'terminal' => false,
        ];
    }
}
