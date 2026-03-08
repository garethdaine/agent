<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id')->nullable();
            $table->uuid('connector_id');
            $table->string('action_name', 128);
            $table->string('type', 20)->default('approval');
            $table->string('status', 20)->default('pending');
            $table->uuid('requested_by_run_id')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_via', 20)->nullable();
            $table->jsonb('resolution_payload')->default('{}');
            $table->jsonb('request_context')->default('{}');
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('agent_connector_connections');
            $table->foreign('connector_id')->references('id')->on('agent_connectors');
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['connection_id', 'status'], 'idx_approvals_connection_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_approvals');
    }
};
