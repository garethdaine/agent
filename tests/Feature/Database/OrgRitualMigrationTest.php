<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrgRitualMigrationTest extends TestCase
{
    public function test_org_ritual_templates_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('org_ritual_templates'));
        $this->assertTrue(Schema::hasColumns('org_ritual_templates', [
            'id', 'user_id', 'name', 'description', 'cron_expression',
            'timezone', 'phase_graph', 'phase_role_mappings', 'context_inputs',
            'verification_strategy', 'delivery_targets', 'escalation_timeout_seconds',
            'notification_level', 'is_paused', 'archived_at',
        ]));
    }

    public function test_org_ritual_runs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('org_ritual_runs'));
        $this->assertTrue(Schema::hasColumns('org_ritual_runs', [
            'id', 'ritual_template_id', 'user_id', 'state',
            'delegation_graph_id', 'phase_outputs', 'started_at',
            'completed_at', 'correlation_id',
        ]));
    }
}
