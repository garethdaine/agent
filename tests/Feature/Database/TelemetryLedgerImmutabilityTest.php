<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelemetryLedgerImmutabilityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_telemetry_ledger_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('telemetry_event_ledger'));
        $this->assertTrue(Schema::hasColumns('telemetry_event_ledger', [
            'id',
            'event_id',
            'run_attempt_id',
            'workflow_key',
            'event_type',
            'payload_json',
            'ingested_at',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasTable('telemetry_ledger_mutation_attempts'));
        $this->assertTrue(Schema::hasColumns('telemetry_ledger_mutation_attempts', [
            'id',
            'attempted_at',
            'db_user',
            'application_name',
            'client_addr',
            'operation',
            'table_name',
            'row_identifier',
            'query_text',
            'context_json',
            'created_at',
        ]));
    }

    public function test_insert_into_telemetry_ledger_still_succeeds(): void
    {
        $id = $this->insertLedgerRow();

        $this->assertDatabaseHas('telemetry_event_ledger', [
            'id' => $id,
            'event_type' => 'run.started',
        ]);
    }

    public function test_update_attempt_is_blocked_and_audited(): void
    {
        $id = $this->insertLedgerRow();

        try {
            DB::table('telemetry_event_ledger')
                ->where('id', $id)
                ->update(['event_type' => 'run.mutated']);

            $this->fail('Expected UPDATE on telemetry_event_ledger to be blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseHas('telemetry_event_ledger', [
            'id' => $id,
            'event_type' => 'run.started',
        ]);

        $this->assertDatabaseHas('telemetry_ledger_mutation_attempts', [
            'operation' => 'UPDATE',
            'table_name' => 'telemetry_event_ledger',
            'row_identifier' => (string) $id,
        ]);
    }

    public function test_delete_attempt_is_blocked_and_audited(): void
    {
        $id = $this->insertLedgerRow();

        try {
            DB::table('telemetry_event_ledger')
                ->where('id', $id)
                ->delete();

            $this->fail('Expected DELETE on telemetry_event_ledger to be blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseHas('telemetry_event_ledger', [
            'id' => $id,
            'event_type' => 'run.started',
        ]);

        $this->assertDatabaseHas('telemetry_ledger_mutation_attempts', [
            'operation' => 'DELETE',
            'table_name' => 'telemetry_event_ledger',
            'row_identifier' => (string) $id,
        ]);
    }

    public function test_schema_comment_documents_append_only_contract(): void
    {
        $comment = DB::selectOne(<<<'SQL'
            SELECT obj_description('public.telemetry_event_ledger'::regclass, 'pg_class') AS comment
        SQL);

        $this->assertNotNull($comment);
        $this->assertIsString($comment->comment);
        $this->assertStringContainsString('append-only', strtolower($comment->comment));
        $this->assertStringContainsString('update and delete are prohibited', strtolower($comment->comment));
    }

    public function test_public_role_does_not_have_update_or_delete_grants(): void
    {
        $grants = DB::table('information_schema.role_table_grants')
            ->where('table_schema', 'public')
            ->where('table_name', 'telemetry_event_ledger')
            ->where('grantee', 'PUBLIC')
            ->pluck('privilege_type')
            ->map(static fn (mixed $privilege): string => strtoupper((string) $privilege));

        $this->assertFalse($grants->contains('UPDATE'));
        $this->assertFalse($grants->contains('DELETE'));
    }

    private function insertLedgerRow(): int
    {
        return (int) DB::table('telemetry_event_ledger')->insertGetId([
            'event_id' => 'event-'.(string) str()->uuid(),
            'run_attempt_id' => 'attempt-'.(string) str()->uuid(),
            'workflow_key' => 'eng.repo-analysis.v1',
            'event_type' => 'run.started',
            'payload_json' => json_encode(['state' => 'starting'], JSON_THROW_ON_ERROR),
            'ingested_at' => now(),
            'created_at' => now(),
        ]);
    }
}
