<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class IngestionService
{
    public function __construct(
        private readonly VersionedSchemaRegistry $registry,
        private readonly TerminalizationGapProjector $terminalizationGapProjector,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     * @return array{id: int, duplicate: bool}
     */
    public function ingest(array $event): array
    {
        $validated = $this->validateEnvelope($event);

        $eventId = (string) $validated['event_id'];
        $runAttemptId = (string) $validated['run_attempt_id'];
        $schemaName = (string) $validated['schema_name'];
        $schemaVersion = (string) $validated['schema_version'];
        $eventSequence = (int) $validated['event_sequence'];
        $eventType = (string) $validated['event_type'];
        $isTerminal = (bool) $validated['terminal'];

        $pin = $this->registry->resolve($schemaName, $schemaVersion);

        $ingestedAt = CarbonImmutable::now('UTC');
        $eventAt = $this->normalizeEventAt($validated['event_at'] ?? null, $ingestedAt);

        $payload = $this->normalizePayload($validated['payload'] ?? []);
        $sequenceViolationReason = $this->determineSequenceViolationReason($runAttemptId, $eventSequence);

        if ($sequenceViolationReason !== null) {
            $payload['_ingestion'] = array_merge(
                is_array($payload['_ingestion'] ?? null) ? $payload['_ingestion'] : [],
                [
                    'sequence_violation' => true,
                    'sequence_violation_reason' => $sequenceViolationReason,
                ]
            );
        }

        $telemetryEstimated = $this->resolveTelemetryEstimated($payload, $validated);

        try {
            $id = (int) DB::table('telemetry_event_ledger')->insertGetId([
                'schema_name' => $schemaName,
                'schema_version' => $schemaVersion,
                'event_id' => $eventId,
                'run_id' => $this->nullableString($validated['run_id'] ?? null),
                'run_attempt_id' => $runAttemptId,
                'parent_run_id' => $this->nullableString($validated['parent_run_id'] ?? null),
                'workflow_key' => $this->nullableString($validated['workflow_key'] ?? null),
                'event_type' => $eventType,
                'event_sequence' => $eventSequence,
                'event_at' => $eventAt,
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
                'schema_hash' => $pin['schema_hash'],
                'normalizer_version' => $pin['normalizer_version'],
                'registry_revision' => $pin['registry_revision'],
                'rate_card_version' => $this->nullableString($validated['rate_card_version'] ?? null),
                'failure_class' => $this->nullableString($validated['failure_class'] ?? null),
                'failure_reason_code' => $this->nullableString($validated['failure_reason_code'] ?? null),
                'telemetry_estimated' => $telemetryEstimated,
                'telemetry_delayed' => (bool) ($validated['telemetry_delayed'] ?? false),
                'terminal' => $isTerminal,
                'ingested_at' => $ingestedAt,
                'created_at' => $ingestedAt,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateException($exception)) {
                throw $exception;
            }

            $existingId = (int) DB::table('telemetry_event_ledger')
                ->where('event_id', $eventId)
                ->where('run_attempt_id', $runAttemptId)
                ->value('id');

            return ['id' => $existingId, 'duplicate' => true];
        }

        if ($isTerminal && $this->isConfiguredTerminalEventType($eventType)) {
            $this->terminalizationGapProjector->projectGapAuditIfNeeded(
                runAttemptId: $runAttemptId,
                terminalEventId: $eventId,
                terminalSequence: $eventSequence,
                runId: $this->nullableString($validated['run_id'] ?? null),
                workflowKey: $this->nullableString($validated['workflow_key'] ?? null),
                parentRunId: $this->nullableString($validated['parent_run_id'] ?? null),
                eventAt: $eventAt,
                ingestedAt: $ingestedAt,
            );
        }

        return ['id' => $id, 'duplicate' => false];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function validateEnvelope(array $event): array
    {
        $eventId = trim((string) ($event['event_id'] ?? ''));
        $runAttemptId = trim((string) ($event['run_attempt_id'] ?? ''));
        $eventType = trim((string) ($event['event_type'] ?? ''));

        if ($eventId === '' || $runAttemptId === '' || $eventType === '') {
            throw new \InvalidArgumentException('Telemetry envelope requires event_id, run_attempt_id, and event_type.');
        }

        $event['event_id'] = $eventId;
        $event['run_attempt_id'] = $runAttemptId;
        $event['event_type'] = $eventType;

        $event['schema_name'] = trim((string) ($event['schema_name'] ?? 'agent.telemetry.event'));
        $event['schema_version'] = trim((string) ($event['schema_version'] ?? '1.0.0'));
        $event['event_sequence'] = max(0, (int) ($event['event_sequence'] ?? 0));
        $event['terminal'] = (bool) ($event['terminal'] ?? false);

        return $event;
    }

    private function normalizeEventAt(mixed $eventAt, CarbonImmutable $default): CarbonImmutable
    {
        if (! is_string($eventAt) || trim($eventAt) === '') {
            return $default;
        }

        try {
            return CarbonImmutable::parse($eventAt)->utc();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function determineSequenceViolationReason(string $runAttemptId, int $eventSequence): ?string
    {
        $existingSequences = DB::table('telemetry_event_ledger')
            ->where('run_attempt_id', $runAttemptId)
            ->pluck('event_sequence')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        if ($existingSequences === []) {
            return null;
        }

        if (in_array($eventSequence, $existingSequences, true)) {
            return 'duplicate';
        }

        $maxSequence = max($existingSequences);

        if ($eventSequence < $maxSequence) {
            return 'decrease';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $validated
     */
    private function resolveTelemetryEstimated(array $payload, array $validated): bool
    {
        if ((bool) ($validated['telemetry_estimated'] ?? false)) {
            return true;
        }

        $providerCost = $payload['provider_cost'] ?? null;
        if (! is_array($providerCost)) {
            return true;
        }

        $requiredFields = ['input_tokens', 'output_tokens', 'total_cost_usd'];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $providerCost) || $providerCost[$field] === null || $providerCost[$field] === '') {
                return true;
            }
        }

        return false;
    }

    private function isConfiguredTerminalEventType(string $eventType): bool
    {
        $catalog = config('agent.telemetry.terminal_event_types', []);
        if (! is_array($catalog)) {
            return false;
        }

        return in_array($eventType, $catalog, true);
    }

    private function isDuplicateException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        if ($sqlState === '23505') {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'telemetry_event_ledger_event_attempt_unique');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
