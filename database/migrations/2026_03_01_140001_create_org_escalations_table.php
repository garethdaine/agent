<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_escalations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ritual_run_id')->constrained('org_ritual_runs')->cascadeOnDelete();
            $table->string('escalation_type', 50); // budget, verification, risk, approval
            $table->foreignUuid('escalated_to_agent_id')->constrained('org_agent_profiles')->cascadeOnDelete();
            $table->string('state', 50)->default('pending'); // pending, approved, rejected, timed_out
            $table->timestamp('timeout_at')->nullable();
            $table->string('resolved_by')->nullable(); // actor identifier
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            // Indexes for query patterns
            $table->index(['state', 'timeout_at']);
            $table->index(['ritual_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_escalations');
    }
};
