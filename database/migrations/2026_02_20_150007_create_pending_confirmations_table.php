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
        Schema::create('pending_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_action_id');
            $table->uuid('chat_session_id');
            $table->uuid('connector_account_id');
            $table->string('confirmation_token', 64)->unique();
            $table->string('provider_message_id', 255)->nullable();
            $table->json('callback_data')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('chat_action_id')
                ->references('id')
                ->on('chat_actions')
                ->cascadeOnDelete();

            $table->foreign('chat_session_id')
                ->references('id')
                ->on('chat_sessions')
                ->cascadeOnDelete();

            $table->foreign('connector_account_id')
                ->references('id')
                ->on('connector_accounts')
                ->cascadeOnDelete();

            $table->index(['connector_account_id', 'provider_message_id'], 'pending_confirmations_connector_message_idx');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_confirmations');
    }
};
