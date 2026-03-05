<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_analysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('project_directory', 1024);
            $table->string('analyzer_profile', 64)->default('default');
            $table->string('runner_type', 32)->default('claude');
            $table->string('status', 32)->default('setup');
            $table->unsignedTinyInteger('phase')->default(0);
            $table->string('snapshot_hash', 64)->nullable();
            $table->json('manifest_stats_json')->nullable();
            $table->json('report_summary_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'phase'], 'repo_analysis_sessions_status_phase_idx');
            $table->index(['user_id', 'status', 'deleted_at'], 'repo_analysis_sessions_user_status_deleted_idx');
            $table->index(['user_id', 'created_at'], 'repo_analysis_sessions_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_analysis_sessions');
    }
};
