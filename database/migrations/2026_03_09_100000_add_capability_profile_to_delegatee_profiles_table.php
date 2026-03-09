<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegatee_profiles', function (Blueprint $table) {
            $table->jsonb('capability_profile')->nullable()->default(null)->after('soul_json');
        });
    }

    public function down(): void
    {
        Schema::table('delegatee_profiles', function (Blueprint $table) {
            $table->dropColumn('capability_profile');
        });
    }
};
