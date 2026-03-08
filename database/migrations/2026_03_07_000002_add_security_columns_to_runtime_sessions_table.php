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
            $table->jsonb('security_config_json')->nullable();
            $table->jsonb('file_provenance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->dropColumn(['security_config_json', 'file_provenance']);
        });
    }
};
