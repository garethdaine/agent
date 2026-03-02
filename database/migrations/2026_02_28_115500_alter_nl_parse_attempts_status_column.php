<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expands the status column to accommodate 'clarification_required' status
     * which is 22 characters (previously limited to 20).
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE nl_parse_attempts
            ALTER COLUMN status TYPE VARCHAR(30)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE nl_parse_attempts
            ALTER COLUMN status TYPE VARCHAR(20)
        ');
    }
};
