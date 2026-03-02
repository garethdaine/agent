<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_analysis_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_analysis_session_id')->constrained('repo_analysis_sessions')->cascadeOnDelete();
            $table->foreignId('repo_analysis_task_id')->nullable()->constrained('repo_analysis_tasks')->nullOnDelete();
            $table->string('artifact_type', 64);
            $table->string('artifact_key', 191);
            $table->string('content_hash', 64);
            $table->string('schema_version', 64)->nullable();
            $table->string('analyzer_version', 64)->nullable();
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->json('payload_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->unique(
                ['repo_analysis_session_id', 'artifact_key'],
                'repo_analysis_artifacts_session_artifact_key_unique'
            );
            $table->index(
                ['repo_analysis_session_id', 'artifact_type'],
                'repo_analysis_artifacts_session_type_idx'
            );
            $table->index(['content_hash'], 'repo_analysis_artifacts_content_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_analysis_artifacts');
    }
};
