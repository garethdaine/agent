<?php

declare(strict_types=1);

namespace Tests\Feature\RepoAnalysis;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Assumptions for Session 16 Task 2:
 * - Migrations are additive-only.
 * - Existing interrogation tables remain untouched.
 */
class RepoAnalysisSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_repo_analysis_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('repo_analysis_sessions'));
        $this->assertTrue(Schema::hasColumns('repo_analysis_sessions', [
            'id',
            'user_id',
            'name',
            'project_directory',
            'analyzer_profile',
            'runner_type',
            'status',
            'phase',
            'snapshot_hash',
            'manifest_stats_json',
            'report_summary_json',
            'metadata_json',
            'error_code',
            'error_summary',
            'started_at',
            'finished_at',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('repo_analysis_events'));
        $this->assertTrue(Schema::hasColumns('repo_analysis_events', [
            'id',
            'repo_analysis_session_id',
            'sequence',
            'event_type',
            'payload_json',
            'event_ts',
            'phase',
            'status',
            'error_code',
            'error_summary',
            'metadata_json',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('repo_analysis_tasks'));
        $this->assertTrue(Schema::hasColumns('repo_analysis_tasks', [
            'id',
            'repo_analysis_session_id',
            'task_key',
            'task_type',
            'status',
            'phase',
            'depends_on_json',
            'artifact_ids_json',
            'input_hash',
            'output_hash',
            'analyzer_name',
            'analyzer_version',
            'attempt_count',
            'max_attempts',
            'error_code',
            'error_summary',
            'metadata_json',
            'started_at',
            'finished_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('repo_analysis_artifacts'));
        $this->assertTrue(Schema::hasColumns('repo_analysis_artifacts', [
            'id',
            'repo_analysis_session_id',
            'repo_analysis_task_id',
            'artifact_type',
            'artifact_key',
            'content_hash',
            'schema_version',
            'analyzer_version',
            'storage_disk',
            'storage_path',
            'payload_json',
            'metadata_json',
            'error_code',
            'error_summary',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('repo_analysis_reports'));
        $this->assertTrue(Schema::hasColumns('repo_analysis_reports', [
            'id',
            'repo_analysis_session_id',
            'report_version',
            'report_hash',
            'status',
            'payload_json',
            'metadata_json',
            'markdown_export_path',
            'json_export_path',
            'error_code',
            'error_summary',
            'generated_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_interrogation_tables_remain_untouched(): void
    {
        $this->assertTrue(Schema::hasTable('interrogation_sessions'));
        $this->assertTrue(Schema::hasColumns('interrogation_sessions', [
            'id',
            'user_id',
            'status',
            'phase',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('interrogation_events'));
        $this->assertTrue(Schema::hasColumns('interrogation_events', [
            'id',
            'interrogation_session_id',
            'sequence',
            'event_type',
            'payload',
        ]));
    }

    public function test_events_enforce_unique_session_sequence(): void
    {
        $sessionId = $this->createSession();

        DB::table('repo_analysis_events')->insert([
            'repo_analysis_session_id' => $sessionId,
            'sequence' => 1,
            'event_type' => 'phase_transition',
            'payload_json' => '{}',
            'event_ts' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('repo_analysis_events')->insert([
            'repo_analysis_session_id' => $sessionId,
            'sequence' => 1,
            'event_type' => 'phase_transition',
            'payload_json' => '{}',
            'event_ts' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tasks_enforce_unique_session_task_key(): void
    {
        $sessionId = $this->createSession();

        DB::table('repo_analysis_tasks')->insert([
            'repo_analysis_session_id' => $sessionId,
            'task_key' => 'filesystem_manifest_analyzer',
            'task_type' => 'analyzer',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('repo_analysis_tasks')->insert([
            'repo_analysis_session_id' => $sessionId,
            'task_key' => 'filesystem_manifest_analyzer',
            'task_type' => 'analyzer',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_artifacts_enforce_unique_session_artifact_key(): void
    {
        $sessionId = $this->createSession();

        DB::table('repo_analysis_artifacts')->insert([
            'repo_analysis_session_id' => $sessionId,
            'artifact_type' => 'manifest',
            'artifact_key' => 'snapshot_manifest',
            'content_hash' => hash('sha256', 'manifest-a'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('repo_analysis_artifacts')->insert([
            'repo_analysis_session_id' => $sessionId,
            'artifact_type' => 'manifest',
            'artifact_key' => 'snapshot_manifest',
            'content_hash' => hash('sha256', 'manifest-b'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_session_delete_cascades_to_events_tasks_artifacts_and_reports(): void
    {
        $sessionId = $this->createSession();

        $taskId = DB::table('repo_analysis_tasks')->insertGetId([
            'repo_analysis_session_id' => $sessionId,
            'task_key' => 'routes_analyzer',
            'task_type' => 'analyzer',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('repo_analysis_events')->insert([
            'repo_analysis_session_id' => $sessionId,
            'sequence' => 1,
            'event_type' => 'task_completed',
            'payload_json' => '{}',
            'event_ts' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('repo_analysis_artifacts')->insert([
            'repo_analysis_session_id' => $sessionId,
            'repo_analysis_task_id' => $taskId,
            'artifact_type' => 'routes',
            'artifact_key' => 'routes_json',
            'content_hash' => hash('sha256', 'routes-json'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('repo_analysis_reports')->insert([
            'repo_analysis_session_id' => $sessionId,
            'report_version' => 'v1',
            'report_hash' => hash('sha256', 'report-v1'),
            'status' => 'generated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('repo_analysis_sessions')->where('id', $sessionId)->delete();

        $this->assertDatabaseMissing('repo_analysis_events', ['repo_analysis_session_id' => $sessionId]);
        $this->assertDatabaseMissing('repo_analysis_tasks', ['repo_analysis_session_id' => $sessionId]);
        $this->assertDatabaseMissing('repo_analysis_artifacts', ['repo_analysis_session_id' => $sessionId]);
        $this->assertDatabaseMissing('repo_analysis_reports', ['repo_analysis_session_id' => $sessionId]);
    }

    public function test_error_columns_are_nullable_and_status_phase_indexes_exist(): void
    {
        $this->assertColumnNullable('repo_analysis_sessions', 'error_code');
        $this->assertColumnNullable('repo_analysis_sessions', 'error_summary');
        $this->assertColumnNullable('repo_analysis_events', 'error_code');
        $this->assertColumnNullable('repo_analysis_events', 'error_summary');
        $this->assertColumnNullable('repo_analysis_tasks', 'error_code');
        $this->assertColumnNullable('repo_analysis_tasks', 'error_summary');
        $this->assertColumnNullable('repo_analysis_artifacts', 'error_code');
        $this->assertColumnNullable('repo_analysis_artifacts', 'error_summary');
        $this->assertColumnNullable('repo_analysis_reports', 'error_code');
        $this->assertColumnNullable('repo_analysis_reports', 'error_summary');

        $sessionIndexes = $this->getTableIndexes('repo_analysis_sessions');
        $taskIndexes = $this->getTableIndexes('repo_analysis_tasks');

        $this->assertContains('repo_analysis_sessions_status_phase_idx', $sessionIndexes);
        $this->assertContains('repo_analysis_tasks_status_phase_idx', $taskIndexes);
    }

    private function createSession(): int
    {
        $user = User::factory()->create();

        return (int) DB::table('repo_analysis_sessions')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Schema Test Session',
            'project_directory' => base_path(),
            'analyzer_profile' => 'default',
            'status' => 'setup',
            'phase' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertColumnNullable(string $table, string $column): void
    {
        $result = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
            [$table, $column],
        );

        $this->assertNotNull($result, "Column {$table}.{$column} should exist");
        $this->assertSame('YES', $result->is_nullable, "Column {$table}.{$column} should be nullable");
    }

    /**
     * @return array<string>
     */
    private function getTableIndexes(string $table): array
    {
        $results = DB::select(
            'SELECT indexname FROM pg_indexes WHERE tablename = ?',
            [$table],
        );

        return array_map(static fn (object $row): string => (string) $row->indexname, $results);
    }
}
