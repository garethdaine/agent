<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_security_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('config_key', 100);
            $table->text('config_value');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'config_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_security_overrides');
    }
};
