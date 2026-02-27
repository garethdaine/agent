<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_run_events', function (Blueprint $table) {
            $table->string('reasoning_step', 16)->nullable()->after('payload');
            $table->index(['agent_job_run_id', 'reasoning_step'], 'agent_run_events_run_reasoning_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_run_events', function (Blueprint $table) {
            $table->dropIndex('agent_run_events_run_reasoning_idx');
            $table->dropColumn('reasoning_step');
        });
    }
};
