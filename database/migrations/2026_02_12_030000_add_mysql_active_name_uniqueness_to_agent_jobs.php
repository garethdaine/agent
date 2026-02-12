<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE agent_jobs DROP INDEX agent_jobs_user_name_deleted_unique');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS agent_jobs_user_name_deleted_unique');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS agent_jobs_user_name_deleted_unique');
        } else {
            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('agent_jobs', 'active_name_for_uniqueness')) {
            Schema::table('agent_jobs', function (Blueprint $table): void {
                $table->string('active_name_for_uniqueness', 120)
                    ->nullable()
                    ->storedAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
            });
        }

        DB::statement('CREATE UNIQUE INDEX agent_jobs_user_active_name_unique ON agent_jobs (user_id, active_name_for_uniqueness)');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX agent_jobs_user_active_name_unique ON agent_jobs');

            if (Schema::hasColumn('agent_jobs', 'active_name_for_uniqueness')) {
                Schema::table('agent_jobs', function (Blueprint $table): void {
                    $table->dropColumn('active_name_for_uniqueness');
                });
            }

            DB::statement('CREATE UNIQUE INDEX agent_jobs_user_name_deleted_unique ON agent_jobs (user_id, name, deleted_at)');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agent_jobs ADD CONSTRAINT agent_jobs_user_name_deleted_unique UNIQUE (user_id, name, deleted_at)');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agent_jobs_user_name_deleted_unique ON agent_jobs (user_id, name, deleted_at)');

            return;
        }
    }
};
