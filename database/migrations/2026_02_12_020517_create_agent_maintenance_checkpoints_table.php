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
        Schema::create('agent_maintenance_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 32);
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('last_processed_id')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->json('progress_json')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->index(['domain', 'status'], 'agent_maintenance_domain_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_maintenance_checkpoints');
    }
};
