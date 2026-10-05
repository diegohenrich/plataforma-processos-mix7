<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_tasks', function (Blueprint $table): void {
            $table->date('planned_start_on')->nullable()->after('estimate_minutes');
            $table->date('planned_due_on')->nullable()->after('planned_start_on');
        });
    }

    public function down(): void
    {
        Schema::table('demand_tasks', function (Blueprint $table): void {
            $table->dropColumn(['planned_start_on', 'planned_due_on']);
        });
    }
};
