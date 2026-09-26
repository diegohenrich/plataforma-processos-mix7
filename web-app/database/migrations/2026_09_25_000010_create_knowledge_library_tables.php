<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('title', 180);
            $table->text('content');
            $table->string('owner_name', 160)->nullable();
            $table->string('audience', 160)->nullable();
            $table->date('review_due_at')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('steps')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'type', 'archived_at']);
            $table->index(['organization_id', 'title']);
        });

        Schema::create('onboarding_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_item_id')->constrained('knowledge_items')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->json('steps');
            $table->timestamps();
            $table->unique(['knowledge_item_id', 'assigned_to']);
            $table->index(['organization_id', 'assigned_to']);
        });

        Schema::create('onboarding_assignment_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('onboarding_assignments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 180);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_assignment_steps');
        Schema::dropIfExists('onboarding_assignments');
        Schema::dropIfExists('knowledge_items');
    }
};
