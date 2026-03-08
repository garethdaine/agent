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
        Schema::create('agent_backup_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedTinyInteger('run_hour')->default(2);
            $table->unsignedTinyInteger('run_minute')->default(0);
            $table->unsignedSmallInteger('retention_days')->default(14);
            $table->timestampTz('last_run_at')->nullable();
            $table->string('last_status', 24)->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_backup_settings');
    }
};
