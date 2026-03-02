<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_analysis_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_analysis_session_id')->constrained('repo_analysis_sessions')->cascadeOnDelete();
            $table->string('report_version', 64);
            $table->string('report_hash', 64);
            $table->string('status', 32)->default('generated');
            $table->json('payload_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('markdown_export_path', 1024)->nullable();
            $table->string('json_export_path', 1024)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('generated_at')->nullable();
            $table->timestamps();

            $table->index(
                ['repo_analysis_session_id', 'created_at'],
                'repo_analysis_reports_session_created_idx'
            );
            $table->index(['status', 'created_at'], 'repo_analysis_reports_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_analysis_reports');
    }
};
