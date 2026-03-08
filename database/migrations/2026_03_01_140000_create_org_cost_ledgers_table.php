<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_cost_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('org_agent_id')->nullable();
            $table->uuid('ritual_run_id')->nullable();
            $table->foreign('org_agent_id')->references('id')->on('org_agent_profiles')->nullOnDelete();
            $table->foreign('ritual_run_id')->references('id')->on('org_ritual_runs')->nullOnDelete();
            $table->bigInteger('token_count')->default(0);
            $table->bigInteger('runtime_ms')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes for rollup queries
            $table->index(['user_id', 'recorded_at']);
            $table->index(['org_agent_id', 'recorded_at']);
            $table->index(['ritual_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_cost_ledgers');
    }
};
