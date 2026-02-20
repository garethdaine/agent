<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('providerable');
            $table->string('category', 64)->default('task_management');
            $table->string('driver', 64);
            $table->string('provider_user_id', 255)->nullable();
            $table->string('provider_workspace_id', 255)->nullable();
            $table->string('provider_workspace_name', 255)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('token_type', 32)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->json('scopes_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique([
                'providerable_type',
                'providerable_id',
                'category',
                'driver',
            ], 'providers_owner_category_driver_unique');
            $table->index(['user_id', 'category', 'driver'], 'providers_user_category_driver_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
