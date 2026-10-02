<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_capacity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('week_start');
            $table->unsignedSmallInteger('scheduled_minutes')->nullable();
            $table->json('absences');
            $table->string('change_type', 24);
            $table->timestamps();
            $table->index(['organization_id', 'professional_id', 'week_start', 'id'], 'team_capacity_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_capacity_snapshots');
    }
};
