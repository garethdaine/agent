<?php

declare(strict_types=1);

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
        Schema::create('delegation_verification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_task_id')->constrained('delegation_tasks')->cascadeOnDelete();
            $table->foreignId('delegation_attempt_id')->nullable()->constrained('delegation_attempts')->cascadeOnDelete();
            $table->string('step_type', 32);
            $table->unsignedTinyInteger('step_order');
            $table->string('verdict', 16);
            $table->json('evidence_json')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->index(
                ['delegation_task_id', 'step_order'],
                'delegation_verification_task_step_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_verification_results');
    }
};
