<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_event_deduplication', function (Blueprint $table) {
            $table->id();
            $table->uuid('connector_account_id');
            $table->string('event_id', 255);
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');

            $table->foreign('connector_account_id')
                ->references('id')
                ->on('connector_accounts')
                ->cascadeOnDelete();

            $table->unique(['connector_account_id', 'event_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_event_deduplication');
    }
};
