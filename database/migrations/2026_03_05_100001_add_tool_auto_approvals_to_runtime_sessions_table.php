<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table): void {
            $table->json('tool_auto_approvals')->nullable()->after('browser_persistence_mode');
        });
    }

    public function down(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table): void {
            $table->dropColumn('tool_auto_approvals');
        });
    }
};
