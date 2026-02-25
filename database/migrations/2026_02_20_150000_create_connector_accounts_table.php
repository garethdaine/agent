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
        Schema::create('connector_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('name', 255);
            $table->text('credentials');
            $table->string('webhook_secret', 255)->nullable();
            $table->string('connection_mode', 16)->default('local');
            $table->string('status', 32)->default('disconnected');
            $table->json('config')->nullable();
            $table->string('account_key', 64);
            $table->timestamps();

            $table->unique(['provider', 'account_key']);
            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connector_accounts');
    }
};
