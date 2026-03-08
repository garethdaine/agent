<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_invocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->uuid('connector_id');
            $table->string('action_name', 128);
            $table->uuid('run_attempt_id')->nullable();
            $table->uuid('delegatee_id')->nullable();
            $table->string('workflow_key', 255)->nullable();
            $table->string('http_method', 10);
            $table->integer('http_status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('request_size_bytes')->nullable();
            $table->integer('response_size_bytes')->nullable();
            $table->integer('token_usage')->nullable();
            $table->integer('retry_count')->default(0);
            $table->string('outcome', 20);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->foreign('connection_id')->references('id')->on('agent_connector_connections');
            $table->foreign('connector_id')->references('id')->on('agent_connectors');

            $table->index(['connection_id', 'created_at'], 'idx_invocations_connection');
            $table->index('run_attempt_id', 'idx_invocations_run');
            $table->index(['workflow_key', 'created_at'], 'idx_invocations_workflow');
            $table->index(['outcome', 'created_at'], 'idx_invocations_outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_invocations');
    }
};
