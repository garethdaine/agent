<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interrogation_sessions', function (Blueprint $table) {
            $table->string('model', 128)->nullable()->after('runner_type');
        });
    }

    public function down(): void
    {
        Schema::table('interrogation_sessions', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
