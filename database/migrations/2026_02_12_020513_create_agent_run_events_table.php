<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_job_run_id')->constrained('agent_job_runs')->cascadeOnDelete();
            $table->string('event_type', 16);
            $table->unsignedBigInteger('sequence');
            $table->text('payload');
            $table->timestampTz('event_ts', 3);
            $table->timestamps();

            $table->unique(['agent_job_run_id', 'sequence'], 'agent_run_events_run_sequence_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_run_events');
    }
};
