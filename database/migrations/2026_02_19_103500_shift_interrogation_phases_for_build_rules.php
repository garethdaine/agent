<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('interrogation_sessions')
            ->where('phase', '>=', 5)
            ->increment('phase');
    }

    public function down(): void
    {
        DB::table('interrogation_sessions')
            ->where('phase', '>=', 6)
            ->decrement('phase');
    }
};
