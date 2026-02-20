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
        Schema::create('delegation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_graph_id')->constrained('delegation_graphs')->cascadeOnDelete();
            $table->foreignId('delegation_task_id')->nullable()->constrained('delegation_tasks')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->unsignedInteger('sequence');
            $table->json('payload_json')->nullable();
            $table->timestampTz('event_ts');
            $table->timestamps();

            $table->index(
                ['delegation_graph_id', 'sequence'],
                'delegation_events_graph_sequence_idx'
            );
            $table->index(
                ['delegation_task_id', 'event_ts'],
                'delegation_events_task_ts_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_events');
    }
};
