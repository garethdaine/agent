<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_detection_rules_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('security_detection_rules'));

        $expectedColumns = [
            'id', 'pattern', 'pattern_type', 'severity', 'weight',
            'enabled', 'created_at', 'updated_at',
        ];

        $this->assertTrue(
            Schema::hasColumns('security_detection_rules', $expectedColumns),
            'security_detection_rules table should have all expected columns'
        );
    }

    public function test_security_events_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('security_events'));

        $expectedColumns = [
            'id', 'runtime_session_id', 'runtime_tool_call_id',
            'event_type', 'severity', 'details_json', 'created_at',
        ];

        $this->assertTrue(
            Schema::hasColumns('security_events', $expectedColumns),
            'security_events table should have all expected columns'
        );
    }

    public function test_security_events_has_expected_indexes(): void
    {
        $indexes = $this->getTableIndexes('security_events');

        $this->assertTrue(
            $this->hasCompositeIndex($indexes, ['runtime_session_id', 'created_at']),
            'security_events should have index on (runtime_session_id, created_at)'
        );

        $this->assertTrue(
            $this->hasCompositeIndex($indexes, ['event_type', 'created_at']),
            'security_events should have index on (event_type, created_at)'
        );
    }

    public function test_account_security_overrides_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('account_security_overrides'));

        $expectedColumns = [
            'id', 'account_id', 'config_key', 'config_value',
            'created_by', 'created_at', 'updated_at',
        ];

        $this->assertTrue(
            Schema::hasColumns('account_security_overrides', $expectedColumns),
            'account_security_overrides table should have all expected columns'
        );
    }

    public function test_account_security_overrides_has_unique_constraint(): void
    {
        $indexes = $this->getTableIndexes('account_security_overrides');

        $this->assertTrue(
            $this->hasUniqueIndex('account_security_overrides', ['account_id', 'config_key']),
            'account_security_overrides should have unique constraint on (account_id, config_key)'
        );
    }

    public function test_runtime_tool_calls_has_security_columns(): void
    {
        $expectedColumns = [
            'content_trust_level', 'injection_score',
            'injection_action', 'content_sanitized',
        ];

        $this->assertTrue(
            Schema::hasColumns('runtime_tool_calls', $expectedColumns),
            'runtime_tool_calls should have security columns'
        );
    }

    public function test_runtime_tool_calls_has_content_trust_level_index(): void
    {
        $indexes = $this->getTableIndexes('runtime_tool_calls');

        $this->assertTrue(
            $this->hasColumnInIndex($indexes, 'content_trust_level'),
            'runtime_tool_calls should have index on content_trust_level'
        );
    }

    public function test_runtime_sessions_has_security_columns(): void
    {
        $expectedColumns = ['security_config_json', 'file_provenance'];

        $this->assertTrue(
            Schema::hasColumns('runtime_sessions', $expectedColumns),
            'runtime_sessions should have security columns'
        );
    }

    public function test_security_detection_rules_has_enabled_pattern_type_index(): void
    {
        $indexes = $this->getTableIndexes('security_detection_rules');

        $this->assertTrue(
            $this->hasCompositeIndex($indexes, ['enabled', 'pattern_type']),
            'security_detection_rules should have index on (enabled, pattern_type)'
        );
    }

    public function test_seeded_rules_count_at_least_fifty(): void
    {
        $count = DB::table('security_detection_rules')->count();

        $this->assertGreaterThanOrEqual(50, $count, 'Should have at least 50 seeded detection rules');
    }

    /**
     * @return array<int, object{indexname: string, indexdef: string}>
     */
    private function getTableIndexes(string $table): array
    {
        return DB::select('
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = ?
        ', [$table]);
    }

    private function hasCompositeIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            $def = $index->indexdef;
            $allFound = true;
            foreach ($columns as $col) {
                if (! str_contains($def, $col)) {
                    $allFound = false;
                    break;
                }
            }
            if ($allFound) {
                return true;
            }
        }

        return false;
    }

    private function hasColumnInIndex(array $indexes, string $column): bool
    {
        foreach ($indexes as $index) {
            if (str_contains($index->indexdef, $column)) {
                return true;
            }
        }

        return false;
    }

    private function hasUniqueIndex(string $table, array $columns): bool
    {
        $indexes = $this->getTableIndexes($table);

        foreach ($indexes as $index) {
            if (str_contains($index->indexdef, 'UNIQUE')) {
                $allFound = true;
                foreach ($columns as $col) {
                    if (! str_contains($index->indexdef, $col)) {
                        $allFound = false;
                        break;
                    }
                }
                if ($allFound) {
                    return true;
                }
            }
        }

        return false;
    }
}
