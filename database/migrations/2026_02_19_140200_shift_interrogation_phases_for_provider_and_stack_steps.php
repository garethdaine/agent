<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('interrogation_sessions')
            ->where('phase', '>=', 1)
            ->increment('phase', 2);
    }

    public function down(): void
    {
        DB::table('interrogation_sessions')
            ->where('phase', '>=', 3)
            ->decrement('phase', 2);
    }
};
