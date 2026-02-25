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
        if (! Schema::hasTable('connector_accounts')) {
            return;
        }

        if (Schema::hasColumn('connector_accounts', 'deleted_at')) {
            return;
        }

        Schema::table('connector_accounts', function (Blueprint $table): void {
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('connector_accounts')) {
            return;
        }

        if (! Schema::hasColumn('connector_accounts', 'deleted_at')) {
            return;
        }

        Schema::table('connector_accounts', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
