<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->string('browser_profile_name')->nullable()->after('browser_persistence_mode');
            $table->string('browser_cdp_endpoint')->nullable()->after('browser_profile_name');
        });
    }

    public function down(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->dropColumn(['browser_profile_name', 'browser_cdp_endpoint']);
        });
    }
};
