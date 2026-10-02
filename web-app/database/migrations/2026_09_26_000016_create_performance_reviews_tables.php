<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->constrained('demand_tasks')->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('reviewer_role', 32);
            $table->unsignedTinyInteger('reviewer_weight');
            $table->text('deadline_assessment');
            $table->text('quality_assessment');
            $table->text('evidence')->nullable();
            $table->text('external_factors')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'professional_id', 'created_at']);
            $table->unique(['task_id', 'reviewer_id']);
        });

        Schema::create('performance_review_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('response');
            $table->timestamps();
            $table->index(['performance_review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_responses');
        Schema::dropIfExists('performance_reviews');
    }
};
