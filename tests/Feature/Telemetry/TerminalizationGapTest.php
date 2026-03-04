<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\IngestionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TerminalizationGapTest extends TestCase
{
    use DatabaseMigrations;

    public function test_terminal_gap_creates_synthetic_audit_event(): void
    {
        $service = app(IngestionService::class);

        $service->ingest($this->payload('evt-a', 'attempt-gap-1', 1, 'run.progress', false));
        $terminal = $service->ingest($this->payload('evt-b', 'attempt-gap-1', 3, 'run.completed', true));

        $this->assertFalse($terminal['duplicate']);

        $syntheticType = (string) config('agent.telemetry.synthetic_gap_event_type');
        $syntheticRow = DB::table('telemetry_event_ledger')
            ->where('run_attempt_id', 'attempt-gap-1')
            ->where('event_type', $syntheticType)
            ->first();

        $this->assertNotNull($syntheticRow);

        $payload = json_decode((string) $syntheticRow->payload_json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('gap_at_terminalization', data_get($payload, '_ingestion.sequence_violation_reason'));
    }

    public function test_non_terminal_or_non_catalog_event_does_not_emit_terminal_gap_audit(): void
    {
        $service = app(IngestionService::class);

        $service->ingest($this->payload('evt-c', 'attempt-gap-2', 1, 'run.progress', false));
        $service->ingest($this->payload('evt-d', 'attempt-gap-2', 3, 'run.custom_terminal_like', true));

        $syntheticType = (string) config('agent.telemetry.synthetic_gap_event_type');

        $this->assertSame(
            0,
            DB::table('telemetry_event_ledger')
                ->where('run_attempt_id', 'attempt-gap-2')
                ->where('event_type', $syntheticType)
                ->count()
        );
    }

    private function payload(string $eventId, string $runAttemptId, int $sequence, string $eventType, bool $terminal): array
    {
        return [
            'schema_name' => 'agent.telemetry.event',
            'schema_version' => '1.0.0',
            'event_id' => $eventId,
            'run_id' => 'run-'.$runAttemptId,
            'run_attempt_id' => $runAttemptId,
            'workflow_key' => 'eng.repo-analysis.v1',
            'event_type' => $eventType,
            'event_sequence' => $sequence,
            'event_at' => now('UTC')->toIso8601String(),
            'payload' => [],
            'terminal' => $terminal,
        ];
    }
}
