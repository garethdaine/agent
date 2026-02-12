<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('target_type', 120);
            $table->string('target_id', 120);
            $table->json('changed_fields_json')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('outcome', 24)->default('success');
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'agent_audit_logs_user_created_idx');
            $table->index(['action', 'created_at'], 'agent_audit_logs_action_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_audit_logs');
    }
};
