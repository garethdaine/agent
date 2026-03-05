<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for memory system database migrations.
 *
 * Verifies:
 * - All 7 memory tables exist after migration
 * - Foreign keys cascade on user deletion
 * - content_hash index exists on memory_embeddings
 * - tsvector indexes exist for BM25 search
 */
class MemoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memory tables that should exist after migrations.
     *
     * @var array<string>
     */
    private array $memoryTables = [
        'memory_settings',
        'memory_core_blocks',
        'memory_embeddings',
        'memory_conversation_logs',
        'memory_consolidation_log',
        'memory_formation_failures',
        'memory_provider_usage',
    ];

    /**
     * Test that all 7 memory tables exist after migration.
     */
    public function test_all_memory_tables_exist(): void
    {
        foreach ($this->memoryTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Table '{$table}' should exist"
            );
        }
    }

    /**
     * Test that memory_settings has correct columns.
     */
    public function test_memory_settings_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_settings', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_settings', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_settings', 'key'));
        $this->assertTrue(Schema::hasColumn('memory_settings', 'value'));
        $this->assertTrue(Schema::hasColumn('memory_settings', 'created_at'));
        $this->assertTrue(Schema::hasColumn('memory_settings', 'updated_at'));
    }

    /**
     * Test that memory_core_blocks has correct columns.
     */
    public function test_memory_core_blocks_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'job_id'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'block_type'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'block_key'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'content_text'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'content_json'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'classification'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'version'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'created_at'));
        $this->assertTrue(Schema::hasColumn('memory_core_blocks', 'updated_at'));
    }

    /**
     * Test that memory_embeddings has correct columns.
     */
    public function test_memory_embeddings_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'source_type'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'source_id'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'content'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'content_hash'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'metadata_json'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'classification'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'importance_score'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'access_count'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'last_accessed_at'));
        $this->assertTrue(Schema::hasColumn('memory_embeddings', 'created_at'));
    }

    /**
     * Test that memory_conversation_logs has correct columns.
     */
    public function test_memory_conversation_logs_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'run_id'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'job_id'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'runtime_session_id'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'role'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'content'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'sequence'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'event_type'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'classification'));
        $this->assertTrue(Schema::hasColumn('memory_conversation_logs', 'created_at'));
    }

    /**
     * Test that memory_consolidation_log has correct columns.
     */
    public function test_memory_consolidation_log_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'consolidation_type'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'source_count'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'result_summary'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'checkpoint_json'));
        $this->assertTrue(Schema::hasColumn('memory_consolidation_log', 'created_at'));
    }

    /**
     * Test that memory_formation_failures has correct columns.
     */
    public function test_memory_formation_failures_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'run_id'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'failure_type'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'error_message'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'attempts'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'backfilled_at'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'payload_json'));
        $this->assertTrue(Schema::hasColumn('memory_formation_failures', 'created_at'));
    }

    /**
     * Test that memory_provider_usage has correct columns.
     */
    public function test_memory_provider_usage_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'id'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'user_id'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'run_id'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'provider'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'model'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'operation'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'input_tokens'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'output_tokens'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'cost_estimate_usd'));
        $this->assertTrue(Schema::hasColumn('memory_provider_usage', 'created_at'));
    }

    /**
     * Test that foreign keys cascade on user deletion for memory_settings.
     */
    public function test_memory_settings_cascade_on_user_delete(): void
    {
        $user = User::factory()->create();

        DB::table('memory_settings')->insert([
            'user_id' => $user->id,
            'key' => 'test_setting',
            'value' => 'test_value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_settings', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('memory_settings', ['user_id' => $user->id]);
    }

    /**
     * Test that foreign keys cascade on user deletion for memory_core_blocks.
     */
    public function test_memory_core_blocks_cascade_on_user_delete(): void
    {
        $user = User::factory()->create();

        DB::table('memory_core_blocks')->insert([
            'user_id' => $user->id,
            'block_type' => 'identity',
            'block_key' => 'agent_persona',
            'content_text' => 'Test persona',
            'classification' => 'internal',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_core_blocks', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('memory_core_blocks', ['user_id' => $user->id]);
    }

    /**
     * Test that foreign keys cascade on user deletion for memory_embeddings.
     */
    public function test_memory_embeddings_cascade_on_user_delete(): void
    {
        $user = User::factory()->create();

        DB::table('memory_embeddings')->insert([
            'user_id' => $user->id,
            'source_type' => 'conversation',
            'source_id' => 'test-123',
            'content' => 'Test embedding content',
            'content_hash' => hash('sha256', 'Test embedding content'),
            'classification' => 'internal',
            'importance_score' => 0.5,
            'access_count' => 0,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_embeddings', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('memory_embeddings', ['user_id' => $user->id]);
    }

    /**
     * Test that foreign keys cascade on user deletion for memory_provider_usage.
     */
    public function test_memory_provider_usage_cascade_on_user_delete(): void
    {
        $user = User::factory()->create();

        DB::table('memory_provider_usage')->insert([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'operation' => 'extraction',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost_estimate_usd' => 0.001,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_provider_usage', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('memory_provider_usage', ['user_id' => $user->id]);
    }

    /**
     * Test that content_hash index exists on memory_embeddings.
     */
    public function test_memory_embeddings_has_content_hash_index(): void
    {
        $indexes = $this->getTableIndexes('memory_embeddings');

        $this->assertContains(
            'memory_embeddings_content_hash_index',
            $indexes,
            'Index memory_embeddings_content_hash_index should exist'
        );
    }

    /**
     * Test that tsvector index exists on memory_embeddings for BM25 search.
     */
    public function test_memory_embeddings_has_tsvector_index(): void
    {
        $indexes = $this->getTableIndexes('memory_embeddings');

        $this->assertContains(
            'memory_embeddings_content_fts',
            $indexes,
            'FTS index memory_embeddings_content_fts should exist'
        );
    }

    /**
     * Test that tsvector index exists on memory_conversation_logs for BM25 search.
     */
    public function test_memory_conversation_logs_has_tsvector_index(): void
    {
        $indexes = $this->getTableIndexes('memory_conversation_logs');

        $this->assertContains(
            'memory_conversation_logs_content_fts',
            $indexes,
            'FTS index memory_conversation_logs_content_fts should exist'
        );
    }

    /**
     * Test that memory_formation_failures has pending index.
     */
    public function test_memory_formation_failures_has_pending_index(): void
    {
        $indexes = $this->getTableIndexes('memory_formation_failures');

        $this->assertContains(
            'memory_formation_failures_pending',
            $indexes,
            'Index memory_formation_failures_pending should exist'
        );
    }

    /**
     * Test that memory_settings has unique constraint on user_id + key.
     */
    public function test_memory_settings_has_unique_user_key_constraint(): void
    {
        $user = User::factory()->create();

        DB::table('memory_settings')->insert([
            'user_id' => $user->id,
            'key' => 'test_key',
            'value' => 'value1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('memory_settings')->insert([
            'user_id' => $user->id,
            'key' => 'test_key',
            'value' => 'value2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test that memory_core_blocks has unique constraint on user_id + block_key + job_id.
     */
    public function test_memory_core_blocks_has_unique_user_key_job_constraint(): void
    {
        $user = User::factory()->create();

        DB::table('memory_core_blocks')->insert([
            'user_id' => $user->id,
            'job_id' => null,
            'block_type' => 'identity',
            'block_key' => 'agent_persona',
            'content_text' => 'Persona 1',
            'classification' => 'internal',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('memory_core_blocks')->insert([
            'user_id' => $user->id,
            'job_id' => null,
            'block_type' => 'identity',
            'block_key' => 'agent_persona',
            'content_text' => 'Persona 2',
            'classification' => 'internal',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test that user_id in memory_settings can be nullable for global settings.
     */
    public function test_memory_settings_allows_nullable_user_id(): void
    {
        DB::table('memory_settings')->insert([
            'user_id' => null,
            'key' => 'global_setting',
            'value' => 'global_value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_settings', [
            'user_id' => null,
            'key' => 'global_setting',
        ]);
    }

    /**
     * Test that memory_consolidation_log allows nullable user_id for system-wide consolidations.
     */
    public function test_memory_consolidation_log_allows_nullable_user_id(): void
    {
        DB::table('memory_consolidation_log')->insert([
            'user_id' => null,
            'consolidation_type' => 'vector_dedup',
            'source_count' => 100,
            'result_summary' => 'Deduplicated 10 vectors',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('memory_consolidation_log', [
            'user_id' => null,
            'consolidation_type' => 'vector_dedup',
        ]);
    }

    /**
     * Get index names for a table.
     *
     * @return array<string>
     */
    private function getTableIndexes(string $table): array
    {
        $results = DB::select('
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = ?
        ', [$table]);

        return array_map(fn ($row) => $row->indexname, $results);
    }
}
