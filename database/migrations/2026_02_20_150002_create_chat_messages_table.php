<?php

declare(strict_types=1);

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
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_session_id');
            $table->uuid('connector_account_id');
            $table->string('direction', 16);
            $table->text('content');
            $table->json('attachment_ids')->nullable();
            $table->string('idempotency_key', 64);
            $table->string('provider_event_id', 255)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->timestampTz('provider_timestamp')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('chat_session_id')
                ->references('id')
                ->on('chat_sessions')
                ->cascadeOnDelete();

            $table->foreign('connector_account_id')
                ->references('id')
                ->on('connector_accounts')
                ->cascadeOnDelete();

            $table->unique(['connector_account_id', 'idempotency_key']);
            $table->index(['chat_session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
