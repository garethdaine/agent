<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE agent_audit_logs
            ALTER COLUMN actor_id TYPE VARCHAR(64)
            USING actor_id::varchar
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE agent_audit_logs
            ALTER COLUMN actor_id TYPE BIGINT
            USING (
                CASE
                    WHEN actor_id ~ '^[0-9]+$' THEN actor_id::bigint
                    ELSE NULL
                END
            )
        ");
    }
};
