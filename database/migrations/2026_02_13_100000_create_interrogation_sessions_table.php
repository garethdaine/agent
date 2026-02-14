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
        Schema::create('interrogation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('runner_type', 16);
            $table->string('project_directory', 1024);
            $table->string('interrogation_type', 16);
            $table->text('feature_brief')->nullable();
            $table->string('status', 24)->default('setup');
            $table->unsignedTinyInteger('phase')->default(0);
            $table->string('cli_session_id', 255)->nullable();
            $table->json('summary_json')->nullable();
            $table->json('plan_json')->nullable();
            $table->json('annotations_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status', 'deleted_at'], 'interr_sessions_user_status_deleted_idx');
            $table->index(['user_id', 'created_at'], 'interr_sessions_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interrogation_sessions');
    }
};
