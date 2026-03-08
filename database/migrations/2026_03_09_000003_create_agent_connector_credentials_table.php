<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
            $table->uuid('connector_id');
            $table->string('auth_type', 30);
            $table->binary('encrypted_data');
            $table->string('encryption_key_id', 64);
            $table->jsonb('scopes_granted')->default('[]');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('refresh_failure_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->integer('rotation_count')->default(0);
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('connector_id')->references('id')->on('agent_connectors');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['team_id', 'connector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_credentials');
    }
};
