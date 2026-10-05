<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('service_url', 2048)->nullable();
            $table->string('access_method', 32);
            $table->text('instructions');
            $table->date('review_due_on')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'archived_at', 'name']);
        });

        Schema::create('service_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_access_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('active_slot', 191)->nullable()->unique();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id', 'status']);
            $table->index(['service_access_id', 'status']);
        });

        Schema::create('service_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_access_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_access_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->timestamp('created_at');
            $table->index(['organization_id', 'service_access_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_access_events');
        Schema::dropIfExists('service_access_requests');
        Schema::dropIfExists('service_accesses');
    }
};
