<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nl_parse_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('input_text', 200);
            $table->string('timezone', 64);
            $table->string('parser_path', 20); // rule_based, llm_fallback
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('cron_result', 100)->nullable();
            $table->json('active_hours_result')->nullable();
            $table->string('status', 30);
            $table->boolean('user_confirmed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'input_text', 'timezone', 'created_at'], 'nl_parse_idempotency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nl_parse_attempts');
    }
};
