<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('demand_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('demand_tasks')->cascadeOnDelete();
            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index(['depends_on_task_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_task_dependencies');
    }
};
