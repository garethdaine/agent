<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_interrogation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('runner_type', 16)->default('codex');
            $table->string('model', 120)->nullable();
            $table->string('project_directory', 1024);
            $table->string('interrogation_mode', 24)->default('workflow');
            $table->string('company_name', 255);
            $table->text('company_description')->nullable();
            $table->string('workflow_title', 255);
            $table->longText('workflow_brief');
            $table->json('target_teams_json')->nullable();
            $table->json('systems_json')->nullable();
            $table->string('status', 32)->default('setup');
            $table->unsignedTinyInteger('phase')->default(0);
            $table->unsignedInteger('current_round')->default(0);
            $table->string('cli_session_id')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('action_plan_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('summary_confirmed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status', 'deleted_at']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_interrogation_sessions');
    }
};
