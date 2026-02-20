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
        Schema::create('delegation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_graph_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('sequence_order');
            $table->json('contract_json');
            $table->foreignId('assigned_delegatee_profile_id')
                ->nullable()
                ->constrained('delegatee_profiles')
                ->nullOnDelete();
            $table->json('assignment_reason_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->index(
                ['delegation_graph_id', 'status'],
                'delegation_tasks_graph_status_idx'
            );
            $table->index(
                ['assigned_delegatee_profile_id'],
                'delegation_tasks_assigned_profile_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_tasks');
    }
};
