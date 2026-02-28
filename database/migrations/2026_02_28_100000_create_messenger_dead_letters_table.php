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
        Schema::create('messenger_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('connector_account_id')
                ->constrained('connector_accounts')
                ->onDelete('cascade');
            $table->json('original_payload');
            $table->text('error_message');
            $table->json('error_history');
            $table->integer('attempts')->default(1);
            $table->timestamp('failed_at');
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();

            $table->index(['connector_account_id', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_dead_letters');
    }
};
