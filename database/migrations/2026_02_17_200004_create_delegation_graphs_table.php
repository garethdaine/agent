<?php

declare(strict_types=1);

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
        Schema::create('delegation_graphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('cancellation_policy', 16)->default(config('delegation.default_cancellation_policy', 'drain'));
            $table->unsignedTinyInteger('max_parallel_tasks')->default(config('delegation.default_max_parallel_tasks', 5));
            $table->json('metadata_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['user_id', 'status', 'deleted_at'],
                'delegation_graphs_user_status_deleted_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_graphs');
    }
};
