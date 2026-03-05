<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_identity_links', function (Blueprint $table): void {
            $table->string('status', 16)->default('approved')->after('provider_username');
            $table->index(['connector_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('messenger_identity_links', function (Blueprint $table): void {
            $table->dropIndex(['connector_account_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
