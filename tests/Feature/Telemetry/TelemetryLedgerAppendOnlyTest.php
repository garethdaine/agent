<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelemetryLedgerAppendOnlyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $tablesToTruncate = [
        'telemetry_event_ledger',
    ];

    public function test_ledger_has_v1_contract_columns(): void
    {
        $this->assertTrue(Schema::hasTable('telemetry_event_ledger'));
        $this->assertTrue(Schema::hasColumns('telemetry_event_ledger', [
            'id',
            'schema_name',
            'schema_version',
            'event_id',
            'run_id',
            'run_attempt_id',
            'parent_run_id',
            'workflow_key',
            'event_type',
            'event_sequence',
            'event_at',
            'payload_json',
            'schema_hash',
            'normalizer_version',
            'registry_revision',
            'rate_card_version',
            'failure_class',
            'failure_reason_code',
            'telemetry_estimated',
            'telemetry_delayed',
            'terminal',
            'ingested_at',
            'created_at',
        ]));
    }

    public function test_update_is_blocked_by_append_only_trigger(): void
    {
        $id = $this->insertLedgerRow();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('telemetry_event_ledger')
            ->where('id', $id)
            ->update(['event_type' => 'run.mutated']);
    }

    public function test_delete_is_blocked_by_append_only_trigger(): void
    {
        $id = $this->insertLedgerRow();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('telemetry_event_ledger')
            ->where('id', $id)
            ->delete();
    }

    public function test_dedupe_key_conflict_is_deterministic(): void
    {
        $eventId = 'event-'.(string) str()->uuid();
        $attemptId = 'attempt-'.(string) str()->uuid();
        $this->insertLedgerRow($eventId, $attemptId);

        try {
            $this->insertLedgerRow($eventId, $attemptId);
            $this->fail('Expected duplicate (event_id, run_attempt_id) insert to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'telemetry_event_ledger_event_attempt_unique',
                strtolower($exception->getMessage())
            );
        }
    }

    public function test_trigger_still_blocks_mutation_after_direct_grant(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Trigger privilege drift assertion requires PostgreSQL.');
        }

        $id = $this->insertLedgerRow();
        $dbUser = DB::scalar('SELECT current_user');
        DB::statement('GRANT UPDATE, DELETE ON TABLE public.telemetry_event_ledger TO "'.$dbUser.'"');

        try {
            DB::table('telemetry_event_ledger')->where('id', $id)->update(['event_type' => 'run.mutated']);
            $this->fail('Expected trigger to block UPDATE even with direct grant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', strtolower($exception->getMessage()));
        }

        try {
            DB::table('telemetry_event_ledger')->where('id', $id)->delete();
            $this->fail('Expected trigger to block DELETE even with direct grant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', strtolower($exception->getMessage()));
        }
    }

    private function insertLedgerRow(?string $eventId = null, ?string $runAttemptId = null): int
    {
        return (int) DB::table('telemetry_event_ledger')->insertGetId([
            'schema_name' => 'agent.telemetry.event',
            'schema_version' => '1.0.0',
            'event_id' => $eventId ?? 'event-'.(string) str()->uuid(),
            'run_id' => 'run-'.(string) str()->uuid(),
            'run_attempt_id' => $runAttemptId ?? 'attempt-'.(string) str()->uuid(),
            'parent_run_id' => null,
            'workflow_key' => 'eng.repo-analysis.v1',
            'event_type' => 'run.started',
            'event_sequence' => 1,
            'event_at' => now(),
            'payload_json' => json_encode(['state' => 'starting'], JSON_THROW_ON_ERROR),
            'schema_hash' => str_repeat('a', 64),
            'normalizer_version' => 'telemetry-normalizer.v1',
            'registry_revision' => 'registry-2026-03-04',
            'rate_card_version' => '2026-03',
            'failure_class' => 'control_flow',
            'failure_reason_code' => 'skipped',
            'telemetry_estimated' => false,
            'telemetry_delayed' => false,
            'terminal' => false,
            'ingested_at' => now(),
            'created_at' => now(),
        ]);
    }
}
