<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->uuid('connector_id');
            $table->string('event_type', 128);
            $table->string('external_event_id', 255)->nullable();
            $table->string('payload_hash', 64);
            $table->string('processing_status', 20)->default('received');
            $table->string('routed_to_workflow', 255)->nullable();
            $table->uuid('routed_to_job_id')->nullable();
            $table->integer('processing_duration_ms')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->foreign('connection_id')->references('id')->on('agent_connector_connections');
            $table->foreign('connector_id')->references('id')->on('agent_connectors');

            $table->unique(['connection_id', 'external_event_id']);
            $table->index('connection_id', 'idx_webhook_events_connection');
            $table->index('processing_status', 'idx_webhook_events_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_webhook_events');
    }
};
