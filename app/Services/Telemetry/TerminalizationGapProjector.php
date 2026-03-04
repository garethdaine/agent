<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TerminalizationGapProjector
{
    public function __construct(
        private readonly VersionedSchemaRegistry $registry
    ) {}

    /**
     * @return array{gap_detected: bool, missing_sequences: array<int, int>}
     */
    public function projectGapAuditIfNeeded(
        string $runAttemptId,
        string $terminalEventId,
        int $terminalSequence,
        ?string $runId,
        ?string $workflowKey,
        ?string $parentRunId,
        CarbonImmutable $eventAt,
        CarbonImmutable $ingestedAt
    ): array {
        $rows = DB::table('telemetry_event_ledger')
            ->where('run_attempt_id', $runAttemptId)
            ->orderBy('event_sequence')
            ->orderBy('id')
            ->get(['event_sequence']);

        $sequences = $rows
            ->pluck('event_sequence')
            ->map(static fn (mixed $value): int => (int) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $missingSequences = $this->findMissingSequences($sequences, $terminalSequence);
        if ($missingSequences === []) {
            return ['gap_detected' => false, 'missing_sequences' => []];
        }

        $syntheticEventType = (string) config('agent.telemetry.synthetic_gap_event_type', 'telemetry.synthetic.gap_audit');
        $syntheticEventId = 'gap-audit-'.$runAttemptId.'-'.$terminalEventId;

        $schemaName = 'agent.telemetry.event';
        $schemaVersion = '1.0.0';
        $pin = $this->registry->resolve($schemaName, $schemaVersion);

        try {
            DB::table('telemetry_event_ledger')->insert([
                'schema_name' => $schemaName,
                'schema_version' => $schemaVersion,
                'event_id' => $syntheticEventId,
                'run_id' => $runId,
                'run_attempt_id' => $runAttemptId,
                'parent_run_id' => $parentRunId,
                'workflow_key' => $workflowKey,
                'event_type' => $syntheticEventType,
                'event_sequence' => $terminalSequence,
                'event_at' => $eventAt,
                'payload_json' => json_encode([
                    '_ingestion' => [
                        'synthetic' => true,
                        'sequence_violation' => true,
                        'sequence_violation_reason' => 'gap_at_terminalization',
                    ],
                    'terminal_event_id' => $terminalEventId,
                    'missing_sequences' => $missingSequences,
                ], JSON_THROW_ON_ERROR),
                'schema_hash' => $pin['schema_hash'],
                'normalizer_version' => $pin['normalizer_version'],
                'registry_revision' => $pin['registry_revision'],
                'rate_card_version' => null,
                'failure_class' => null,
                'failure_reason_code' => null,
                'telemetry_estimated' => false,
                'telemetry_delayed' => false,
                'terminal' => false,
                'ingested_at' => $ingestedAt,
                'created_at' => $ingestedAt,
            ]);
        } catch (\Throwable) {
            // Synthetic insert is dedupe-safe; duplicate insert attempts should not fail ingestion.
        }

        return [
            'gap_detected' => true,
            'missing_sequences' => $missingSequences,
        ];
    }

    /**
     * @param  array<int, int>  $sequences
     * @return array<int, int>
     */
    private function findMissingSequences(array $sequences, int $terminalSequence): array
    {
        if ($terminalSequence <= 1) {
            return [];
        }

        $expected = range(1, $terminalSequence);

        return array_values(array_filter($expected, static fn (int $sequence): bool => ! in_array($sequence, $sequences, true)));
    }
}
