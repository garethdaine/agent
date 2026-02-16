<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interrogation_sessions', function (Blueprint $table): void {
            $table->timestampTz('approved_at')->nullable()->after('finished_at');
            $table->index(['user_id', 'approved_at'], 'interr_sessions_user_approved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('interrogation_sessions', function (Blueprint $table): void {
            $table->dropIndex('interr_sessions_user_approved_idx');
            $table->dropColumn('approved_at');
        });
    }
};
