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
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('connector_account_id');
            $table->string('provider', 32);
            $table->string('channel_id', 255);
            $table->string('thread_id', 255)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->foreign('connector_account_id')
                ->references('id')
                ->on('connector_accounts')
                ->cascadeOnDelete();

            $table->index(['connector_account_id', 'channel_id', 'thread_id'], 'chat_sessions_connector_channel_thread_idx');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
