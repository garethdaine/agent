<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connector_credential_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('credential_id');
            $table->string('event_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->jsonb('details')->default('{}');
            $table->timestamp('created_at');

            $table->foreign('credential_id')->references('id')->on('agent_connector_credentials');
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['credential_id', 'created_at'], 'idx_credential_events_cred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connector_credential_events');
    }
};
