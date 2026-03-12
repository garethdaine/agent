<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_vault', function (Blueprint $table) {
            $table->string('credential_type', 30)->default('api_key')->after('key');
            $table->string('name', 255)->nullable()->after('credential_type');
            $table->string('url', 2048)->nullable()->after('name');
            $table->text('notes')->nullable()->after('metadata');
            $table->string('approval_mode', 20)->default('supervised')->after('notes');
            $table->timestamp('last_used_at')->nullable()->after('approval_mode');
            $table->timestamp('last_rotated_at')->nullable()->after('last_used_at');
            $table->timestamp('expires_at')->nullable()->after('last_rotated_at');

            $table->index(['user_id', 'credential_type']);
        });
    }

    public function down(): void
    {
        Schema::table('credential_vault', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'credential_type']);

            $table->dropColumn([
                'credential_type',
                'name',
                'url',
                'notes',
                'approval_mode',
                'last_used_at',
                'last_rotated_at',
                'expires_at',
            ]);
        });
    }
};
