<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->boolean('star_preamble_enabled')->nullable()->after('active_hours_config');
            $table->boolean('targeted_retry_enabled')->nullable()->after('star_preamble_enabled');
            $table->unsignedTinyInteger('max_retries')->nullable()->after('targeted_retry_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table) {
            $table->dropColumn(['star_preamble_enabled', 'targeted_retry_enabled', 'max_retries']);
        });
    }
};
