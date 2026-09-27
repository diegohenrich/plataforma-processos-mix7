<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->timestamp('last_heartbeat_at')->nullable()->after('started_at');
            $table->index(['ended_at', 'last_heartbeat_at'], 'task_time_entries_stale_heartbeat_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->dropIndex('task_time_entries_stale_heartbeat_index');
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
