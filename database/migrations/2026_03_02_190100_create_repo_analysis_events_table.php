<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_analysis_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_analysis_session_id')->constrained('repo_analysis_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 64);
            $table->json('payload_json')->nullable();
            $table->timestampTz('event_ts')->nullable();
            $table->unsignedTinyInteger('phase')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['repo_analysis_session_id', 'sequence'],
                'repo_analysis_events_session_sequence_unique'
            );
            $table->index(
                ['repo_analysis_session_id', 'created_at'],
                'repo_analysis_events_session_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_analysis_events');
    }
};
