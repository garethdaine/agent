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
        Schema::create('interrogation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interrogation_session_id')->constrained('interrogation_sessions')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->unsignedBigInteger('sequence');
            $table->json('payload');
            $table->timestampTz('event_ts', 3);
            $table->timestamps();

            $table->unique(['interrogation_session_id', 'sequence'], 'interr_events_session_sequence_unique');
            $table->index(['interrogation_session_id', 'event_type'], 'interr_events_session_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interrogation_events');
    }
};
