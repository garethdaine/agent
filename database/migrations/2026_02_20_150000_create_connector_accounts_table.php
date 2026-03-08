<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->string('runtime_state', 16)->default('disconnected');
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('runtime_error_message')->nullable();
            $table->json('config')->nullable();
            $table->string('account_key', 64);
            $table->softDeletesTz();
            $table->timestamps();

            $table->unique(['provider', 'account_key']);
            $table->index(['provider', 'status']);
            $table->index(['connection_mode', 'runtime_state'], 'connector_accounts_mode_runtime_state_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE connector_accounts
                ADD CONSTRAINT connector_accounts_runtime_state_check
                CHECK (runtime_state IN ('connected', 'reconnecting', 'disconnected', 'error'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_accounts');
    }
};
