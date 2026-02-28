<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds runtime state tracking columns to connector_accounts:
     * - runtime_state: Current worker connection state (connected, reconnecting, disconnected, error)
     * - last_health_check_at: Timestamp of last health check from gateway manager
     * - runtime_error_message: Error details when in error state
     */
    public function up(): void
    {
        Schema::table('connector_accounts', function (Blueprint $table): void {
            // Runtime state - using string with check constraint for PostgreSQL compatibility
            $table->string('runtime_state', 16)->default('disconnected')->after('status');

            // Last health check timestamp
            $table->timestamp('last_health_check_at')->nullable()->after('runtime_state');

            // Error message for runtime errors
            $table->text('runtime_error_message')->nullable()->after('last_health_check_at');
        });

        // Add check constraint for valid runtime_state values (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE connector_accounts
                ADD CONSTRAINT connector_accounts_runtime_state_check
                CHECK (runtime_state IN ('connected', 'reconnecting', 'disconnected', 'error'))
            ");
        }

        // Add composite index for efficient queries by mode and state
        Schema::table('connector_accounts', function (Blueprint $table): void {
            $table->index(['connection_mode', 'runtime_state'], 'connector_accounts_mode_runtime_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove check constraint first (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE connector_accounts DROP CONSTRAINT IF EXISTS connector_accounts_runtime_state_check');
        }

        Schema::table('connector_accounts', function (Blueprint $table): void {
            $table->dropIndex('connector_accounts_mode_runtime_state_idx');
            $table->dropColumn(['runtime_state', 'last_health_check_at', 'runtime_error_message']);
        });
    }
};
