<?php

declare(strict_types=1);

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
        Schema::create('agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('workflow_key', 160);
            $table->text('description')->nullable();
            $table->string('cron_expression', 100);
            $table->string('timezone', 64);
            $table->json('active_hours_config')->nullable();
            $table->boolean('star_preamble_enabled')->nullable();
            $table->boolean('targeted_retry_enabled')->nullable();
            $table->unsignedTinyInteger('max_retries')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('max_runtime_seconds');
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->string('runner_type', 16);
            $table->string('command_template', 2000);
            $table->string('task_markdown_path', 1024);
            $table->string('working_directory', 1024);
            $table->json('env_json')->nullable();
            $table->string('last_validated_executable_path', 1024)->nullable();
            $table->unsignedSmallInteger('scheduled_path_failure_streak')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_enabled', 'deleted_at'], 'agent_jobs_user_enabled_deleted_idx');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (! Schema::hasColumn('agent_jobs', 'active_name_for_uniqueness')) {
                Schema::table('agent_jobs', function (Blueprint $table): void {
                    $table->string('active_name_for_uniqueness', 120)
                        ->nullable()
                        ->storedAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
                });
            }

            DB::statement('CREATE UNIQUE INDEX agent_jobs_user_active_name_unique ON agent_jobs (user_id, active_name_for_uniqueness)');
        }

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agent_jobs_user_name_active_unique ON agent_jobs (user_id, name) WHERE deleted_at IS NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE agent_jobs ADD CONSTRAINT agent_jobs_runner_type_check CHECK (runner_type IN ('claude','codex','custom'))");

            $regex = str_replace("'", "''", (string) config('agent.workflow_key.regex', WorkflowKey::REGEX));

            DB::statement('CREATE INDEX IF NOT EXISTS agent_jobs_user_workflow_key_idx ON agent_jobs (user_id, workflow_key)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agent_jobs_user_workflow_key_active_unique ON agent_jobs (user_id, workflow_key) WHERE deleted_at IS NULL');
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
        Schema::dropIfExists('agent_jobs');
    }
};
