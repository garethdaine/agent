<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('scheduled_path_failure_streak')->default(0)->after('last_validated_executable_path');
        });
    }

    public function down(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table): void {
            $table->dropColumn('scheduled_path_failure_streak');
        });
    }
};
