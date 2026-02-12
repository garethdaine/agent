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
        Schema::create('agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('cron_expression', 100);
            $table->string('timezone', 64);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('max_runtime_seconds');
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->string('runner_type', 16);
            $table->string('command_template', 2000);
            $table->string('task_markdown_path', 1024);
            $table->string('working_directory', 1024);
            $table->json('env_json')->nullable();
            $table->string('last_validated_executable_path', 1024)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_enabled', 'deleted_at'], 'agent_jobs_user_enabled_deleted_idx');
            $table->unique(['user_id', 'name', 'deleted_at'], 'agent_jobs_user_name_deleted_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_jobs');
    }
};
