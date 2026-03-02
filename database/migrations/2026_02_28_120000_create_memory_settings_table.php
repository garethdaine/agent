<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_settings table for encrypted user settings.
 *
 * No foreign key dependencies - can be created first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value'); // encrypted cast in model
            $table->timestamps();

            $table->unique(['user_id', 'key'], 'memory_settings_user_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_settings');
    }
};
