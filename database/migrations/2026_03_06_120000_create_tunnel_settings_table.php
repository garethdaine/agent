<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunnel_settings', function (Blueprint $table) {
            $table->id();
            $table->json('settings')->default('{}');
            $table->string('status', 20)->default('unconfigured')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunnel_settings');
    }
};
