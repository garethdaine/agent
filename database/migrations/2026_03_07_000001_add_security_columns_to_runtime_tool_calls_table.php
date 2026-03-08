<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runtime_tool_calls', function (Blueprint $table) {
            $table->string('content_trust_level', 20)->nullable();
            $table->decimal('injection_score', 3, 2)->nullable();
            $table->string('injection_action', 10)->nullable();
            $table->boolean('content_sanitized')->default(false);

            $table->index('content_trust_level');
        });
    }

    public function down(): void
    {
        Schema::table('runtime_tool_calls', function (Blueprint $table) {
            $table->dropIndex(['content_trust_level']);
            $table->dropColumn([
                'content_trust_level',
                'injection_score',
                'injection_action',
                'content_sanitized',
            ]);
        });
    }
};
