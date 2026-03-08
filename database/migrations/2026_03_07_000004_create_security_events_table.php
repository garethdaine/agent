<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_session_id')->nullable();
            $table->uuid('runtime_tool_call_id')->nullable();
            $table->string('event_type', 30);
            $table->string('severity', 20);
            $table->jsonb('details_json');
            $table->timestamp('created_at');

            $table->foreign('runtime_session_id')
                ->references('id')
                ->on('runtime_sessions')
                ->nullOnDelete();

            $table->foreign('runtime_tool_call_id')
                ->references('id')
                ->on('runtime_tool_calls')
                ->nullOnDelete();

            $table->index(['runtime_session_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
