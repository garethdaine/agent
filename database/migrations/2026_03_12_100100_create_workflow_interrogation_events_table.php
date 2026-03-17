<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_interrogation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_interrogation_session_id', 'workflow_interrogation_events_session_fk')
                ->constrained('workflow_interrogation_sessions')
                ->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->unsignedBigInteger('sequence');
            $table->json('payload');
            $table->timestampTz('event_ts', 3);
            $table->timestamps();

            $table->unique(['workflow_interrogation_session_id', 'sequence'], 'workflow_interrogation_events_sequence_unique');
            $table->index(['workflow_interrogation_session_id', 'event_type'], 'workflow_interrogation_events_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_interrogation_events');
    }
};
