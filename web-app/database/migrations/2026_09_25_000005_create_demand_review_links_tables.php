<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_review_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->char('token_hash', 64)->unique();
            $table->string('material_url', 2048);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['demand_id', 'version']);
            $table->index(['organization_id', 'demand_id', 'expires_at']);
        });

        Schema::create('demand_review_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demand_review_link_id')->constrained('demand_review_links')->cascadeOnDelete();
            $table->string('reviewer_name', 120);
            $table->string('type', 24);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['demand_review_link_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_review_responses');
        Schema::dropIfExists('demand_review_links');
    }
};
