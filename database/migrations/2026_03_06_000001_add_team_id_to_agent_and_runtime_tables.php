<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('agent_job_runs', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('agent_job_id')->constrained()->nullOnDelete();
        });

        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('agent_job_runs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
    }
};
