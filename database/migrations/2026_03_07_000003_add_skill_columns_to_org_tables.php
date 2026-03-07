<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_agent_profiles', function (Blueprint $table) {
            $table->jsonb('skill_access_profile')->nullable()->after('authority_overrides');
        });

        Schema::table('org_ritual_templates', function (Blueprint $table) {
            $table->jsonb('required_skills')->nullable()->after('delivery_targets');
        });
    }

    public function down(): void
    {
        Schema::table('org_agent_profiles', function (Blueprint $table) {
            $table->dropColumn('skill_access_profile');
        });

        Schema::table('org_ritual_templates', function (Blueprint $table) {
            $table->dropColumn('required_skills');
        });
    }
};
