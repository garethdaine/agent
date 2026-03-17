<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_interrogation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_interrogation_session_id', 'workflow_interrogation_batches_session_fk')
                ->constrained('workflow_interrogation_sessions')
                ->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_interrogation_session_id', 'round'], 'workflow_interrogation_batches_round_unique');
            $table->index(['workflow_interrogation_session_id', 'is_active'], 'workflow_interrogation_batches_active_idx');
        });

        Schema::create('workflow_interrogation_batch_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_interrogation_batch_id', 'workflow_interrogation_batch_questions_batch_fk')
                ->constrained('workflow_interrogation_batches')
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('question_key', 120);
            $table->text('prompt');
            $table->string('answer_type', 24);
            $table->json('options_json')->nullable();
            $table->boolean('is_required')->default(true);
            $table->text('rationale')->nullable();
            $table->string('category', 120)->nullable();
            $table->timestamps();

            $table->unique(['workflow_interrogation_batch_id', 'question_key'], 'workflow_interrogation_batch_questions_key_unique');
            $table->index(['workflow_interrogation_batch_id', 'position'], 'workflow_interrogation_batch_questions_position_idx');
        });

        Schema::create('workflow_interrogation_batch_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_interrogation_batch_question_id', 'workflow_interrogation_batch_answers_question_fk')
                ->constrained('workflow_interrogation_batch_questions')
                ->cascadeOnDelete();
            $table->string('answer_type', 24);
            $table->text('answer_text')->nullable();
            $table->text('selected_option')->nullable();
            $table->json('selected_options_json')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestamps();

            $table->unique('workflow_interrogation_batch_question_id', 'workflow_interrogation_batch_answers_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_interrogation_batch_answers');
        Schema::dropIfExists('workflow_interrogation_batch_questions');
        Schema::dropIfExists('workflow_interrogation_batches');
    }
};
