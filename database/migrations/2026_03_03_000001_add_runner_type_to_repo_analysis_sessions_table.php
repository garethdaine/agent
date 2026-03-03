<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repo_analysis_sessions', function (Blueprint $table): void {
            $table->string('runner_type', 32)->default('claude')->after('analyzer_profile');
        });

        DB::table('repo_analysis_sessions')
            ->whereNull('runner_type')
            ->update(['runner_type' => 'claude']);
    }

    public function down(): void
    {
        Schema::table('repo_analysis_sessions', function (Blueprint $table): void {
            $table->dropColumn('runner_type');
        });
    }
};

