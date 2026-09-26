<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_tasks', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->index();
        });

        DB::table('demand_tasks')
            ->where('status', TaskStatus::Completed->value)
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('demand_tasks', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropColumn('completed_at');
        });
    }
};
