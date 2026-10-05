<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('brief');
            $table->string('status', 32)->default('received');
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('demand_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('todo');
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'assigned_to', 'status']);
            $table->index(['demand_id', 'status']);
        });

        Schema::create('demand_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('demand_tasks')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('summary', 240);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'demand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_events');
        Schema::dropIfExists('demand_tasks');
        Schema::dropIfExists('demands');
    }
};
