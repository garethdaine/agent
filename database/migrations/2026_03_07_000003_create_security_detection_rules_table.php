<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_detection_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('pattern');
            $table->string('pattern_type', 20);
            $table->string('severity', 20);
            $table->decimal('weight', 3, 2)->default(1.00);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'pattern_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_detection_rules');
    }
};
