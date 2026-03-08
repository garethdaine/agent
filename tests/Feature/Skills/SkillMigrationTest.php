<?php

declare(strict_types=1);

namespace Tests\Feature\Skills;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_skills_table_exists_with_all_columns(): void
    {
        $this->assertTrue(Schema::hasTable('agent_skills'));

        $expectedColumns = [
            'id', 'team_id', 'name', 'slug', 'description', 'version', 'author',
            'risk_level', 'status', 'industries', 'memory_blocks', 'mcp_dependencies',
            'tools_required', 'trigger_keywords', 'run_after', 'requires_approval',
            'skill_path', 'checksum', 'file_hashes', 'validation_result', 'is_global',
            'installed_by', 'installed_at', 'updated_by', 'updated_at',
            'paused_by', 'paused_at', 'last_invoked_at', 'invocation_count',
        ];

        $this->assertTrue(
            Schema::hasColumns('agent_skills', $expectedColumns),
            'agent_skills table should have all expected columns'
        );
    }

    public function test_agent_skills_unique_team_slug_constraint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $baseData = [
            'id' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'description' => 'A test skill for validation',
            'version' => '1.0.0',
            'author' => 'test-author',
            'risk_level' => 'standard',
            'status' => 'active',
            'industries' => '[]',
            'memory_blocks' => '[]',
            'mcp_dependencies' => '[]',
            'tools_required' => '[]',
            'trigger_keywords' => '[]',
            'run_after' => '[]',
            'requires_approval' => false,
            'skill_path' => '/tmp/skills/test-skill',
            'checksum' => hash('sha256', 'test'),
            'file_hashes' => '{}',
            'validation_result' => '{}',
            'is_global' => false,
            'installed_at' => now(),
            'updated_at' => now(),
            'invocation_count' => 0,
        ];

        DB::table('agent_skills')->insert($baseData);

        $this->expectException(QueryException::class);

        $baseData['id'] = Str::uuid()->toString();
        DB::table('agent_skills')->insert($baseData);
    }

    public function test_agent_skill_validations_table_exists_with_all_columns(): void
    {
        $this->assertTrue(Schema::hasTable('agent_skill_validations'));

        $expectedColumns = [
            'id', 'skill_name', 'team_id', 'validation_result',
            'risk_score', 'overall_pass', 'source', 'validated_by', 'created_at',
        ];

        $this->assertTrue(
            Schema::hasColumns('agent_skill_validations', $expectedColumns),
            'agent_skill_validations table should have all expected columns'
        );
    }

    public function test_gin_indexes_exist_on_jsonb_columns(): void
    {
        $indexes = $this->getTableIndexes('agent_skills');

        $this->assertContains(
            'idx_skills_team_industry',
            $indexes,
            'GIN index idx_skills_team_industry should exist on industries column'
        );

        $this->assertContains(
            'idx_skills_trigger_keywords',
            $indexes,
            'GIN index idx_skills_trigger_keywords should exist on trigger_keywords column'
        );
    }

    public function test_team_status_index_exists(): void
    {
        $indexes = $this->getTableIndexes('agent_skills');

        $this->assertContains(
            'idx_skills_team_status',
            $indexes,
            'Index idx_skills_team_status should exist'
        );
    }

    public function test_skill_validations_name_index_exists(): void
    {
        $indexes = $this->getTableIndexes('agent_skill_validations');

        $this->assertContains(
            'idx_skill_validations_name',
            $indexes,
            'Index idx_skill_validations_name should exist'
        );
    }

    /**
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
