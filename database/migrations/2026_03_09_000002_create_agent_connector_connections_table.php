<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
            $table->uuid('connector_id');
            $table->string('status', 20)->default('pending');
            $table->decimal('health_score', 3, 2)->default(1.00);
            $table->jsonb('config')->default('{}');
            $table->jsonb('webhook_subscriptions')->default('[]');
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->integer('action_count_24h')->default(0);
            $table->integer('error_count_24h')->default(0);
            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->unsignedBigInteger('disconnected_by')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('connector_id')->references('id')->on('agent_connectors');
            $table->foreign('connected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('disconnected_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['team_id', 'connector_id']);
            $table->index(['team_id', 'status'], 'idx_connections_team_status');
            $table->index('connector_id', 'idx_connections_connector');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_connections');
    }
};
