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
        Schema::create('chat_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_message_id');
            $table->string('action_type', 32);
            $table->json('parameters');
            $table->string('status', 16)->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('executed_at')->nullable();

            $table->foreign('chat_message_id')
                ->references('id')
                ->on('chat_messages')
                ->cascadeOnDelete();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_actions');
    }
};
