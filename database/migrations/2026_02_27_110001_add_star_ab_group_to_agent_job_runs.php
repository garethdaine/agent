<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_job_runs', function (Blueprint $table) {
            $table->string('star_ab_group', 16)->nullable()->after('metadata_json');
        });
    }

    public function down(): void
    {
        Schema::table('agent_job_runs', function (Blueprint $table) {
            $table->dropColumn('star_ab_group');
        });
    }
};
