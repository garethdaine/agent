<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
            $table->string('name', 64);
            $table->string('slug', 64);
            $table->text('description');
            $table->string('version', 20);
            $table->string('author', 128);
            $table->string('risk_level', 20)->default('standard');
            $table->string('status', 20)->default('active');
            $table->jsonb('industries')->default('[]');
            $table->jsonb('memory_blocks')->default('[]');
            $table->jsonb('mcp_dependencies')->default('[]');
            $table->jsonb('tools_required')->default('[]');
            $table->jsonb('trigger_keywords')->default('[]');
            $table->jsonb('run_after')->default('[]');
            $table->boolean('requires_approval')->default(false);
            $table->text('skill_path');
            $table->string('checksum', 64);
            $table->jsonb('file_hashes');
            $table->jsonb('validation_result');
            $table->boolean('is_global')->default(false);
            $table->unsignedBigInteger('installed_by')->nullable();
            $table->timestamp('installed_at')->useCurrent();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unsignedBigInteger('paused_by')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('last_invoked_at')->nullable();
            $table->integer('invocation_count')->default(0);

            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('installed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('paused_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'status'], 'idx_skills_team_status');
        });

        DB::statement('CREATE INDEX idx_skills_team_industry ON agent_skills USING GIN (industries)');
        DB::statement('CREATE INDEX idx_skills_trigger_keywords ON agent_skills USING GIN (trigger_keywords)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_skills');
    }
};
