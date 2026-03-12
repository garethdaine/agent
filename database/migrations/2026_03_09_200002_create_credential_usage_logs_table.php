<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('credential_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);
            $table->jsonb('context')->default('{}');
            $table->string('outcome', 20);
            $table->timestamp('created_at');

            $table->foreign('credential_id')
                ->references('id')
                ->on('credential_vault')
                ->cascadeOnDelete();

            $table->index(['credential_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_usage_logs');
    }
};
