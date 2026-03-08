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
            $table->uuid('parent_session_id')->nullable()->after('id')->index();
            $table->unsignedTinyInteger('spawn_depth')->default(0)->after('parent_session_id');
            $table->foreign('parent_session_id')
                ->references('id')
                ->on('runtime_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('runtime_sessions', function (Blueprint $table) {
            $table->dropForeign(['parent_session_id']);
            $table->dropColumn(['parent_session_id', 'spawn_depth']);
        });
    }
};
