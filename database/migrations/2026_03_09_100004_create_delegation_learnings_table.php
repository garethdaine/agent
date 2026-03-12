<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delegation_learnings')) {
            return;
        }

        Schema::create('delegation_learnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_graph_id')->constrained('delegation_graphs')->cascadeOnDelete();
            $table->foreignId('delegatee_profile_id')->constrained('delegatee_profiles')->cascadeOnDelete();
            $table->json('outcome_summary_json')->nullable();
            $table->json('success_patterns_json')->nullable();
            $table->json('failure_patterns_json')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_learnings');
    }
};
