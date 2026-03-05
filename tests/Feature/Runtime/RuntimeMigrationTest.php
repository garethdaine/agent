<?php

namespace Tests\Feature\Runtime;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_sessions_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_sessions'));
        $this->assertTrue(Schema::hasColumns('runtime_sessions', [
            'id', 'chat_session_id', 'user_id', 'status', 'mode',
            'title', 'workspace_root', 'browser_persistence_mode',
            'started_at', 'ended_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_runtime_turns_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_turns'));
        $this->assertTrue(Schema::hasColumns('runtime_turns', [
            'id', 'runtime_session_id', 'sequence',
            'input_message_id', 'output_message_id', 'status', 'summary',
        ]));
    }

    public function test_runtime_tool_calls_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_tool_calls'));
        $this->assertTrue(Schema::hasColumns('runtime_tool_calls', [
            'id', 'runtime_turn_id', 'tool_name', 'arguments_json',
            'result_json', 'status', 'duration_ms', 'requires_approval', 'approved_at',
        ]));
    }

    public function test_runtime_approvals_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_approvals'));
        $this->assertTrue(Schema::hasColumns('runtime_approvals', [
            'id', 'runtime_tool_call_id', 'requested_by', 'state',
            'decision_by', 'decision_reason', 'expires_at',
        ]));
    }

    public function test_runtime_artifacts_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_artifacts'));
    }

    public function test_runtime_policy_snapshots_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('runtime_policy_snapshots'));
    }
}
