<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NlScheduleMigrationsTest extends TestCase
{
    public function test_agent_jobs_has_active_hours_config_column(): void
    {
        $this->assertTrue(Schema::hasColumn('agent_jobs', 'active_hours_config'));
    }

    public function test_nl_parse_attempts_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('nl_parse_attempts'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'id'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'user_id'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'input_text'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'timezone'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'parser_path'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'confidence'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'status'));
        $this->assertTrue(Schema::hasColumn('nl_parse_attempts', 'user_confirmed'));
    }
}
