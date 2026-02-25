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
        Schema::create('messenger_identity_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('connector_account_id');
            $table->string('provider_user_id', 255);
            $table->string('provider_username', 255)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('connector_account_id')
                ->references('id')
                ->on('connector_accounts')
                ->cascadeOnDelete();

            $table->unique(['connector_account_id', 'provider_user_id']);
            $table->index(['user_id', 'connector_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_identity_links');
    }
};
