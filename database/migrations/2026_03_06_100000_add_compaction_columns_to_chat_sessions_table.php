<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->text('compaction_summary')->nullable()->after('status');
            $table->unsignedInteger('compaction_message_count')->default(0)->after('compaction_summary');
            $table->timestampTz('compaction_at')->nullable()->after('compaction_message_count');
            $table->uuid('compaction_boundary_message_id')->nullable()->after('compaction_at');

            $table->foreign('compaction_boundary_message_id')
                ->references('id')
                ->on('chat_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->dropForeign(['compaction_boundary_message_id']);
            $table->dropColumn([
                'compaction_summary',
                'compaction_message_count',
                'compaction_at',
                'compaction_boundary_message_id',
            ]);
        });
    }
};
