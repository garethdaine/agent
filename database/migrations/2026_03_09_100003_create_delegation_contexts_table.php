<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delegation_contexts')) {
            return;
        }

        Schema::create('delegation_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_task_id')->constrained('delegation_tasks')->cascadeOnDelete();
            $table->json('context_json');
            $table->string('propagation_direction')->default('down');
            $table->unsignedInteger('ttl_seconds')->default(3600);
            $table->unsignedBigInteger('created_by_agent_id')->nullable();
            $table->timestamps();

            $table->index(['delegation_task_id', 'propagation_direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_contexts');
    }
};
