<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_reporting_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subordinate_agent_id');
            $table->uuid('manager_agent_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('subordinate_agent_id')
                ->references('id')
                ->on('org_agent_profiles')
                ->cascadeOnDelete();

            $table->foreign('manager_agent_id')
                ->references('id')
                ->on('org_agent_profiles')
                ->cascadeOnDelete();

            // Each agent can have at most one manager
            $table->unique('subordinate_agent_id');
            $table->index('manager_agent_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_reporting_edges');
    }
};
