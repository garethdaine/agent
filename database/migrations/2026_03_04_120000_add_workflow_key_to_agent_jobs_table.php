<?php

use App\Support\Agent\WorkflowKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORMAT_CHECK_CONSTRAINT = 'agent_jobs_workflow_key_format_check';

    private const STRUCTURE_CHECK_CONSTRAINT = 'agent_jobs_workflow_key_structure_check';

    public function up(): void
    {
        if (! Schema::hasColumn('agent_jobs', 'workflow_key')) {
            Schema::table('agent_jobs', function (Blueprint $table): void {
                $table->string('workflow_key', 160)->nullable()->after('name');
            });
        }

        DB::table('agent_jobs')
            ->select(['id', 'name', 'workflow_key'])
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $current = is_string($row->workflow_key) ? trim($row->workflow_key) : null;

                    if (WorkflowKey::isValid($current)) {
                        continue;
                    }

                    DB::table('agent_jobs')
                        ->where('id', $row->id)
                        ->update([
                            'workflow_key' => WorkflowKey::deriveFromName((string) $row->name, (int) $row->id),
                        ]);
                }
            });

        if (DB::getDriverName() === 'pgsql') {
            $regex = str_replace("'", "''", (string) config('agent.workflow_key.regex', WorkflowKey::REGEX));

            DB::statement('CREATE INDEX IF NOT EXISTS agent_jobs_user_workflow_key_idx ON agent_jobs (user_id, workflow_key)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agent_jobs_user_workflow_key_active_unique ON agent_jobs (user_id, workflow_key) WHERE deleted_at IS NULL');
            DB::statement('ALTER TABLE agent_jobs ALTER COLUMN workflow_key SET NOT NULL');
            DB::statement(sprintf(
                'ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS %s',
                self::FORMAT_CHECK_CONSTRAINT
            ));
            DB::statement(sprintf(
                'ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS %s',
                self::STRUCTURE_CHECK_CONSTRAINT
            ));
            DB::statement(sprintf(
                "ALTER TABLE agent_jobs ADD CONSTRAINT %s CHECK (workflow_key ~ '%s')",
                self::FORMAT_CHECK_CONSTRAINT,
                $regex
            ));
            DB::statement(sprintf(
                "ALTER TABLE agent_jobs ADD CONSTRAINT %s CHECK (workflow_key !~ '[._-]{2,}' AND workflow_key !~ '^[._-]' AND workflow_key !~ '[._-][.]v[1-9][0-9]*$')",
                self::STRUCTURE_CHECK_CONSTRAINT
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS %s',
                self::FORMAT_CHECK_CONSTRAINT
            ));
            DB::statement(sprintf(
                'ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS %s',
                self::STRUCTURE_CHECK_CONSTRAINT
            ));
            DB::statement('DROP INDEX IF EXISTS agent_jobs_user_workflow_key_active_unique');
            DB::statement('DROP INDEX IF EXISTS agent_jobs_user_workflow_key_idx');
        }

        if (Schema::hasColumn('agent_jobs', 'workflow_key')) {
            Schema::table('agent_jobs', function (Blueprint $table): void {
                $table->dropColumn('workflow_key');
            });
        }
    }
};
