<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegatee_profiles', function (Blueprint $table) {
            $table->decimal('trust_score', 3, 2)->nullable()->after('is_active');
            $table->timestamp('trust_updated_at')->nullable()->after('trust_score');
        });
    }

    public function down(): void
    {
        Schema::table('delegatee_profiles', function (Blueprint $table) {
            $table->dropColumn(['trust_score', 'trust_updated_at']);
        });
    }
};
