<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('runtime_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_session_id');
            $table->integer('sequence');
            $table->uuid('input_message_id')->nullable();
            $table->uuid('output_message_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestamps();

            $table->foreign('runtime_session_id')->references('id')->on('runtime_sessions')->cascadeOnDelete();

            $table->index(['runtime_session_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runtime_turns');
    }
};
