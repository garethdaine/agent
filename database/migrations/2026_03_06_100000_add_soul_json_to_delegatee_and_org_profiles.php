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
            $table->jsonb('soul_json')->nullable()->after('config_json');
        });

        Schema::table('org_agent_profiles', function (Blueprint $table) {
            $table->jsonb('soul_json')->nullable()->after('default_output_schema');
        });
    }

    public function down(): void
    {
        Schema::table('delegatee_profiles', function (Blueprint $table) {
            $table->dropColumn('soul_json');
        });

        Schema::table('org_agent_profiles', function (Blueprint $table) {
            $table->dropColumn('soul_json');
        });
    }
};
